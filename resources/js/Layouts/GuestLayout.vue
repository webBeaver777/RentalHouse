<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 px-4 py-10">
        <Link href="/" class="mb-8 flex items-center gap-3">
            <div class="w-10 h-10 bg-emerald-500 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <span class="text-2xl font-bold text-white">Rent2Proof</span>
        </Link>

        <div class="w-full max-w-md bg-slate-800/50 border border-slate-700 rounded-2xl p-8 shadow-xl">
            <slot />
        </div>
    </div>
</template>
