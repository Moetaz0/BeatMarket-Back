<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\Wallet;
use App\Entity\WalletTransaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/wallet', name: 'api_wallet_')]
class WalletController extends AbstractController
{
    private EntityManagerInterface $em;
    private string $stripeKey;

    public function __construct(EntityManagerInterface $em, string $stripeSecretKey)
    {
        $this->em = $em;
        $this->stripeKey = $stripeSecretKey;
        \Stripe\Stripe::setApiKey($stripeSecretKey);
    }

    private function getOrCreateStripeCustomerId(User $user): string
    {
        $existingCustomerId = $user->getStripeCustomerId();
        if ($existingCustomerId) {
            return $existingCustomerId;
        }

        $customer = \Stripe\Customer::create([
            'email' => $user->getEmail(),
            'name' => $user->getUsername(),
            'metadata' => [
                'user_id' => (string) $user->getId(),
                'user_email' => $user->getEmail(),
            ],
        ]);

        $user->setStripeCustomerId($customer->id);
        $this->em->persist($user);
        $this->em->flush();

        return $customer->id;
    }

    /**
     * GET /api/wallet - Get wallet information
     */
    #[Route('', name: 'get_wallet', methods: ['GET'])]
    public function getWallet(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $wallet = $user->getWallet();
        if (!$wallet) {
            // Create wallet if doesn't exist
            $wallet = new Wallet();
            $wallet->setUser($user);
            $wallet->setBalance(0);
            $this->em->persist($wallet);
            $this->em->flush();
        }

        return $this->json([
            'id' => $wallet->getId(),
            'balance' => $wallet->getBalance(),
            'createdAt' => $wallet->getCreatedAt()->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * GET /api/wallet/stats - Get wallet statistics
     */
    #[Route('/stats', name: 'wallet_stats', methods: ['GET'])]
    public function getStats(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $wallet = $user->getWallet();
        if (!$wallet) {
            return $this->json([
                'earnings' => 0,
                'spent' => 0,
                'totalTransactions' => 0
            ]);
        }

        // Get current month earnings and spent
        $currentMonth = new \DateTime('first day of this month');
        $nextMonth = new \DateTime('first day of next month');

        $transactionRepo = $this->em->getRepository(WalletTransaction::class);

        // Get all transactions for this wallet
        $allTransactions = $transactionRepo->findBy(['wallet' => $wallet]);

        $earnings = 0;
        $spent = 0;
        $thisMonthEarnings = 0;
        $thisMonthSpent = 0;

        foreach ($allTransactions as $transaction) {
            if ($transaction->getType() === 'credit') {
                $earnings += $transaction->getAmount();
                if ($transaction->getCreatedAt() >= $currentMonth && $transaction->getCreatedAt() < $nextMonth) {
                    $thisMonthEarnings += $transaction->getAmount();
                }
            } else {
                $spent += $transaction->getAmount();
                if ($transaction->getCreatedAt() >= $currentMonth && $transaction->getCreatedAt() < $nextMonth) {
                    $thisMonthSpent += $transaction->getAmount();
                }
            }
        }

        return $this->json([
            'earnings' => round($thisMonthEarnings, 2),
            'spent' => round($thisMonthSpent, 2),
            'totalTransactions' => count($allTransactions),
            'totalEarnings' => round($earnings, 2),
            'totalSpent' => round($spent, 2)
        ]);
    }

    /**
     * GET /api/wallet/transactions - Get wallet transactions
     */
    #[Route('/transactions', name: 'wallet_transactions', methods: ['GET'])]
    public function getTransactions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $wallet = $user->getWallet();
        if (!$wallet) {
            return $this->json([]);
        }

        $limit = $request->query->getInt('limit', 50);
        $offset = $request->query->getInt('offset', 0);

        $transactionRepo = $this->em->getRepository(WalletTransaction::class);
        $transactions = $transactionRepo->findBy(
            ['wallet' => $wallet],
            ['createdAt' => 'DESC'],
            $limit,
            $offset
        );

        $data = [];
        foreach ($transactions as $transaction) {
            $data[] = [
                'id' => $transaction->getId(),
                'amount' => $transaction->getAmount(),
                'type' => $transaction->getType(),
                'description' => $transaction->getDescription(),
                'reference' => $transaction->getReference(),
                'createdAt' => $transaction->getCreatedAt()->format('Y-m-d H:i:s')
            ];
        }

        return $this->json($data);
    }

    /**
     * POST /api/wallet/deposit - Deposit funds via Stripe
     * 
     * Request body:
     * {
     *   "amount": 50.00,
     *   "paymentMethodId": "pm_..."
     * }
     */
    #[Route('/deposit', name: 'deposit_funds', methods: ['POST'])]
    public function depositFunds(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $amount = $data['amount'] ?? null;
        $paymentMethodId = $data['paymentMethodId'] ?? null;

        if (!$amount || $amount <= 0) {
            return $this->json(['error' => 'Invalid amount'], Response::HTTP_BAD_REQUEST);
        }

        if (!$paymentMethodId) {
            return $this->json(['error' => 'Payment method required'], Response::HTTP_BAD_REQUEST);
        }

        $wallet = $user->getWallet();
        if (!$wallet) {
            $wallet = new Wallet();
            $wallet->setUser($user);
            $wallet->setBalance(0);
            $this->em->persist($wallet);
            $this->em->flush();
        }

        try {
            $stripeCustomerId = $user->getStripeCustomerId();

            // Create Stripe PaymentIntent for test purposes
            $paymentIntentPayload = [
                'amount' => (int) ($amount * 100), // Convert to cents
                'currency' => 'usd',
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'return_url' => 'http://localhost:5173/wallet', // Frontend return URL
                'description' => "Deposit to BeatMarket wallet for user: " . $user->getEmail(),
                'metadata' => [
                    'user_id' => $user->getId(),
                    'user_email' => $user->getEmail()
                ]
            ];

            if ($stripeCustomerId) {
                $paymentIntentPayload['customer'] = $stripeCustomerId;
            }

            $paymentIntent = \Stripe\PaymentIntent::create($paymentIntentPayload);

            // If payment succeeded (for test card 4242 4242 4242 4242)
            if ($paymentIntent->status === 'succeeded') {
                // Update wallet balance
                $wallet->setBalance($wallet->getBalance() + $amount);

                // Create transaction record
                $transaction = new WalletTransaction();
                $transaction->setWallet($wallet);
                $transaction->setAmount($amount);
                $transaction->setType('credit');
                $transaction->setDescription('Deposit via Stripe');
                $transaction->setReference($paymentIntent->id);

                $this->em->persist($transaction);
                $this->em->flush();

                return $this->json([
                    'message' => 'Deposit successful',
                    'balance' => $wallet->getBalance(),
                    'transactionId' => $transaction->getId(),
                    'stripeId' => $paymentIntent->id
                ]);
            } elseif ($paymentIntent->status === 'requires_action') {
                return $this->json([
                    'message' => 'Payment requires additional action',
                    'clientSecret' => $paymentIntent->client_secret,
                    'status' => 'requires_action'
                ], Response::HTTP_ACCEPTED);
            } else {
                return $this->json(
                    ['error' => 'Payment failed: ' . $paymentIntent->status],
                    Response::HTTP_BAD_REQUEST
                );
            }
        } catch (\Stripe\Exception\CardException $e) {
            error_log("Stripe Card Error: " . $e->getMessage());
            return $this->json(
                ['error' => 'Card declined: ' . $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Stripe\Exception\RateLimitException $e) {
            error_log("Stripe Rate Limit Error: " . $e->getMessage());
            return $this->json(
                ['error' => 'Too many requests'],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            error_log("Stripe Invalid Request Error: " . $e->getMessage());
            return $this->json(
                ['error' => 'Invalid request: ' . $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Stripe\Exception\AuthenticationException $e) {
            error_log("Stripe Authentication Error: " . $e->getMessage());
            return $this->json(
                ['error' => 'Authentication error'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            error_log("Stripe API Connection Error: " . $e->getMessage());
            return $this->json(
                ['error' => 'Connection error'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Stripe API Error: " . $e->getMessage());
            return $this->json(
                ['error' => 'Payment processing failed'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * POST /api/wallet/withdraw - Withdraw funds to payment method
     * 
     * Request body:
     * {
     *   "amount": 50.00,
     *   "bankAccountId": "ba_..."
     * }
     */
    #[Route('/withdraw', name: 'withdraw_funds', methods: ['POST'])]
    public function withdrawFunds(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $amount = $data['amount'] ?? null;

        if (!$amount || $amount <= 0) {
            return $this->json(['error' => 'Invalid amount'], Response::HTTP_BAD_REQUEST);
        }

        $wallet = $user->getWallet();
        if (!$wallet || $wallet->getBalance() < $amount) {
            return $this->json(['error' => 'Insufficient funds'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // For test purposes, we'll create a payout to a test bank account
            $payout = \Stripe\Payout::create([
                'amount' => (int) ($amount * 100), // Convert to cents
                'currency' => 'usd',
                'destination' => 'default', // Uses default bank account
                'description' => "Withdrawal from BeatMarket wallet for user: " . $user->getEmail(),
                'metadata' => [
                    'user_id' => $user->getId(),
                    'user_email' => $user->getEmail()
                ]
            ]);

            // Deduct from wallet
            $wallet->setBalance($wallet->getBalance() - $amount);

            // Create transaction record
            $transaction = new WalletTransaction();
            $transaction->setWallet($wallet);
            $transaction->setAmount($amount);
            $transaction->setType('debit');
            $transaction->setDescription('Withdrawal via Stripe');
            $transaction->setReference($payout->id);

            $this->em->persist($transaction);
            $this->em->flush();

            return $this->json([
                'message' => 'Withdrawal processed successfully',
                'balance' => $wallet->getBalance(),
                'transactionId' => $transaction->getId(),
                'stripeId' => $payout->id,
                'status' => $payout->status
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Stripe Payout Error: " . $e->getMessage());
            return $this->json(
                ['error' => 'Withdrawal failed: ' . $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * POST /api/wallet/payment-methods - Add a payment method
     * 
     * Request body (one of two options):
     * Option 1 - With card details:
     * {
     *   "cardNumber": "4242424242424242",
     *   "cardExpiry": "12/25",
     *   "cardCvc": "123"
     * }
     * 
     * Option 2 - With existing payment method ID:
     * {
     *   "paymentMethodId": "pm_..."
     * }
     */
    #[Route('/payment-methods', name: 'add_payment_method', methods: ['POST'])]
    public function addPaymentMethod(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $paymentMethodId = $data['paymentMethodId'] ?? null;
        $cardNumber = $data['cardNumber'] ?? null;
        $cardExpiry = $data['cardExpiry'] ?? null;
        $cardCvc = $data['cardCvc'] ?? null;

        // Either paymentMethodId or card details must be provided
        if (!$paymentMethodId && (!$cardNumber || !$cardExpiry || !$cardCvc)) {
            return $this->json(
                ['error' => 'Either paymentMethodId or card details (cardNumber, cardExpiry, cardCvc) are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            // If card details provided, create a payment method from them
            if ($cardNumber && $cardExpiry && $cardCvc) {
                // Parse expiry date
                $expiryParts = explode('/', trim($cardExpiry));
                if (count($expiryParts) !== 2) {
                    return $this->json(
                        ['error' => 'Invalid expiry format. Use MM/YY'],
                        Response::HTTP_BAD_REQUEST
                    );
                }

                $expMonth = (int) trim($expiryParts[0]);
                $expYear = (int) ('20' . trim($expiryParts[1])); // Convert YY to 20YY

                // Create payment method from card details
                $paymentMethod = \Stripe\PaymentMethod::create([
                    'type' => 'card',
                    'card' => [
                        'number' => str_replace(' ', '', $cardNumber),
                        'exp_month' => $expMonth,
                        'exp_year' => $expYear,
                        'cvc' => $cardCvc,
                    ],
                ]);
            } else {
                // Retrieve existing payment method
                $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);
            }

            $stripeCustomerId = $this->getOrCreateStripeCustomerId($user);

            // Attach payment method to customer so it shows under Stripe Dashboard > Customers
            $attached = $paymentMethod->attach([
                'customer' => $stripeCustomerId,
            ]);

            // Optionally set as default
            $customer = \Stripe\Customer::update($stripeCustomerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethod->id,
                ],
            ]);

            // Return the payment method details
            return $this->json([
                'message' => 'Payment method added successfully',
                'customerId' => $stripeCustomerId,
                'id' => $attached->id,
                'type' => $attached->type,
                'card' => [
                    'brand' => $attached->card->brand ?? null,
                    'last4' => $attached->card->last4 ?? null,
                    'expMonth' => $attached->card->exp_month ?? null,
                    'expYear' => $attached->card->exp_year ?? null
                ],
                'default' => ($customer->invoice_settings->default_payment_method ?? null) === $attached->id,
            ]);
        } catch (\Stripe\Exception\CardException $e) {
            error_log("Stripe Card Error: " . $e->getMessage());
            return $this->json(
                ['error' => 'Card error: ' . $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            error_log("Stripe Invalid Request Error: " . $e->getMessage());
            return $this->json(
                ['error' => 'Invalid card details: ' . $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Stripe Payment Method Error: " . $e->getMessage());
            return $this->json(
                ['error' => 'Failed to add payment method: ' . $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * DELETE /api/wallet/payment-methods/{id} - Remove a payment method
     * 
     * For Stripe, this detaches the payment method
     */
    #[Route('/payment-methods/{id}', name: 'remove_payment_method', methods: ['DELETE'])]
    public function removePaymentMethod(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $stripeCustomerId = $user->getStripeCustomerId();
            if (!$stripeCustomerId) {
                return $this->json(['error' => 'No Stripe customer found for user'], Response::HTTP_BAD_REQUEST);
            }

            $paymentMethod = \Stripe\PaymentMethod::retrieve($id);

            // Safety check: only allow detaching PMs belonging to this customer
            if (($paymentMethod->customer ?? null) !== $stripeCustomerId) {
                return $this->json(['error' => 'Payment method does not belong to this user'], Response::HTTP_FORBIDDEN);
            }

            $customer = \Stripe\Customer::retrieve($stripeCustomerId);
            $defaultPaymentMethodId = $customer->invoice_settings->default_payment_method ?? null;
            if ($defaultPaymentMethodId === $paymentMethod->id) {
                \Stripe\Customer::update($stripeCustomerId, [
                    'invoice_settings' => [
                        'default_payment_method' => null,
                    ],
                ]);
            }

            $paymentMethod->detach();

            return $this->json([
                'message' => 'Payment method removed successfully'
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Stripe Payment Method Removal Error: " . $e->getMessage());
            return $this->json(
                ['error' => 'Failed to remove payment method'],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * GET /api/wallet/payment-methods - List all payment methods for user
     * 
     * For test purposes, returns sample payment methods
     */
    #[Route('/payment-methods', name: 'list_payment_methods', methods: ['GET'])]
    public function listPaymentMethods(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $stripeCustomerId = $user->getStripeCustomerId();
        if (!$stripeCustomerId) {
            return $this->json([
                'customerId' => null,
                'paymentMethods' => [],
            ]);
        }

        try {
            $customer = \Stripe\Customer::retrieve($stripeCustomerId);
            $defaultPaymentMethodId = $customer->invoice_settings->default_payment_method ?? null;

            $paymentMethods = \Stripe\PaymentMethod::all([
                'customer' => $stripeCustomerId,
                'type' => 'card',
            ]);

            $result = [];
            foreach ($paymentMethods->data as $pm) {
                $result[] = [
                    'id' => $pm->id,
                    'type' => $pm->card->brand ?? null,
                    'last4' => $pm->card->last4 ?? null,
                    'expMonth' => $pm->card->exp_month ?? null,
                    'expYear' => $pm->card->exp_year ?? null,
                    'default' => $pm->id === $defaultPaymentMethodId,
                ];
            }

            return $this->json([
                'customerId' => $stripeCustomerId,
                'paymentMethods' => $result,
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Stripe List Payment Methods Error: " . $e->getMessage());
            return $this->json(
                ['error' => 'Failed to list payment methods'],
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
