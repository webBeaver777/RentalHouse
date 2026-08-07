<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    entitlements: {
        type: Object,
        default: () => ({}),
    },
    products: {
        type: Array,
        default: () => [],
    },
    devModeAvailable: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const status = computed(() => page.props.flash?.status);

const form = useForm({
    product_code: '',
});

function buy(productCode) {
    form.product_code = productCode;
    form.post(route('billing.dev-grant'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Dostęp" />
    <AppLayout>
        <div class="max-w-2xl">
            <h1 class="text-2xl font-bold text-slate-900 mb-1">Dostęp i uprawnienia</h1>
            <p class="text-slate-600 text-sm mb-6">
                Wysłanie zaproszenia drugiej stronie i wystawienie aktu wymaga opłaconego dostępu
                (HARD-GATE) — nie ma darmowego planu.
            </p>

            <div v-if="status" class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm">
                {{ status }}
            </div>

            <div v-if="devModeAvailable" class="mb-6 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
                Tryb deweloperski: prawdziwa integracja Przelewy24 nie jest jeszcze podłączona.
                Przyciski poniżej nadają dostęp bez płatności — tymczasowo, tylko w tym środowisku.
            </div>

            <div class="bg-white border border-slate-200 rounded-xl divide-y divide-slate-100 mb-6">
                <div
                    v-for="(summary, action) in entitlements"
                    :key="action"
                    class="p-5 flex items-center justify-between"
                >
                    <div>
                        <p class="font-medium text-slate-900">{{ summary.label }}</p>
                        <p class="text-sm text-slate-500">Pozostało: {{ summary.total_remaining }}</p>
                    </div>
                </div>
            </div>

            <div v-if="devModeAvailable" class="flex flex-wrap gap-3">
                <button
                    v-for="product in products"
                    :key="product.code"
                    type="button"
                    :disabled="form.processing"
                    @click="buy(product.code)"
                    class="px-4 py-2.5 text-sm font-medium bg-slate-900 text-white rounded-lg hover:bg-slate-700 transition disabled:opacity-50"
                >
                    Kup dostęp (dev): {{ product.label }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
