<script setup>
import { Head, usePage } from '@inertiajs/vue3';
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
                To jest Twój kabinet. Stąd będziesz zarządzać obiektami i protokołami zdawczo-odbiorczymi.
            </p>

            <div class="bg-white border border-slate-200 rounded-xl p-6">
                <p class="text-slate-500 text-sm">
                    Lista obiektów i historia protokołów pojawią się tutaj wkrótce (Faza 3).
                    Twoja rola — wynajmujący czy najemca — wybierasz osobno przy tworzeniu
                    każdego protokołu, a nie raz na zawsze przy koncie.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
