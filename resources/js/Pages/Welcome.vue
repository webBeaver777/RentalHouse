<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    laravelVersion: String,
    phpVersion: String,
});
</script>

<template>
    <Head title="Strona główna" />

    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
        <!-- Header -->
        <header class="container mx-auto px-6 py-6">
            <nav class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-emerald-500 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-white">Rent2Proof</span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="/admin" class="px-4 py-2 text-slate-300 hover:text-white transition">
                        Zaloguj się
                    </a>
                    <a href="/admin" class="px-5 py-2.5 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium">
                        Panel admina
                    </a>
                </div>
            </nav>
        </header>

        <!-- Hero Section -->
        <main class="container mx-auto px-6 pt-20 pb-32">
            <div class="max-w-4xl mx-auto text-center">
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-emerald-400 text-sm mb-8">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    Zgodny z RODO
                </div>

                <h1 class="text-5xl md:text-6xl font-bold text-white mb-6 leading-tight">
                    Dokumentuj stan mieszkania
                    <span class="text-emerald-400">profesjonalnie</span>
                </h1>

                <p class="text-xl text-slate-400 mb-10 max-w-2xl mx-auto">
                    System protokołów zdawczo-odbiorczych dla wynajmu nieruchomości.
                    Zdjęcia, podpisy, PDF — wszystko w jednym miejscu.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="/admin" class="px-8 py-4 bg-emerald-500 text-white rounded-xl hover:bg-emerald-600 transition font-semibold text-lg shadow-lg shadow-emerald-500/25">
                        Rozpocznij za darmo
                    </a>
                    <a href="#features" class="px-8 py-4 bg-slate-700 text-white rounded-xl hover:bg-slate-600 transition font-semibold text-lg">
                        Zobacz funkcje
                    </a>
                </div>
            </div>

            <!-- Features Section -->
            <div id="features" class="mt-32 grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <div class="bg-slate-800/50 border border-slate-700 rounded-2xl p-8">
                    <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-3">Dokumentacja zdjęciowa</h3>
                    <p class="text-slate-400">Dodawaj zdjęcia każdego pomieszczenia z automatycznym hashowaniem SHA-256 dla integralności.</p>
                </div>

                <div class="bg-slate-800/50 border border-slate-700 rounded-2xl p-8">
                    <div class="w-12 h-12 bg-purple-500/10 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-3">Podpisy elektroniczne</h3>
                    <p class="text-slate-400">Najemca i wynajmujący podpisują protokół online. Magic-link dla kontrahentów bez rejestracji.</p>
                </div>

                <div class="bg-slate-800/50 border border-slate-700 rounded-2xl p-8">
                    <div class="w-12 h-12 bg-orange-500/10 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-3">Eksport do PDF</h3>
                    <p class="text-slate-400">Generuj profesjonalne protokoły PDF gotowe do wydruku lub archiwizacji.</p>
                </div>
            </div>

            <!-- Tech Stack -->
            <div class="mt-20 text-center">
                <p class="text-slate-500 text-sm">
                    Laravel {{ laravelVersion }} · PHP {{ phpVersion }} · Vue 3 · Inertia.js · Tailwind CSS
                </p>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-800 py-8">
            <div class="container mx-auto px-6 text-center text-slate-500 text-sm">
                © 2026 Rent2Proof. Wszystkie prawa zastrzeżone.
            </div>
        </footer>
    </div>
</template>
