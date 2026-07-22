<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    token: String,
});
</script>

<template>
    <Head title="Zaproszenie do protokolu" />

    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex items-center justify-center">
        <div class="max-w-md mx-auto px-6">
            <div class="bg-slate-800/50 border border-slate-700 rounded-2xl p-8">
                <!-- Invite Icon -->
                <div class="flex justify-center mb-6">
                    <div class="w-16 h-16 bg-emerald-500/10 rounded-full flex items-center justify-center">
                        <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>

                <h1 class="text-xl font-bold text-white text-center mb-2">
                    Zaproszenie do protokolu
                </h1>
                <p class="text-slate-400 text-center mb-6">
                    Zostales zaproszony do udzialu w protokole zdawczo-odbiorczym.
                    Przejrzyj dokumentacje i dodaj swoje uwagi.
                </p>

                <!-- Token Info -->
                <div class="p-3 bg-slate-900/50 rounded-lg mb-6">
                    <p class="text-slate-500 text-xs mb-1">Token zaproszenia</p>
                    <p class="text-slate-300 font-mono text-xs break-all">{{ token }}</p>
                </div>

                <!-- Actions -->
                <div class="space-y-3">
                    <button class="w-full px-6 py-3 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium">
                        Przejdz do protokolu
                    </button>
                </div>

                <!-- Info Notice -->
                <div class="mt-6 p-4 bg-blue-500/10 border border-blue-500/20 rounded-xl">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-blue-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <p class="text-blue-400/70 text-sm">
                            Link jest wazny przez 72 godziny. Po tym czasie bedziesz musial poprosic o nowy link.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
