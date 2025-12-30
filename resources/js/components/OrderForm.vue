<template>
    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-xl font-bold mb-6">Place Limit Order</h2>
        
        <form @submit.prevent="submitOrder" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Symbol</label>
                <select
                    v-model="form.symbol"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Select Symbol</option>
                    <option value="BTC">BTC</option>
                    <option value="ETH">ETH</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Side</label>
                <select
                    v-model="form.side"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Select Side</option>
                    <option value="buy">Buy</option>
                    <option value="sell">Sell</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Price (USD)</label>
                <input
                    v-model.number="form.price"
                    type="number"
                    step="0.01"
                    min="0.01"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                <input
                    v-model.number="form.amount"
                    type="number"
                    step="0.00000001"
                    min="0.00000001"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
            </div>

            <div v-if="form.price && form.amount" class="text-sm text-gray-600 bg-gray-50 p-3 rounded-md">
                <div class="space-y-1">
                    <p><strong>Volume:</strong> ${{ (form.price * form.amount).toFixed(2) }}</p>
                    <p><strong>Commission (1.5%):</strong> ${{ ((form.price * form.amount) * 0.015).toFixed(2) }}</p>
                    <p v-if="form.side === 'buy'" class="text-red-600">
                        <strong>Total to pay:</strong> ${{ ((form.price * form.amount) * 1.015).toFixed(2) }}
                    </p>
                </div>
            </div>

            <button
                type="submit"
                :disabled="loading"
                class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                {{ loading ? 'Placing Order...' : 'Place Order' }}
            </button>

            <div v-if="error" class="text-red-600 text-sm mt-2">
                {{ error }}
            </div>

            <div v-if="success" class="text-green-600 text-sm mt-2">
                Order placed successfully!
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { emit } from '../utils/eventBus.js';

const form = ref({
    symbol: '',
    side: '',
    price: null,
    amount: null,
});

const loading = ref(false);
const error = ref(null);
const success = ref(false);

const submitOrder = async () => {
    loading.value = true;
    error.value = null;
    success.value = false;

    try {
        const response = await axios.post('/api/orders', form.value);
        
        // Reset form
        form.value = {
            symbol: '',
            side: '',
            price: null,
            amount: null,
        };
        
        success.value = true;
        emit('order-created', response.data);
        
        // Show toast notification
        if (window.showToast) {
            window.showToast('Order placed successfully!', 'success');
        }
        
        // Clear success message after 3 seconds
        setTimeout(() => {
            success.value = false;
        }, 3000);
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to place order';
        if (window.showToast) {
            window.showToast(error.value, 'error');
        }
    } finally {
        loading.value = false;
    }
};
</script>

