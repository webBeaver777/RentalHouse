<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    protocol: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="protocol.title" />
    <AppLayout>
        <div class="max-w-xl">
            <Link :href="route('properties.index')" class="text-sm text-slate-500 hover:text-slate-700">
                &larr; Powrót do obiektów
            </Link>

            <div class="mt-4 flex items-center justify-between gap-3">
                <h1 class="text-2xl font-bold text-slate-900">{{ protocol.title }}</h1>
                <span class="shrink-0 text-xs px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full">
                    {{ protocol.status_label }}
                </span>
            </div>
            <p class="text-slate-600 text-sm mt-1">{{ protocol.type_label }}</p>

            <dl class="mt-6 bg-white border border-slate-200 rounded-xl divide-y divide-slate-100">
                <div class="p-5 flex items-center justify-between gap-4">
                    <dt class="text-sm text-slate-500">Obiekt</dt>
                    <dd class="text-sm font-medium text-slate-900 text-right">
                        {{ protocol.property.name }}
                        <span class="block text-xs font-normal text-slate-500">{{ protocol.property.full_address }}</span>
                    </dd>
                </div>
                <div class="p-5 flex items-center justify-between gap-4">
                    <dt class="text-sm text-slate-500">Status</dt>
                    <dd class="text-sm font-medium text-slate-900">{{ protocol.status_label }}</dd>
                </div>
                <div class="p-5 flex items-center justify-between gap-4">
                    <dt class="text-sm text-slate-500">Inicjator</dt>
                    <dd class="text-sm font-medium text-slate-900">{{ protocol.initiator_name }}</dd>
                </div>
            </dl>
        </div>
    </AppLayout>
</template>
