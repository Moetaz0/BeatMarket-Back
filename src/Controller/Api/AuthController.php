<?php
// src/Controller/Api/AuthController.php
namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\RefreshToken;
use App\Entity\ActionCode;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    private EntityManagerInterface $em;
    private JWTTokenManagerInterface $jwtManager;
    private UserPasswordHasherInterface $hasher;
    private MailerInterface $mailer;

    public function __construct(
        EntityManagerInterface $em,
        JWTTokenManagerInterface $jwtManager,
        UserPasswordHasherInterface $hasher,
        MailerInterface $mailer
    ) {
        $this->em = $em;
        $this->jwtManager = $jwtManager;
        $this->hasher = $hasher;
        $this->mailer = $mailer;
    }
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Extract and trim input
        $email = trim($data['email'] ?? '');
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $role = $data['role'] ?? 'ROLE_ARTIST'; // default role
        

        // Validate required fields
        if (!$email || !$username || !$password) {
            return $this->json(['error' => 'email, username, and password are required'], Response::HTTP_BAD_REQUEST);
        }

        // Check if email or username already exists
        $repo = $this->em->getRepository(User::class);
        if ($repo->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'email already used'], Response::HTTP_CONFLICT);
        }
        if ($repo->findOneBy(['username' => $username])) {
            return $this->json(['error' => 'username already used'], Response::HTTP_CONFLICT);
        }

        // Create new user
        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setRoles([$role]); // sets userRole internally
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->setIsVerified(false);
        $user->setRoles([$role]);
        $this->em->persist($user);
        $this->em->flush();

        // Optional: create email verification code
        $code = random_int(100000, 999999);
        $ac = new ActionCode();
        $ac->setUser($user);
        $ac->setCode((string) $code);
        $ac->setPurpose(ActionCode::PURPOSE_EMAIL_VERIFY);
        $ac->setExpiresAt(new \DateTime('+2 days'));

        $this->em->persist($ac);
        $this->em->flush();

        // Send verification email
        $emailMsg = (new Email())
            ->from('no-reply@yourdomain.com')
            ->to($user->getEmail())
            ->subject('Verify your email')
            ->text("Your verification code: $code");

        $this->mailer->send($emailMsg);

        return $this->json([
            'message' => 'user created successfully, verification email sent'
        ], Response::HTTP_CREATED);
    }


    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $remember = (bool) ($data['remember'] ?? false);

        if (!$email || !$password) {
            return $this->json(['error' => 'email and password required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user || !$this->hasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        // create JWT
        $jwt = $this->jwtManager->create($user);

        $response = [
            'token' => $jwt,
            'expires_in' => 900 // equal to token_ttl in config (seconds)
        ];

        if ($remember) {
            $refresh = new RefreshToken();
            $refresh->setUser($user);
            $refresh->setToken(bin2hex(random_bytes(64)));
            $refresh->setExpiresAt(new \DateTime('+30 days'));
            $this->em->persist($refresh);
            $this->em->flush();
            $response['refresh_token'] = $refresh->getToken();
            $response['refresh_expires_at'] = $refresh->getExpiresAt()->format(\DateTime::ATOM);
        }

        return $this->json($response);
    }

    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['refresh_token'] ?? null;
        if (!$token) {
            return $this->json(['error' => 'refresh_token required'], Response::HTTP_BAD_REQUEST);
        }

        $rtRepo = $this->em->getRepository(RefreshToken::class);
        $rt = $rtRepo->findOneBy(['token' => $token]);
        if (!$rt || $rt->isRevoked() || $rt->getExpiresAt() < new \DateTime()) {
            return $this->json(['error' => 'invalid_or_expired_refresh_token'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $rt->getUser();
        $jwt = $this->jwtManager->create($user);

        // rotate refresh token (best practice)
        $rt->setToken(bin2hex(random_bytes(64)));
        $rt->setExpiresAt(new \DateTime('+30 days'));
        $this->em->flush();

        return $this->json([
            'token' => $jwt,
            'expires_in' => 900,
            'refresh_token' => $rt->getToken(),
            'refresh_expires_at' => $rt->getExpiresAt()->format(\DateTime::ATOM)
        ]);
    }

    #[Route('/forgot', name: 'forgot', methods: ['POST'])]
    public function forgot(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email)
            return $this->json(['message' => 'ok'], Response::HTTP_OK);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            // Do not reveal existence
            return $this->json(['message' => 'If account exists, email sent'], Response::HTTP_OK);
        }

        $code = random_int(100000, 999999);
        $ac = new ActionCode();
        $ac->setUser($user);
        $ac->setCode((string) $code);
        $ac->setPurpose(ActionCode::PURPOSE_PASSWORD_RESET);
        $ac->setExpiresAt(new \DateTime('+15 minutes'));
        $this->em->persist($ac);
        $this->em->flush();

        $emailMsg = (new Email())
            ->from('no-reply@yourdomain.com')
            ->to($user->getEmail())
            ->subject('Password reset code')
            ->text("Your password reset code is: $code (valid for 15 minutes)");

        $this->mailer->send($emailMsg);

        return $this->json(['message' => 'If account exists, email sent'], Response::HTTP_OK);
    }

    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function reset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $code = $data['code'] ?? null;
        $newPassword = $data['password'] ?? null;

        if (!$email || !$code || !$newPassword) {
            return $this->json(['error' => 'email, code and password required'], Response::HTTP_BAD_REQUEST);
        }

        $acRepo = $this->em->getRepository(ActionCode::class);
        $action = $acRepo->findOneBy(['code' => (string) $code, 'purpose' => ActionCode::PURPOSE_PASSWORD_RESET, 'used' => false]);

        if (!$action || $action->getExpiresAt() < new \DateTime() || $action->getUser()->getEmail() !== $email) {
            return $this->json(['error' => 'invalid_or_expired_code'], Response::HTTP_BAD_REQUEST);
        }

        $user = $action->getUser();
        $user->setPassword($this->hasher->hashPassword($user, $newPassword));
        $action->setUsed(true);
        $this->em->flush();

        return $this->json(['message' => 'password updated'], Response::HTTP_OK);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $refresh = $data['refresh_token'] ?? null;
        if (!$refresh) {
            return $this->json(['message' => 'no refresh token provided'], Response::HTTP_BAD_REQUEST);
        }

        $rtRepo = $this->em->getRepository(RefreshToken::class);
        $rt = $rtRepo->findOneBy(['token' => $refresh]);
        if ($rt) {
            $rt->setRevoked(true);
            $this->em->flush();
        }

        return $this->json(['message' => 'logged out']);
    }
}
