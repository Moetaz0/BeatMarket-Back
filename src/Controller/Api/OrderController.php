<?php

namespace App\Controller\Api;

use App\Entity\Beat;
use App\Entity\License;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Entity\Wallet;
use App\Entity\WalletTransaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment as TwigEnvironment;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[Route('/api/orders', name: 'api_orders_')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly TwigEnvironment $twig
    ) {
    }

    /**
     * POST /api/orders/checkout
     * Wallet checkout: creates an order, deducts wallet balance, creates wallet transaction.
     *
     * Body:
     * {
     *   "items": [
     *     {"beatId": 1, "quantity": 1, "licenseId": 2}
     *   ]
     * }
     */
    #[Route('/checkout', name: 'checkout', methods: ['POST'])]
    public function checkout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        $itemsPayload = $payload['items'] ?? null;

        if (!is_array($itemsPayload) || count($itemsPayload) === 0) {
            return $this->json(['error' => 'Items are required'], Response::HTTP_BAD_REQUEST);
        }

        $wallet = $user->getWallet();
        if (!$wallet) {
            $wallet = new Wallet();
            $wallet->setUser($user);
            $wallet->setBalance(0);
            $this->em->persist($wallet);
            $this->em->flush();
        }

        $beatRepo = $this->em->getRepository(Beat::class);
        $licenseRepo = $this->em->getRepository(License::class);

        $order = new Order();
        $order->setUser($user);

        $total = 0.0;

        foreach ($itemsPayload as $index => $itemPayload) {
            $beatId = $itemPayload['beatId'] ?? null;
            $quantity = (int) ($itemPayload['quantity'] ?? 1);
            $licenseId = $itemPayload['licenseId'] ?? null;

            if (!$beatId) {
                return $this->json(['error' => "Missing beatId for item #" . ($index + 1)], Response::HTTP_BAD_REQUEST);
            }
            if ($quantity < 1) {
                return $this->json(['error' => "Invalid quantity for item #" . ($index + 1)], Response::HTTP_BAD_REQUEST);
            }

            /** @var Beat|null $beat */
            $beat = $beatRepo->find($beatId);
            if (!$beat) {
                return $this->json(['error' => "Beat not found: " . $beatId], Response::HTTP_NOT_FOUND);
            }

            // Check if beat is already exclusively owned by someone else
            if ($beat->isExclusive() && $beat->getExclusiveOwner()?->getId() !== $user->getId()) {
                return $this->json(['error' => "Beat '" . $beat->getTitle() . "' is exclusively owned by another user"], Response::HTTP_BAD_REQUEST);
            }

            // Pricing rule:
            // - Base price is Beat.price
            // - If licenseId provided, use that license multiplier
            // - Else if Beat has a license, use Beat.license multiplier
            // - Else multiplier = 1
            $multiplier = 1.0;
            $selectedLicense = null;

            if ($licenseId) {
                $selectedLicense = $licenseRepo->find($licenseId);
                if (!$selectedLicense) {
                    return $this->json(['error' => "License not found: " . $licenseId], Response::HTTP_NOT_FOUND);
                }
                $multiplier = $selectedLicense->getPriceMultiplier();
            } elseif ($beat->getLicense()) {
                $selectedLicense = $beat->getLicense();
                $multiplier = $beat->getLicense()->getPriceMultiplier();
            }

            $unitPrice = round($beat->getPrice() * $multiplier, 2);
            $lineTotal = round($unitPrice * $quantity, 2);
            $total = round($total + $lineTotal, 2);

            $orderItem = new OrderItem();
            $orderItem->setBeat($beat);
            $orderItem->setQuantity($quantity);
            $orderItem->setPrice($unitPrice);
            $order->addItem($orderItem);

            $this->em->persist($orderItem);
        }

        if ($wallet->getBalance() < $total) {
            return $this->json([
                'error' => 'Insufficient wallet balance',
                'balance' => $wallet->getBalance(),
                'total' => $total,
            ], Response::HTTP_BAD_REQUEST);
        }

        // Deduct wallet + mark order paid
        $wallet->setBalance(round($wallet->getBalance() - $total, 2));

        $order->setTotalAmount($total);
        $order->setStatus('paid');
        $order->setPaidAt(new \DateTimeImmutable());

        // Handle exclusive licenses - check the SELECTED license from checkout
        foreach ($itemsPayload as $index => $itemPayload) {
            $beatId = $itemPayload['beatId'] ?? null;
            $licenseId = $itemPayload['licenseId'] ?? null;

            $beat = $beatRepo->find($beatId);
            if ($beat && $licenseId) {
                $selectedLicense = $licenseRepo->find($licenseId);
                // If the selected license is exclusive, mark beat as exclusive
                if ($selectedLicense && $selectedLicense->isExclusive()) {
                    $beat->setIsExclusive(true);
                    $beat->setExclusiveOwner($user);
                    $this->em->persist($beat);
                }
            }
        }

        $this->em->persist($order);

        $walletTx = new WalletTransaction();
        $walletTx->setWallet($wallet);
        $walletTx->setType('debit');
        $walletTx->setAmount($total);
        $walletTx->setDescription('Beat purchase');
        $walletTx->setReference('order_checkout');

        $this->em->persist($walletTx);

        $this->em->flush();

        // Send confirmation email
        try {
            $html = $this->twig->render('emails/order_confirmation.html.twig', [
                'user' => $user,
                'order' => $order,
                'items' => $order->getItems()->toArray(),
            ]);

            $email = (new Email())
                ->from('noreply@beatmarket.com')
                ->to($user->getEmail())
                ->subject('Order Confirmation - BeatMarket #' . $order->getId())
                ->html($html);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            error_log("Failed to send order confirmation email: " . $e->getMessage());
            // Don't fail the checkout if email fails
        }

        return $this->json([
            'message' => 'Checkout successful',
            'order' => [
                'id' => $order->getId(),
                'status' => $order->getStatus(),
                'totalAmount' => $order->getTotalAmount(),
                'createdAt' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
                'paidAt' => $order->getPaidAt()?->format('Y-m-d H:i:s'),
                'items' => array_map(static fn(OrderItem $it) => [
                    'id' => $it->getId(),
                    'beatId' => $it->getBeat()?->getId(),
                    'beatTitle' => $it->getBeat()?->getTitle(),
                    'quantity' => $it->getQuantity(),
                    'unitPrice' => $it->getPrice(),
                ], $order->getItems()->toArray()),
            ],
            'wallet' => [
                'balance' => $wallet->getBalance(),
            ],
        ]);
    }

    /**
     * GET /api/orders - List authenticated user's orders
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $orders = $this->em->getRepository(Order::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        $data = [];
        foreach ($orders as $order) {
            $data[] = [
                'id' => $order->getId(),
                'status' => $order->getStatus(),
                'totalAmount' => $order->getTotalAmount(),
                'createdAt' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
                'paidAt' => $order->getPaidAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json($data);
    }

    /**
     * GET /api/orders/purchased-beats - Get all beats the user has purchased
     */
    #[Route('/purchased-beats', name: 'all_purchased_beats', methods: ['GET'])]
    public function getPurchasedBeats(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $orders = $this->em->getRepository(Order::class)->findBy(
            ['user' => $user, 'status' => 'paid'],
            ['createdAt' => 'DESC']
        );

        $beats = [];
        foreach ($orders as $order) {
            foreach ($order->getItems() as $item) {
                $beat = $item->getBeat();
                if ($beat) {
                    $beats[] = [
                        'id' => $beat->getId(),
                        'title' => $beat->getTitle(),
                        'description' => $beat->getDescription(),
                        'fileUrl' => $beat->getFileUrl(),
                        'coverImage' => $beat->getCoverImage(),
                        'price' => $beat->getPrice(),
                        'genre' => $beat->getGenre(),
                        'bpm' => $beat->getBpm(),
                        'key' => $beat->getKey(),
                        'producer' => $beat->getUser()?->getUsername(),
                        'producerId' => $beat->getUser()?->getId(),
                        'purchasedAt' => $order->getPaidAt()?->format('Y-m-d H:i:s'),
                        'orderId' => $order->getId(),
                    ];
                }
            }
        }

        return $this->json([
            'total' => count($beats),
            'beats' => $beats,
        ]);
    }

    /**
     * GET /api/orders/beats/{beatId}/download - Download a purchased beat
     * 
     * Verifies the user has purchased this beat, then streams the file
     */
    #[Route('/beats/{beatId}/download', name: 'download_beat', methods: ['GET'])]
    public function downloadBeat(int $beatId)
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var Beat|null $beat */
        $beat = $this->em->getRepository(Beat::class)->find($beatId);
        if (!$beat) {
            return $this->json(['error' => 'Beat not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if user purchased this beat
        $orders = $this->em->getRepository(Order::class)->findBy(
            ['user' => $user, 'status' => 'paid']
        );

        $purchased = false;
        foreach ($orders as $order) {
            foreach ($order->getItems() as $item) {
                if ($item->getBeat()?->getId() === $beatId) {
                    $purchased = true;
                    break 2;
                }
            }
        }

        if (!$purchased) {
            return $this->json(['error' => 'Beat not purchased or access denied'], Response::HTTP_FORBIDDEN);
        }

        $fileUrl = $beat->getFileUrl();
        if (!$fileUrl) {
            return $this->json(['error' => 'Beat file not available'], Response::HTTP_NOT_FOUND);
        }

        // Check if it's a local file path or a remote URL
        if (filter_var($fileUrl, FILTER_VALIDATE_URL)) {
            // Remote URL - redirect or proxy
            // For now, return a redirect to the file
            return $this->redirect($fileUrl);
        } else {
            // Local file path
            $filePath = $this->getParameter('kernel.project_dir') . '/public' . $fileUrl;

            if (!file_exists($filePath)) {
                error_log("Beat file not found at: " . $filePath);
                return $this->json(['error' => 'File not found on server'], Response::HTTP_NOT_FOUND);
            }

            $response = new BinaryFileResponse($filePath);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $beat->getTitle() . '.mp3'
            );

            return $response;
        }
    }

    /**
     * GET /api/orders/{id} - Get one order (owner only)
     */
    #[Route('/{id}', name: 'get_one', methods: ['GET'])]
    public function getOne(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var Order|null $order */
        $order = $this->em->getRepository(Order::class)->find($id);
        if (!$order) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        if ($order->getUser()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'id' => $order->getId(),
            'status' => $order->getStatus(),
            'totalAmount' => $order->getTotalAmount(),
            'createdAt' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
            'paidAt' => $order->getPaidAt()?->format('Y-m-d H:i:s'),
            'items' => array_map(static fn(OrderItem $it) => [
                'id' => $it->getId(),
                'beatId' => $it->getBeat()?->getId(),
                'beatTitle' => $it->getBeat()?->getTitle(),
                'quantity' => $it->getQuantity(),
                'unitPrice' => $it->getPrice(),
            ], $order->getItems()->toArray()),
        ]);
    }

    /**
     * GET /api/orders/{id}/beats - Get all beats in an order with full details
     */
    #[Route('/{id}/beats', name: 'get_order_beats', methods: ['GET'])]
    public function getOrderBeats(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var Order|null $order */
        $order = $this->em->getRepository(Order::class)->find($id);
        if (!$order) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        if ($order->getUser()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $beats = [];
        foreach ($order->getItems() as $item) {
            $beat = $item->getBeat();
            if ($beat) {
                $beats[] = [
                    'id' => $beat->getId(),
                    'title' => $beat->getTitle(),
                    'description' => $beat->getDescription(),
                    'fileUrl' => $beat->getFileUrl(),
                    'coverImage' => $beat->getCoverImage(),
                    'price' => $beat->getPrice(),
                    'genre' => $beat->getGenre(),
                    'bpm' => $beat->getBpm(),
                    'key' => $beat->getKey(),
                    'producer' => $beat->getUser()?->getUsername(),
                    'producerId' => $beat->getUser()?->getId(),
                    'purchasedAt' => $order->getPaidAt()?->format('Y-m-d H:i:s'),
                    'quantity' => $item->getQuantity(),
                    'unitPrice' => $item->getPrice(),
                ];
            }
        }

        return $this->json([
            'orderId' => $order->getId(),
            'status' => $order->getStatus(),
            'totalAmount' => $order->getTotalAmount(),
            'beats' => $beats,
        ]);
    }
}
