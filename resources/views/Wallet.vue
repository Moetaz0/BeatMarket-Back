<template>
    <div class="min-h-screen bg-black text-white">
        <Navbar />

        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold mb-2">Wallet</h1>
                <p class="text-gray-400">
                    Manage your balance and payment methods
                </p>
            </div>

            <!-- Balance Card -->
            <div
                class="bg-gradient-to-r from-red-600 to-red-700 rounded-lg p-8 mb-8"
            >
                <p class="text-red-100 text-sm mb-2">Available Balance</p>
                <h2 class="text-4xl font-bold mb-4">
                    ${{ balance.toFixed(2) }}
                </h2>
                <div class="flex gap-4">
                    <button
                        @click="showWithdrawModal = true"
                        class="px-6 py-2 bg-white text-red-600 font-semibold rounded-lg hover:bg-gray-100 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="balance <= 0 || loading"
                    >
                        Withdraw
                    </button>
                    <button
                        @click="showDepositModal = true"
                        class="px-6 py-2 border border-white text-white font-semibold rounded-lg hover:bg-white/10 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="loading"
                    >
                        Add Funds
                    </button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
                    <p class="text-gray-400 text-sm mb-2">
                        Earnings (This Month)
                    </p>
                    <h3 class="text-2xl font-bold">
                        ${{ stats.earnings?.toFixed(2) || "0.00" }}
                    </h3>
                    <p class="text-gray-500 text-xs mt-2">
                        Earnings from sales
                    </p>
                </div>
                <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
                    <p class="text-gray-400 text-sm mb-2">Spent (This Month)</p>
                    <h3 class="text-2xl font-bold">
                        ${{ stats.spent?.toFixed(2) || "0.00" }}
                    </h3>
                    <p class="text-gray-500 text-xs mt-2">Total purchases</p>
                </div>
                <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
                    <p class="text-gray-400 text-sm mb-2">Total Transactions</p>
                    <h3 class="text-2xl font-bold">
                        {{ stats.totalTransactions || 0 }}
                    </h3>
                    <p class="text-gray-500 text-xs mt-2">All time</p>
                </div>
            </div>

            <!-- Transactions History -->
            <div class="bg-gray-900 rounded-lg p-6 border border-gray-800 mb-8">
                <h3 class="text-xl font-semibold mb-6">Recent Transactions</h3>
                <div v-if="transactions.length === 0" class="text-center py-8">
                    <p class="text-gray-400">No transactions yet</p>
                </div>
                <div v-else class="space-y-2 max-h-96 overflow-y-auto">
                    <div
                        v-for="transaction in transactions"
                        :key="transaction.id"
                        class="flex items-center justify-between p-4 bg-gray-800 rounded-lg border border-gray-700 hover:border-gray-600 transition"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                :class="[
                                    'w-10 h-10 rounded-full flex items-center justify-center',
                                    transaction.type === 'credit'
                                        ? 'bg-green-600/30'
                                        : 'bg-red-600/30',
                                ]"
                            >
                                <svg
                                    class="w-5 h-5"
                                    :class="
                                        transaction.type === 'credit'
                                            ? 'text-green-400'
                                            : 'text-red-400'
                                    "
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        v-if="transaction.type === 'credit'"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 4v16m8-8H4"
                                    />
                                    <path
                                        v-else
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M20 12H4"
                                    />
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold">
                                    {{ transaction.description }}
                                </p>
                                <p class="text-gray-400 text-sm">
                                    {{
                                        new Date(
                                            transaction.createdAt
                                        ).toLocaleDateString()
                                    }}
                                </p>
                            </div>
                        </div>
                        <p
                            :class="[
                                'font-semibold',
                                transaction.type === 'credit'
                                    ? 'text-green-400'
                                    : 'text-red-400',
                            ]"
                        >
                            {{ transaction.type === "credit" ? "+" : "-" }}${{
                                transaction.amount.toFixed(2)
                            }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="bg-gray-900 rounded-lg p-6 border border-gray-800 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold">Payment Methods</h3>
                    <button
                        @click="showAddPaymentModal = true"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="loading"
                    >
                        + Add Payment Method
                    </button>
                </div>

                <div
                    v-if="paymentMethods.length === 0"
                    class="text-center py-8"
                >
                    <p class="text-gray-400">No payment methods added yet</p>
                </div>
                <div v-else class="space-y-4">
                    <div
                        v-for="method in paymentMethods"
                        :key="method.id"
                        class="flex items-center justify-between p-4 bg-gray-800 rounded-lg border border-gray-700"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="w-12 h-8 bg-gradient-to-r from-blue-600 to-blue-700 rounded flex items-center justify-center"
                            >
                                <svg
                                    class="w-6 h-4 text-white"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        d="M3 4a1 1 0 011-1h12a1 1 0 011 1H3zm0 2h14v10a2 2 0 01-2 2H5a2 2 0 01-2-2V6z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold capitalize">
                                    {{ method.card?.brand || method.type }}
                                </p>
                                <p class="text-gray-400 text-sm">
                                    ••••
                                    {{ method.card?.last4 || method.last4 }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="method.default"
                                class="px-2 py-1 bg-green-600/30 text-green-400 text-xs rounded"
                            >
                                Default
                            </span>
                            <button
                                @click="removePaymentMethod(method.id)"
                                class="text-gray-400 hover:text-red-400 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="loading"
                            >
                                <svg
                                    class="w-5 h-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Withdraw Modal -->
        <div
            v-if="showWithdrawModal"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        >
            <div
                class="bg-gray-900 rounded-lg p-6 max-w-md w-full border border-gray-800"
            >
                <h3 class="text-xl font-semibold mb-4">Withdraw Funds</h3>
                <div class="space-y-4 mb-6">
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-300 mb-2"
                        >
                            Amount
                        </label>
                        <input
                            v-model="withdrawAmount"
                            type="number"
                            placeholder="0.00"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-red-600"
                        />
                        <p class="text-gray-400 text-xs mt-2">
                            Available: ${{ balance.toFixed(2) }}
                        </p>
                    </div>
                </div>
                <div
                    v-if="error"
                    class="mb-4 p-3 bg-red-600/20 border border-red-600 rounded text-red-400 text-sm"
                >
                    {{ error }}
                </div>
                <div class="flex gap-3">
                    <button
                        @click="
                            showWithdrawModal = false;
                            error = null;
                        "
                        class="flex-1 px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg font-medium transition disabled:opacity-50"
                        :disabled="loading"
                    >
                        Cancel
                    </button>
                    <button
                        @click="processWithdraw"
                        class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="loading || !withdrawAmount"
                    >
                        {{ loading ? "Processing..." : "Withdraw" }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Deposit Modal -->
        <div
            v-if="showDepositModal"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        >
            <div
                class="bg-gray-900 rounded-lg p-6 max-w-md w-full border border-gray-800"
            >
                <h3 class="text-xl font-semibold mb-4">Add Funds</h3>
                <div class="space-y-4 mb-6">
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-300 mb-2"
                        >
                            Amount
                        </label>
                        <input
                            v-model="depositAmount"
                            type="number"
                            placeholder="0.00"
                            min="0.50"
                            step="0.01"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-red-600"
                        />
                        <p class="text-gray-400 text-xs mt-2">Minimum: $0.50</p>
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-300 mb-2"
                        >
                            Card Number
                        </label>
                        <input
                            v-model="cardNumber"
                            type="text"
                            placeholder="4242 4242 4242 4242"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-red-600"
                        />
                        <p class="text-gray-400 text-xs mt-2">
                            Use 4242 4242 4242 4242 for test
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-300 mb-2"
                            >
                                Expiry (MM/YY)
                            </label>
                            <input
                                v-model="cardExpiry"
                                type="text"
                                placeholder="12/25"
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-red-600"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-300 mb-2"
                            >
                                CVC
                            </label>
                            <input
                                v-model="cardCvc"
                                type="text"
                                placeholder="123"
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-red-600"
                            />
                        </div>
                    </div>
                </div>
                <div
                    v-if="error"
                    class="mb-4 p-3 bg-red-600/20 border border-red-600 rounded text-red-400 text-sm"
                >
                    {{ error }}
                </div>
                <div class="flex gap-3">
                    <button
                        @click="
                            showDepositModal = false;
                            error = null;
                        "
                        class="flex-1 px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg font-medium transition disabled:opacity-50"
                        :disabled="loading"
                    >
                        Cancel
                    </button>
                    <button
                        @click="processDeposit"
                        class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="loading || !depositAmount || !cardNumber"
                    >
                        {{ loading ? "Processing..." : "Add Funds" }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Payment Method Modal -->
        <div
            v-if="showAddPaymentModal"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        >
            <div
                class="bg-gray-900 rounded-lg p-6 max-w-md w-full border border-gray-800"
            >
                <h3 class="text-xl font-semibold mb-4">Add Payment Method</h3>
                <div class="space-y-4 mb-6">
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-300 mb-2"
                        >
                            Card Number
                        </label>
                        <input
                            v-model="newCardNumber"
                            type="text"
                            placeholder="4111 1111 1111 1111"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-red-600"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-300 mb-2"
                            >
                                Expiry (MM/YY)
                            </label>
                            <input
                                v-model="newCardExpiry"
                                type="text"
                                placeholder="12/25"
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-red-600"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-300 mb-2"
                            >
                                CVC
                            </label>
                            <input
                                v-model="newCardCvc"
                                type="text"
                                placeholder="123"
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-red-600"
                            />
                        </div>
                    </div>
                </div>
                <div
                    v-if="error"
                    class="mb-4 p-3 bg-red-600/20 border border-red-600 rounded text-red-400 text-sm"
                >
                    {{ error }}
                </div>
                <div class="flex gap-3">
                    <button
                        @click="
                            showAddPaymentModal = false;
                            error = null;
                        "
                        class="flex-1 px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg font-medium transition disabled:opacity-50"
                        :disabled="loading"
                    >
                        Cancel
                    </button>
                    <button
                        @click="addPaymentMethod"
                        class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="loading || !newCardNumber"
                    >
                        {{ loading ? "Adding..." : "Add" }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import Navbar from "@/components/Navbar.vue";

const balance = ref(0);
const stats = ref({
    earnings: 0,
    spent: 0,
    totalTransactions: 0,
});
const transactions = ref([]);
const paymentMethods = ref([]);

const showWithdrawModal = ref(false);
const showDepositModal = ref(false);
const showAddPaymentModal = ref(false);

const withdrawAmount = ref("");
const depositAmount = ref("");
const selectedPaymentMethod = ref("");

const cardNumber = ref("");
const cardExpiry = ref("");
const cardCvc = ref("");

const newCardNumber = ref("");
const newCardExpiry = ref("");
const newCardCvc = ref("");

const loading = ref(false);
const error = ref("");

const API_BASE = "http://localhost:8000/api";

// Get token from localStorage
const getToken = () => {
    return localStorage.getItem("jwt_token");
};

// Fetch wallet balance
const fetchWallet = async () => {
    try {
        const response = await fetch(`${API_BASE}/wallet`, {
            headers: {
                Authorization: `Bearer ${getToken()}`,
                "Content-Type": "application/json",
            },
        });

        if (!response.ok) throw new Error("Failed to fetch wallet");
        const data = await response.json();
        balance.value = data.balance || 0;
    } catch (err) {
        console.error("Error fetching wallet:", err);
        error.value = "Failed to load wallet";
    }
};

// Fetch wallet stats
const fetchStats = async () => {
    try {
        const response = await fetch(`${API_BASE}/wallet/stats`, {
            headers: {
                Authorization: `Bearer ${getToken()}`,
                "Content-Type": "application/json",
            },
        });

        if (!response.ok) throw new Error("Failed to fetch stats");
        stats.value = await response.json();
    } catch (err) {
        console.error("Error fetching stats:", err);
    }
};

// Fetch transactions
const fetchTransactions = async () => {
    try {
        const response = await fetch(
            `${API_BASE}/wallet/transactions?limit=10`,
            {
                headers: {
                    Authorization: `Bearer ${getToken()}`,
                    "Content-Type": "application/json",
                },
            }
        );

        if (!response.ok) throw new Error("Failed to fetch transactions");
        transactions.value = await response.json();
    } catch (err) {
        console.error("Error fetching transactions:", err);
    }
};

// Fetch payment methods
const fetchPaymentMethods = async () => {
    try {
        const response = await fetch(`${API_BASE}/wallet/payment-methods`, {
            headers: {
                Authorization: `Bearer ${getToken()}`,
                "Content-Type": "application/json",
            },
        });

        if (!response.ok) throw new Error("Failed to fetch payment methods");
        const data = await response.json();
        paymentMethods.value = data.paymentMethods || [];
    } catch (err) {
        console.error("Error fetching payment methods:", err);
    }
};

// Process deposit
const processDeposit = async () => {
    if (!depositAmount.value) {
        error.value = "Please enter an amount";
        return;
    }

    loading.value = true;
    error.value = "";

    try {
        // In production, create Stripe PaymentMethod here
        // For now, send request with test payment method
        const response = await fetch(`${API_BASE}/wallet/deposit`, {
            method: "POST",
            headers: {
                Authorization: `Bearer ${getToken()}`,
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                amount: parseFloat(depositAmount.value),
                paymentMethodId: "pm_card_visa", // Test payment method
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || "Deposit failed");
        }

        balance.value = data.balance;
        showDepositModal.value = false;
        depositAmount.value = "";
        cardNumber.value = "";
        cardExpiry.value = "";
        cardCvc.value = "";

        await fetchTransactions();
        await fetchStats();
    } catch (err) {
        error.value = err.message || "Failed to process deposit";
    } finally {
        loading.value = false;
    }
};

// Process withdrawal
const processWithdraw = async () => {
    if (!withdrawAmount.value) {
        error.value = "Please enter an amount";
        return;
    }

    if (parseFloat(withdrawAmount.value) > balance.value) {
        error.value = "Insufficient funds";
        return;
    }

    loading.value = true;
    error.value = "";

    try {
        const response = await fetch(`${API_BASE}/wallet/withdraw`, {
            method: "POST",
            headers: {
                Authorization: `Bearer ${getToken()}`,
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                amount: parseFloat(withdrawAmount.value),
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || "Withdrawal failed");
        }

        balance.value = data.balance;
        showWithdrawModal.value = false;
        withdrawAmount.value = "";

        await fetchTransactions();
        await fetchStats();
    } catch (err) {
        error.value = err.message || "Failed to process withdrawal";
    } finally {
        loading.value = false;
    }
};

// Add payment method
const addPaymentMethod = async () => {
    if (!newCardNumber.value) {
        error.value = "Please enter card details";
        return;
    }

    loading.value = true;
    error.value = "";

    try {
        // In production, create Stripe PaymentMethod from card details
        const response = await fetch(`${API_BASE}/wallet/payment-methods`, {
            method: "POST",
            headers: {
                Authorization: `Bearer ${getToken()}`,
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                paymentMethodId: "pm_card_visa_test", // Test payment method
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || "Failed to add payment method");
        }

        showAddPaymentModal.value = false;
        newCardNumber.value = "";
        newCardExpiry.value = "";
        newCardCvc.value = "";

        await fetchPaymentMethods();
    } catch (err) {
        error.value = err.message || "Failed to add payment method";
    } finally {
        loading.value = false;
    }
};

// Remove payment method
const removePaymentMethod = async (id) => {
    if (!confirm("Are you sure you want to remove this payment method?")) {
        return;
    }

    loading.value = true;
    error.value = "";

    try {
        const response = await fetch(
            `${API_BASE}/wallet/payment-methods/${id}`,
            {
                method: "DELETE",
                headers: {
                    Authorization: `Bearer ${getToken()}`,
                    "Content-Type": "application/json",
                },
            }
        );

        if (!response.ok) {
            throw new Error("Failed to remove payment method");
        }

        await fetchPaymentMethods();
    } catch (err) {
        error.value = err.message || "Failed to remove payment method";
    } finally {
        loading.value = false;
    }
};

// Load all data on mount
onMounted(() => {
    fetchWallet();
    fetchStats();
    fetchTransactions();
    fetchPaymentMethods();
});
</script>
