<template>
    <TransitionGroup
        name="toast"
        tag="div"
        class="fixed top-4 right-4 z-50 space-y-2"
    >
        <div
            v-for="toast in toasts"
            :key="toast.id"
            :class="getToastClass(toast.type)"
            class="min-w-[300px] p-4 rounded-lg shadow-lg flex items-start justify-between"
        >
            <div class="flex-1">
                <p class="font-medium">{{ toast.message }}</p>
            </div>
            <button
                @click="removeToast(toast.id)"
                class="ml-4 text-gray-400 hover:text-gray-600"
            >
                ✕
            </button>
        </div>
    </TransitionGroup>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { on, emit } from '../utils/eventBus.js';

const toasts = ref([]);

const getToastClass = (type) => {
    const classes = {
        success: 'bg-green-50 border border-green-200 text-green-800',
        error: 'bg-red-50 border border-red-200 text-red-800',
        info: 'bg-blue-50 border border-blue-200 text-blue-800',
        warning: 'bg-yellow-50 border border-yellow-200 text-yellow-800',
    };
    return classes[type] || classes.info;
};

const showToast = (message, type = 'info', duration = 3000) => {
    const id = Date.now();
    toasts.value.push({ id, message, type });
    
    setTimeout(() => {
        removeToast(id);
    }, duration);
};

const removeToast = (id) => {
    const index = toasts.value.findIndex(t => t.id === id);
    if (index > -1) {
        toasts.value.splice(index, 1);
    }
};

// Listen for toast events
onMounted(() => {
    on('toast', ({ message, type, duration }) => {
        showToast(message, type, duration);
    });
});

// Export function for use in other components
window.showToast = showToast;
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}

.toast-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}
</style>

