<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\ActionCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[Route('/api/user', name: 'api_user_')]
class UserController extends AbstractController
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;
    private MailerInterface $mailer;
    private Environment $twig;

    public function __construct(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        MailerInterface $mailer,
        Environment $twig
    ) {
        $this->em = $em;
        $this->hasher = $hasher;
        $this->mailer = $mailer;
        $this->twig = $twig;
    }


    #[Route('/profile', name: 'profile', methods: ['GET'])]
    public function getProfile(Request $request): JsonResponse
    {
        // Debug: log authorization header
        $authHeader = $request->headers->get('Authorization');
        error_log("Authorization header: " . ($authHeader ?? 'MISSING'));

        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'phone' => $user->getPhone(),
            'profilePicture' => $user->getProfilePicture(),
            'role' => $user->getRoles()[0] ?? 'ROLE_USER',
            'isVerified' => $user->isVerified(),
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'wallet' => $user->getWallet() ? [
                'balance' => $user->getWallet()->getBalance()
            ] : null
        ]);
    }


    #[Route('/settings', name: 'update_settings', methods: ['PUT', 'PATCH'])]
    public function updateSettings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        // Debug: log received data
        error_log("Update settings data: " . json_encode($data));

        // Update username if provided
        if (isset($data['username'])) {
            $username = trim($data['username']);

            // Check if username is already taken by another user
            $existingUser = $this->em->getRepository(User::class)
                ->findOneBy(['username' => $username]);

            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                return $this->json(
                    ['error' => 'Username already taken'],
                    Response::HTTP_CONFLICT
                );
            }

            $user->setUsername($username);
        }

        // Update phone if provided
        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }

        // Update profile picture if provided and not empty
        if (isset($data['profilePicture']) && !empty(trim($data['profilePicture']))) {
            error_log("Setting profile picture to: " . $data['profilePicture']);
            $user->setProfilePicture($data['profilePicture']);
        }

        $this->em->flush();

        return $this->json([
            'message' => 'Settings updated successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'phone' => $user->getPhone(),
                'profilePicture' => $user->getProfilePicture()
            ]
        ]);
    }


    #[Route('/security/password', name: 'change_password', methods: ['PUT'])]
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $currentPassword = $data['currentPassword'] ?? null;
        $newPassword = $data['newPassword'] ?? null;

        if (!$currentPassword || !$newPassword) {
            return $this->json(
                ['error' => 'currentPassword and newPassword are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Verify current password
        if (!$this->hasher->isPasswordValid($user, $currentPassword)) {
            return $this->json(
                ['error' => 'Current password is incorrect'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Validate new password strength (optional)
        if (strlen($newPassword) < 8) {
            return $this->json(
                ['error' => 'New password must be at least 8 characters long'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Update password
        $hashedPassword = $this->hasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $this->em->flush();

        return $this->json([
            'message' => 'Password changed successfully'
        ]);
    }


    #[Route('/security/email', name: 'change_email', methods: ['PUT'])]
    public function changeEmail(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $newEmail = trim($data['newEmail'] ?? '');
        $password = $data['password'] ?? null;

        if (!$newEmail || !$password) {
            return $this->json(
                ['error' => 'newEmail and password are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Verify password for security
        if (!$this->hasher->isPasswordValid($user, $password)) {
            return $this->json(
                ['error' => 'Password is incorrect'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Check if email is already in use
        $existingUser = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $newEmail]);

        if ($existingUser) {
            return $this->json(
                ['error' => 'Email already in use'],
                Response::HTTP_CONFLICT
            );
        }

        // Create verification code for new email
        $code = random_int(100000, 999999);
        $ac = new ActionCode();
        $ac->setUser($user);
        $ac->setCode((string) $code);
        $ac->setPurpose(ActionCode::PURPOSE_EMAIL_VERIFY);
        $ac->setExpiresAt(new \DateTime('+1 day'));

        $this->em->persist($ac);
        $this->em->flush();

        // Send verification email to new address
        try {
            $htmlContent = $this->twig->render('emails/verify_email.html.twig', [
                'username' => $user->getUsername(),
                'code' => $code
            ]);

            $emailMsg = (new Email())
                ->from('no-reply@yourdomain.com')
                ->to($newEmail)
                ->subject('Verify your new email address')
                ->html($htmlContent);

            $this->mailer->send($emailMsg);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Failed to send verification email'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->json([
            'message' => 'Verification code sent to new email. Please verify to complete email change.',
            'newEmail' => $newEmail
        ]);
    }

    /**
     * POST /api/user/security/email/verify - Verify new email with code
     * 
     * Request body:
     * {
     *   "newEmail": "newemail@example.com",
     *   "code": "123456"
     * }
     */
    #[Route('/security/email/verify', name: 'verify_email_change', methods: ['POST'])]
    public function verifyEmailChange(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $newEmail = trim($data['newEmail'] ?? '');
        $code = $data['code'] ?? null;

        if (!$newEmail || !$code) {
            return $this->json(
                ['error' => 'newEmail and code are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Find the verification code
        $acRepo = $this->em->getRepository(ActionCode::class);
        $actionCode = $acRepo->findOneBy([
            'user' => $user,
            'code' => $code,
            'purpose' => ActionCode::PURPOSE_EMAIL_VERIFY
        ]);

        if (!$actionCode) {
            return $this->json(
                ['error' => 'Invalid verification code'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($actionCode->getExpiresAt() < new \DateTime()) {
            return $this->json(
                ['error' => 'Verification code has expired'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Update email
        $user->setEmail($newEmail);
        $user->setIsVerified(true);

        // Remove used action code
        $this->em->remove($actionCode);
        $this->em->flush();

        return $this->json([
            'message' => 'Email changed successfully',
            'newEmail' => $newEmail
        ]);
    }

    /**
     * DELETE /api/user/account - Delete user account
     * 
     * Request body:
     * {
     *   "password": "currentPassword",
     *   "confirmation": "DELETE"
     * }
     */
    #[Route('/account', name: 'delete_account', methods: ['DELETE'])]
    public function deleteAccount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $password = $data['password'] ?? null;
        $confirmation = $data['confirmation'] ?? null;

        if (!$password || $confirmation !== 'DELETE') {
            return $this->json(
                ['error' => 'Password and confirmation "DELETE" are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Verify password
        if (!$this->hasher->isPasswordValid($user, $password)) {
            return $this->json(
                ['error' => 'Password is incorrect'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Delete user account
        $this->em->remove($user);
        $this->em->flush();

        return $this->json([
            'message' => 'Account deleted successfully'
        ]);
    }

    /**
     * GET /api/user/security - Get security settings overview
     */
    #[Route('/security', name: 'security_overview', methods: ['GET'])]
    public function getSecurityOverview(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'email' => $user->getEmail(),
            'isVerified' => $user->isVerified(),
            'accountCreated' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'lastPasswordChange' => null, // TODO: Add field to track this
            'twoFactorEnabled' => false // TODO: Implement 2FA
        ]);
    }
}
