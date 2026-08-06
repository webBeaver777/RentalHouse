<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <header class="bg-slate-900 border-b border-slate-800">
            <nav class="container mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <span class="text-lg font-bold text-white">Rent2Proof</span>
                </Link>

                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-300 hidden sm:inline">{{ user?.name }}</span>
                    <button
                        @click="logout"
                        class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-white border border-slate-700 rounded-lg hover:border-slate-500 transition"
                    >
                        Wyloguj się
                    </button>
                </div>
            </nav>
        </header>

        <main class="container mx-auto px-4 sm:px-6 py-8">
            <slot />
        </main>
    </div>
</template>
