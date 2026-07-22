<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    document_exists: Boolean,
    document_hash: String,
    generated_at: String,
    document_type: String,
    protocol_type: String,
    protocol_type_label: String,
    legal_mode: String,
    legal_mode_label: String,
    initiator_signed: Boolean,
    counterparty_signed: Boolean,
    all_signed: Boolean,
    protocol_status: String,
    protocol_status_label: String,
    protocol_created_at: String,
    protocol_completed_at: String,
});
</script>

<template>
    <Head title="Weryfikacja dokumentu" />

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
                <!-- Verification Card -->
                <div class="bg-slate-800/50 border border-slate-700 rounded-2xl p-8">
                    <!-- Success Icon -->
                    <div class="flex justify-center mb-6">
                        <div class="w-20 h-20 bg-emerald-500/10 rounded-full flex items-center justify-center">
                            <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>

                    <h1 class="text-2xl font-bold text-white text-center mb-2">
                        Dokument zweryfikowany
                    </h1>
                    <p class="text-slate-400 text-center mb-8">
                        Ten dokument jest autentyczny i został wygenerowany przez system Rent2Proof.
                    </p>

                    <!-- Document Details -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center py-3 border-b border-slate-700">
                            <span class="text-slate-400">Typ dokumentu</span>
                            <span class="text-white font-medium">{{ document_type }}</span>
                        </div>

                        <div class="flex justify-between items-center py-3 border-b border-slate-700">
                            <span class="text-slate-400">Typ protokolu</span>
                            <span class="text-white font-medium">{{ protocol_type_label }}</span>
                        </div>

                        <div class="flex justify-between items-center py-3 border-b border-slate-700" v-if="legal_mode_label">
                            <span class="text-slate-400">Tryb prawny</span>
                            <span class="text-white font-medium">{{ legal_mode_label }}</span>
                        </div>

                        <div class="flex justify-between items-center py-3 border-b border-slate-700">
                            <span class="text-slate-400">Data wygenerowania</span>
                            <span class="text-white font-medium">{{ generated_at }}</span>
                        </div>

                        <div class="flex justify-between items-center py-3 border-b border-slate-700">
                            <span class="text-slate-400">Data utworzenia protokolu</span>
                            <span class="text-white font-medium">{{ protocol_created_at }}</span>
                        </div>

                        <div class="flex justify-between items-center py-3 border-b border-slate-700" v-if="protocol_completed_at">
                            <span class="text-slate-400">Data zakonczenia</span>
                            <span class="text-white font-medium">{{ protocol_completed_at }}</span>
                        </div>

                        <div class="flex justify-between items-center py-3 border-b border-slate-700">
                            <span class="text-slate-400">Status</span>
                            <span class="text-white font-medium">{{ protocol_status_label }}</span>
                        </div>

                        <!-- Signature Status -->
                        <div class="flex justify-between items-center py-3 border-b border-slate-700">
                            <span class="text-slate-400">Podpisy</span>
                            <div class="flex items-center gap-2">
                                <span v-if="all_signed" class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-500/10 text-emerald-400 rounded-full text-sm">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    Wszystkie strony podpisaly
                                </span>
                                <span v-else class="inline-flex items-center gap-1 px-3 py-1 bg-amber-500/10 text-amber-400 rounded-full text-sm">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                    </svg>
                                    Oczekuje na podpisy
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Document Hash -->
                    <div class="mt-6 p-4 bg-slate-900/50 rounded-xl">
                        <p class="text-slate-500 text-xs mb-2">Hash dokumentu (SHA-256)</p>
                        <p class="text-slate-300 font-mono text-xs break-all">{{ document_hash }}</p>
                    </div>
                </div>

                <!-- Info Notice -->
                <div class="mt-6 p-4 bg-blue-500/10 border border-blue-500/20 rounded-xl">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-blue-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <div>
                            <p class="text-blue-300 text-sm font-medium">Informacja o prywatnosci</p>
                            <p class="text-blue-400/70 text-sm mt-1">
                                Ta strona nie wyswietla danych osobowych stron protokolu.
                                Sluzy wylacznie do weryfikacji autentycznosci dokumentu.
                            </p>
                        </div>
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
