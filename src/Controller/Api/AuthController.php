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
use Twig\Environment;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    private EntityManagerInterface $em;
    private JWTTokenManagerInterface $jwtManager;
    private UserPasswordHasherInterface $hasher;
    private MailerInterface $mailer;
    private Environment $twig;

    public function __construct(
        EntityManagerInterface $em,
        JWTTokenManagerInterface $jwtManager,
        UserPasswordHasherInterface $hasher,
        MailerInterface $mailer,
        Environment $twig
    ) {
        $this->em = $em;
        $this->jwtManager = $jwtManager;
        $this->hasher = $hasher;
        $this->mailer = $mailer;
        $this->twig = $twig;
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
        $htmlContent = $this->twig->render('emails/verify_email.html.twig', [
            'username' => $user->getUsername(),
            'code' => $code
        ]);

        $emailMsg = (new Email())
            ->from('no-reply@yourdomain.com')
            ->to($user->getEmail())
            ->subject('Verify your email')
            ->html($htmlContent);

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

        // Debug: log token creation
        error_log("JWT Token created for user: " . $user->getUserIdentifier());
        error_log("Token payload user: " . $user->getId());

        $response = [
            'token' => $jwt,
            'expires_in' => 3600 // match token_ttl in config (seconds)
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
    #[Route('/verify', name: 'verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $code = $data['code'] ?? null;

        if (!$email || !$code) {
            return $this->json(['error' => 'email and code are required'], Response::HTTP_BAD_REQUEST);
        }

        // Fetch the user by email
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['error' => 'user not found'], Response::HTTP_NOT_FOUND);
        }

        // Fetch the ActionCode entry associated with the user and the given code
        $acRepo = $this->em->getRepository(ActionCode::class);
        $actionCode = $acRepo->findOneBy([
            'user' => $user,
            'code' => $code,
            'purpose' => ActionCode::PURPOSE_EMAIL_VERIFY,
            'used' => false
        ]);

        if (!$actionCode) {
            return $this->json(['error' => 'invalid or expired verification code'], Response::HTTP_BAD_REQUEST);
        }

        // Check if the code has expired
        if ($actionCode->getExpiresAt() < new \DateTime()) {
            return $this->json(['error' => 'verification code expired'], Response::HTTP_BAD_REQUEST);
        }

        // Mark the action code as used
        $actionCode->setUsed(true);
        $user->setIsVerified(true);  // Mark user as verified
        $this->em->flush();

        return $this->json(['message' => 'email verified successfully'], Response::HTTP_OK);
    }


    #[Route('/forgot', name: 'forgot', methods: ['POST'])]
    public function forgot(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            // Handle the case when email is not provided
            return $this->json(['error' => 'Email is required'], Response::HTTP_BAD_REQUEST);
        }

        // Look up user by email
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            // Don't reveal whether an account exists or not
            return $this->json(['message' => 'If account exists, email sent'], Response::HTTP_OK);
        }

        // Generate a random password reset code
        $code = random_int(100000, 999999);

        // Create a new ActionCode object for password reset
        $ac = new ActionCode();
        $ac->setUser($user);
        $ac->setCode((string) $code);
        $ac->setPurpose(ActionCode::PURPOSE_PASSWORD_RESET);
        $ac->setExpiresAt(new \DateTime('+15 minutes'));

        // Persist the ActionCode entity to the database
        $this->em->persist($ac);
        $this->em->flush();

        // Prepare the email content
        $htmlContent = $this->twig->render('emails/reset_password.html.twig', [
            'username' => $user->getUsername(),
            'code' => $code
        ]);

        $emailMsg = (new Email())
            ->from('no-reply@yourdomain.com') // Change to your sender address
            ->to($user->getEmail())
            ->subject('Password reset code')
            ->html($htmlContent);

        try {
            // Attempt to send the email
            $this->mailer->send($emailMsg);
            return $this->json(['message' => 'If account exists, email sent'], Response::HTTP_OK);
        } catch (\Exception $e) {
            // If email sending fails, log the error and return a failure message
            $this->logger->error("Error sending password reset email: " . $e->getMessage());
            return $this->json(['error' => 'Failed to send email. Please try again later.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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
