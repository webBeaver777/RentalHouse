<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    hash: String,
});
</script>

<template>
    <Head title="Dokument nie znaleziony" />

    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
        <!-- Header -->
        <header class="container mx-auto px-6 py-6">
            <nav class="flex items-center justify-center">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-emerald-500 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-white">Rent2Proof</span>
                </div>
            </nav>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-6 py-12">
            <div class="max-w-2xl mx-auto">
                <!-- Not Found Card -->
                <div class="bg-slate-800/50 border border-slate-700 rounded-2xl p-8">
                    <!-- Error Icon -->
                    <div class="flex justify-center mb-6">
                        <div class="w-20 h-20 bg-red-500/10 rounded-full flex items-center justify-center">
                            <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>

                    <h1 class="text-2xl font-bold text-white text-center mb-2">
                        Dokument nie znaleziony
                    </h1>
                    <p class="text-slate-400 text-center mb-8">
                        Nie znaleziono dokumentu o podanym identyfikatorze.
                        Sprawdz, czy kod QR zostal poprawnie zeskanowany.
                    </p>

                    <!-- Possible Reasons -->
                    <div class="space-y-3">
                        <div class="flex items-start gap-3 p-4 bg-slate-900/50 rounded-xl">
                            <svg class="w-5 h-5 text-slate-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <p class="text-slate-300 text-sm font-medium">Nieprawidlowy kod QR</p>
                                <p class="text-slate-500 text-sm">Kod QR mogl zostac uszkodzony lub nieprawidlowo zeskanowany.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-4 bg-slate-900/50 rounded-xl">
                            <svg class="w-5 h-5 text-slate-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <p class="text-slate-300 text-sm font-medium">Dokument usunieto</p>
                                <p class="text-slate-500 text-sm">Dokument mogl zostac usuniety po uplywie okresu przechowywania.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Back Link -->
                    <div class="mt-8 text-center">
                        <a href="/" class="inline-flex items-center gap-2 px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-600 transition font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Wróc do strony glównej
                        </a>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-800 py-8 mt-auto">
            <div class="container mx-auto px-6 text-center text-slate-500 text-sm">
                &copy; 2026 Rent2Proof. Wszystkie prawa zastrzezone.
            </div>
        </footer>
    </div>
</template>
