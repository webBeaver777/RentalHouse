<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
</script>

<template>
    <Head title="Kabinet" />
    <AppLayout>
        <div class="max-w-2xl">
            <h1 class="text-2xl font-bold text-slate-900 mb-2">
                Witaj, {{ user?.name }}
            </h1>
            <p class="text-slate-600 mb-6">
                To jest Twój kabinet. Stąd zarządzasz obiektami i protokołami zdawczo-odbiorczymi.
                Twoja rola — wynajmujący czy najemca — wybierasz osobno przy tworzeniu każdego
                protokołu, a nie raz na zawsze przy koncie.
            </p>

            <div class="bg-white border border-slate-200 rounded-xl p-6 flex items-center justify-between">
                <div>
                    <p class="font-medium text-slate-900">Obiekty</p>
                    <p class="text-slate-500 text-sm">Twoje nieruchomości i tworzenie nowych protokołów.</p>
                </div>
                <Link
                    :href="route('properties.index')"
                    class="px-4 py-2.5 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium text-sm whitespace-nowrap"
                >
                    Przejdź do obiektów
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
