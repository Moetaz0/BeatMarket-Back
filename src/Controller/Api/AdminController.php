<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\Order;
use App\Entity\Beat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/admin', name: 'api_admin_')]
class AdminController extends AbstractController
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

    /**
     * Get Dashboard Statistics
     */
    #[Route('/stats', name: 'dashboard_stats', methods: ['GET'])]
    public function getDashboardStats(): JsonResponse
    {
        try {
            $userCount = $this->em->getRepository(User::class)->count([]);
            $beatCount = $this->em->getRepository(Beat::class)->count([]);
            $orderCount = $this->em->getRepository(Order::class)->count([]);

            // Calculate total revenue
            $orders = $this->em->getRepository(Order::class)->findAll();
            $totalRevenue = 0;
            foreach ($orders as $order) {
                $totalRevenue += $order->getTotalAmount() ?? 0;
            }

            return $this->json([
                'users' => $userCount,
                'beats' => $beatCount,
                'orders' => $orderCount,
                'totalRevenue' => $totalRevenue,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to fetch dashboard stats'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create Admin Account
     */
    #[Route('/create-admin', name: 'create_admin', methods: ['POST'])]
    public function createAdmin(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validate required fields
            $email = trim($data['email'] ?? '');
            $fullName = trim($data['fullName'] ?? '');

            if (!$email || !$fullName) {
                return $this->json(['error' => 'email and fullName are required'], Response::HTTP_BAD_REQUEST);
            }

            // Check if email already exists
            $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                if (in_array('ROLE_ADMIN', $existingUser->getRoles(), true)) {
                    return $this->json(['error' => 'An admin with this email already exists'], Response::HTTP_CONFLICT);
                }

                return $this->json(['error' => 'A user with this email already exists'], Response::HTTP_CONFLICT);
            }

            // Generate random password
            $generatedPassword = $this->generatePassword();

            // Create new admin as a normal User with ROLE_ADMIN
            $admin = new User();
            $admin->setEmail($email);
            $admin->setUsername($fullName); // Use fullName as username
            $admin->setPassword($this->hasher->hashPassword($admin, $generatedPassword));
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setIsVerified(true);

            $this->em->persist($admin);
            $this->em->flush();

            // Send welcome email with credentials
            $this->sendAdminWelcomeEmail($email, $fullName, $generatedPassword);

            return $this->json([
                'message' => 'Admin account created successfully',
                'admin' => [
                    'id' => $admin->getId(),
                    'email' => $admin->getEmail(),
                    'fullName' => $fullName,
                ],
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to create admin: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get Admin List with Pagination
     */
    #[Route('/list', name: 'admin_list', methods: ['GET'])]
    public function getAdmins(Request $request): JsonResponse
    {
        try {
            $page = (int) ($request->query->get('page', 1));
            $limit = (int) ($request->query->get('limit', 10));

            if ($page < 1) {
                $page = 1;
            }
            if ($limit < 1) {
                $limit = 10;
            }

            $offset = ($page - 1) * $limit;

            $userRepository = $this->em->getRepository(User::class);
            $criteria = ['userRole' => 'ROLE_ADMIN'];
            $admins = $userRepository->findBy($criteria, ['id' => 'DESC'], $limit, $offset);
            $totalCount = $userRepository->count($criteria);

            $adminData = [];
            foreach ($admins as $admin) {
                $adminData[] = [
                    'id' => $admin->getId(),
                    'email' => $admin->getEmail(),
                    'username' => $admin->getUsername(),
                    'isVerified' => $admin->isVerified(),
                    'createdAt' => $admin->getCreatedAt()?->format('Y-m-d H:i:s'),
                ];
            }

            return $this->json([
                'admins' => $adminData,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'totalPages' => ceil($totalCount / $limit),
                ],
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to fetch admins'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete Admin Account
     */
    #[Route('/{id}', name: 'delete_admin', methods: ['DELETE'])]
    public function deleteAdmin(string $id): JsonResponse
    {
        try {
            $admin = $this->em->getRepository(User::class)->find($id);

            if (!$admin || !in_array('ROLE_ADMIN', $admin->getRoles(), true)) {
                return $this->json(['error' => 'Admin not found'], Response::HTTP_NOT_FOUND);
            }

            $this->em->remove($admin);
            $this->em->flush();

            return $this->json(['message' => 'Admin deleted successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to delete admin'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get User Statistics
     */
    #[Route('/users/statistics', name: 'user_statistics', methods: ['GET'])]
    public function getUserStatistics(): JsonResponse
    {
        try {
            $userRepository = $this->em->getRepository(User::class);

            // Total users
            $totalUsers = $userRepository->count([]);

            // Users created this month
            $monthStart = new \DateTime('first day of this month');
            $monthEnd = new \DateTime('last day of this month');

            $qb = $userRepository->createQueryBuilder('u');
            $thisMonthUsers = $qb
                ->where('u.createdAt >= :start AND u.createdAt <= :end')
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd)
                ->getQuery()
                ->getResult();
            $thisMonthCount = count($thisMonthUsers);

            // Verified vs Unverified
            $verifiedCount = $userRepository->count(['isVerified' => true]);
            $unverifiedCount = $totalUsers - $verifiedCount;

            return $this->json([
                'totalUsers' => $totalUsers,
                'thisMonthUsers' => $thisMonthCount,
                'verified' => $verifiedCount,
                'unverified' => $unverifiedCount,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to fetch user statistics'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate a random password
     */
    private function generatePassword(int $length = 12): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $password;
    }


    private function sendAdminWelcomeEmail(string $email, string $fullName, string $password): void
    {
        try {
            $htmlContent = $this->twig->render('emails/admin_welcome.html.twig', [
                'fullName' => $fullName,
                'email' => $email,
                'password' => $password,
                'loginUrl' => 'https://yourdomain.com/login', // Update with your actual login URL
            ]);

            $emailMsg = (new Email())
                ->from('no-reply@beatmarket.com')
                ->to($email)
                ->subject('Your Admin Account has been created - BeatMarket')
                ->html($htmlContent);

            $this->mailer->send($emailMsg);
        } catch (\Exception $e) {
            // Log the error but don't fail the admin creation
            // You might want to add logging here
        }
    }
}
