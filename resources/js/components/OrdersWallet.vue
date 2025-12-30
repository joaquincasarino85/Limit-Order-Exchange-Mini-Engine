<template>
    <div class="space-y-6">
        <!-- Wallet Overview -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-bold mb-4">Wallet</h2>
            
            <div class="space-y-4">
                <div>
                    <p class="text-sm text-gray-600">USD Balance</p>
                    <p class="text-2xl font-bold">${{ profile?.balance?.toFixed(2) || '0.00' }}</p>
                </div>

                <div>
                    <p class="text-sm text-gray-600 mb-2">Assets</p>
                    <div v-if="profile?.assets?.length" class="space-y-2">
                        <div
                            v-for="asset in profile.assets"
                            :key="asset.symbol"
                            class="flex justify-between items-center p-2 bg-gray-50 rounded"
                        >
                            <span class="font-medium">{{ asset.symbol }}</span>
                            <div class="text-right">
                                <p class="font-semibold">{{ asset.amount.toFixed(8) }}</p>
                                <p v-if="asset.locked_amount > 0" class="text-xs text-gray-500">
                                    Locked: {{ asset.locked_amount.toFixed(8) }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-gray-500 text-sm">No assets</p>
                </div>
            </div>
        </div>

        <!-- Orders List -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Orders</h2>
                <select
                    v-model="selectedSymbol"
                    @change="loadOrders"
                    class="px-3 py-1 border border-gray-300 rounded-md text-sm"
                >
                    <option value="">All Symbols</option>
                    <option value="BTC">BTC</option>
                    <option value="ETH">ETH</option>
                </select>
            </div>

            <div v-if="loading" class="text-center py-4">
                <p class="text-gray-500">Loading orders...</p>
            </div>

            <div v-else-if="orders.length === 0" class="text-center py-4">
                <p class="text-gray-500">No orders found</p>
            </div>

            <div v-else class="space-y-2">
                <div
                    v-for="order in orders"
                    :key="order.id"
                    class="flex justify-between items-center p-3 border border-gray-200 rounded-md hover:bg-gray-50"
                >
                    <div class="flex-1">
                        <div class="flex items-center space-x-2">
                            <span
                                :class="order.side === 'buy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                class="px-2 py-1 rounded text-xs font-medium"
                            >
                                {{ order.side.toUpperCase() }}
                            </span>
                            <span class="font-medium">{{ order.symbol }}</span>
                            <span
                                :class="getStatusClass(order.status)"
                                class="px-2 py-1 rounded text-xs"
                            >
                                {{ getStatusText(order.status) }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">
                            {{ order.amount }} @ ${{ order.price.toFixed(2) }}
                        </p>
                    </div>
                    <button
                        v-if="order.status === 1"
                        @click="cancelOrder(order.id)"
                        class="px-3 py-1 text-sm text-red-600 hover:text-red-800 border border-red-300 rounded hover:bg-red-50"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <!-- Orderbook -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-bold mb-4">Orderbook</h2>
            
            <select
                v-model="orderbookSymbol"
                @change="loadOrderbook"
                class="w-full mb-4 px-3 py-2 border border-gray-300 rounded-md"
            >
                <option value="BTC">BTC</option>
                <option value="ETH">ETH</option>
            </select>

            <div v-if="orderbookLoading" class="text-center py-4">
                <p class="text-gray-500">Loading orderbook...</p>
            </div>

            <div v-else class="space-y-4">
                <!-- Sell Orders -->
                <div>
                    <h3 class="text-sm font-medium text-red-600 mb-2">Sell Orders</h3>
                    <div class="space-y-1">
                        <div
                            v-for="order in sellOrders"
                            :key="order.id"
                            class="flex justify-between text-sm p-2 bg-red-50 rounded"
                        >
                            <span>{{ order.amount.toFixed(8) }}</span>
                            <span class="font-medium">${{ order.price.toFixed(2) }}</span>
                        </div>
                        <p v-if="sellOrders.length === 0" class="text-gray-500 text-sm text-center py-2">
                            No sell orders
                        </p>
                    </div>
                </div>

                <!-- Buy Orders -->
                <div>
                    <h3 class="text-sm font-medium text-green-600 mb-2">Buy Orders</h3>
                    <div class="space-y-1">
                        <div
                            v-for="order in buyOrders"
                            :key="order.id"
                            class="flex justify-between text-sm p-2 bg-green-50 rounded"
                        >
                            <span>{{ order.amount.toFixed(8) }}</span>
                            <span class="font-medium">${{ order.price.toFixed(2) }}</span>
                        </div>
                        <p v-if="buyOrders.length === 0" class="text-gray-500 text-sm text-center py-2">
                            No buy orders
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { on } from '../utils/eventBus.js';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

const profile = ref(null);
const orders = ref([]);
const orderbook = ref([]);
const selectedSymbol = ref('');
const orderbookSymbol = ref('BTC');
const loading = ref(false);
const orderbookLoading = ref(false);
let echo = null;

const buyOrders = computed(() => {
    return orderbook.value
        .filter(order => order.side === 'buy')
        .sort((a, b) => b.price - a.price);
});

const sellOrders = computed(() => {
    return orderbook.value
        .filter(order => order.side === 'sell')
        .sort((a, b) => a.price - b.price);
});

const getStatusText = (status) => {
    const statusMap = {
        1: 'Open',
        2: 'Filled',
        3: 'Cancelled',
    };
    return statusMap[status] || 'Unknown';
};

const getStatusClass = (status) => {
    const classMap = {
        1: 'bg-blue-100 text-blue-800',
        2: 'bg-green-100 text-green-800',
        3: 'bg-gray-100 text-gray-800',
    };
    return classMap[status] || 'bg-gray-100 text-gray-800';
};

const loadProfile = async () => {
    try {
        const response = await axios.get('/api/profile');
        profile.value = response.data;
    } catch (error) {
        console.error('Failed to load profile:', error);
    }
};

const loadOrders = async () => {
    loading.value = true;
    try {
        const params = selectedSymbol.value ? { symbol: selectedSymbol.value } : {};
        const response = await axios.get('/api/orders', { params });
        orders.value = response.data;
    } catch (error) {
        console.error('Failed to load orders:', error);
    } finally {
        loading.value = false;
    }
};

const loadOrderbook = async () => {
    orderbookLoading.value = true;
    try {
        const response = await axios.get('/api/orders', {
            params: { symbol: orderbookSymbol.value },
        });
        orderbook.value = response.data;
    } catch (error) {
        console.error('Failed to load orderbook:', error);
    } finally {
        orderbookLoading.value = false;
    }
};

const cancelOrder = async (orderId) => {
    if (!confirm('Are you sure you want to cancel this order?')) {
        return;
    }

    try {
        await axios.post(`/api/orders/${orderId}/cancel`);
        await loadOrders();
        await loadOrderbook();
        await loadProfile();
    } catch (error) {
        alert(error.response?.data?.message || 'Failed to cancel order');
    }
};

const setupPusher = () => {
    // Only setup Pusher if we have a user and token
    if (!props.user?.id || !localStorage.getItem('token')) {
        return;
    }

    // Initialize Laravel Echo with Pusher
    window.Pusher = Pusher;
    
    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
    const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1';
    
    // Only setup if Pusher key is configured
    if (!pusherKey) {
        console.warn('Pusher key not configured. Real-time updates will not work.');
        return;
    }
    
    echo = new Echo({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: pusherCluster,
        forceTLS: true,
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                Authorization: `Bearer ${localStorage.getItem('token')}`,
            },
        },
    });

    // Listen for order matched events on user's private channel
    echo.private(`user.${props.user.id}`)
        .listen('.order.matched', (event) => {
            console.log('Order matched:', event);
            
            // Refresh all data
            loadProfile();
            loadOrders();
            loadOrderbook();
        });
};

onMounted(async () => {
    await loadProfile();
    await loadOrders();
    await loadOrderbook();
    
    // Setup Pusher listener
    setupPusher();
    
    // Listen for order created events
    on('order-created', () => {
        loadOrders();
        loadOrderbook();
        loadProfile();
    });
});

onUnmounted(() => {
    if (echo) {
        echo.disconnect();
    }
});
</script>

