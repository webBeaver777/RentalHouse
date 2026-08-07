<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    properties: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const status = computed(() => page.props.flash?.status ?? page.props.status);
</script>

<template>
    <Head title="Obiekty" />
    <AppLayout>
        <div class="max-w-3xl">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Twoje obiekty</h1>
                    <p class="text-slate-600 text-sm mt-1">
                        Stąd tworzysz protokoły zdawczo-odbiorcze dla swoich nieruchomości.
                    </p>
                </div>
                <Link
                    :href="route('properties.create')"
                    class="px-4 py-2.5 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium text-sm whitespace-nowrap"
                >
                    + Dodaj obiekt
                </Link>
            </div>

            <div v-if="status" class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm">
                {{ status }}
            </div>

            <div v-if="properties.length === 0" class="bg-white border border-slate-200 rounded-xl p-8 text-center">
                <p class="text-slate-500 mb-4">Nie masz jeszcze żadnych obiektów.</p>
                <Link
                    :href="route('properties.create')"
                    class="inline-block px-4 py-2.5 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium text-sm"
                >
                    Dodaj pierwszy obiekt
                </Link>
            </div>

            <div v-else class="bg-white border border-slate-200 rounded-xl divide-y divide-slate-100">
                <div
                    v-for="property in properties"
                    :key="property.id"
                    class="p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                >
                    <div class="flex items-center justify-between sm:block gap-3">
                        <div>
                            <p class="font-medium text-slate-900">{{ property.name }}</p>
                            <p class="text-sm text-slate-500">{{ property.full_address }}</p>
                        </div>
                        <span class="text-xs px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full sm:hidden">
                            {{ property.declaration_type_label }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full hidden sm:inline-block">
                            {{ property.declaration_type_label }}
                        </span>
                        <Link
                            :href="route('protocols.create', { property_id: property.id })"
                            class="px-3.5 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-700 transition font-medium text-sm whitespace-nowrap"
                        >
                            Utwórz protokół wjazdu
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
