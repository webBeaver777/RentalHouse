<script setup>
import { Head, router } from '@inertiajs/vue3';

defineProps({
    session: String,
});

const simulateSuccess = () => {
    // In a real implementation, this would call the backend to simulate payment
    router.post('/payment/webhook', {
        sessionId: props.session,
        statement: 'OK',
        orderId: 'SANDBOX_' + Date.now(),
        amount: 4900,
        currency: 'PLN',
        sign: 'sandbox_signature',
    });
};
</script>

<template>
    <Head title="Sandbox platnosci" />

    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex items-center justify-center">
        <div class="max-w-md mx-auto px-6">
            <div class="bg-slate-800/50 border border-slate-700 rounded-2xl p-8">
                <!-- Sandbox Badge -->
                <div class="flex justify-center mb-6">
                    <span class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500/10 border border-amber-500/20 rounded-full text-amber-400 text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        Tryb sandbox
                    </span>
                </div>

                <h1 class="text-xl font-bold text-white text-center mb-2">
                    Symulacja platnosci P24
                </h1>
                <p class="text-slate-400 text-center mb-6">
                    To jest strona testowa. W produkcji uzytkownik zostanie przekierowany do prawdziwego systemu Przelewy24.
                </p>

                <div class="p-3 bg-slate-900/50 rounded-lg mb-6">
                    <p class="text-slate-500 text-xs">ID sesji</p>
                    <p class="text-slate-300 font-mono text-sm">{{ session }}</p>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-3">
                    <button
                        @click="simulateSuccess"
                        class="w-full px-6 py-3 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Symuluj udana platnosc
                    </button>

                    <a
                        href="/"
                        class="w-full px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-600 transition font-medium flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Anuluj
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
