<template>
    <div class="min-h-screen bg-gray-50">
        <nav class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <h1 class="text-xl font-bold text-gray-900">Limit Order Exchange</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span v-if="user" class="text-sm text-gray-700">{{ user.name }}</span>
                        <button
                            v-if="user"
                            @click="logout"
                            class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900"
                        >
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <div v-if="!user" class="max-w-md mx-auto mt-20">
            <div class="bg-white shadow-md rounded-lg p-8">
                <h2 class="text-2xl font-bold mb-6 text-center">Login</h2>
                <form @submit.prevent="login" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input
                            v-model="loginForm.email"
                            type="email"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input
                            v-model="loginForm.password"
                            type="password"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <button
                        type="submit"
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        Login
                    </button>
                </form>
            </div>
        </div>

        <div v-else class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Order Form -->
                <OrderForm @order-created="handleOrderCreated" />

                <!-- Orders & Wallet Overview -->
                <OrdersWallet :user="user" />
            </div>
        </div>

        <!-- Toast Notifications -->
        <Toast />
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import OrderForm from './components/OrderForm.vue';
import OrdersWallet from './components/OrdersWallet.vue';
import Toast from './components/Toast.vue';
import { on } from './utils/eventBus.js';

const user = ref(null);
const loginForm = ref({
    email: '',
    password: '',
});

const checkAuth = async () => {
    try {
        const token = localStorage.getItem('token');
        if (token) {
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
            const response = await axios.get('/api/user');
            user.value = response.data;
        }
    } catch (error) {
        localStorage.removeItem('token');
        delete axios.defaults.headers.common['Authorization'];
    }
};

const login = async () => {
    try {
        const response = await axios.post('/api/login', loginForm.value);
        const token = response.data.token;
        localStorage.setItem('token', token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        await checkAuth();
    } catch (error) {
        alert('Login failed. Please check your credentials.');
    }
};

const logout = () => {
    localStorage.removeItem('token');
    delete axios.defaults.headers.common['Authorization'];
    user.value = null;
};

const handleOrderCreated = () => {
    // Order created, components will refresh automatically
};

onMounted(() => {
    checkAuth();
    
    // Listen for order created events
    on('order-created', handleOrderCreated);
});
</script>

