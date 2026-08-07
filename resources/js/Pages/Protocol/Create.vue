<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    property: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    property_id: props.property.id,
    title: '',
});

function submit() {
    form.post(route('protocols.store'));
}
</script>

<template>
    <Head title="Nowy protokół wjazdu" />
    <AppLayout>
        <div class="max-w-xl">
            <h1 class="text-2xl font-bold text-slate-900 mb-1">Nowy protokół wjazdu</h1>
            <p class="text-slate-600 text-sm mb-6">
                Protokół zdawczo-odbiorczy (wprowadzenie) dla obiektu poniżej. Zostanie utworzony w statusie „Szkic”.
            </p>

            <div class="bg-white border border-slate-200 rounded-xl p-5 mb-4">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Obiekt</p>
                <p class="font-medium text-slate-900">{{ property.name }}</p>
                <p class="text-sm text-slate-500">{{ property.full_address }}</p>
            </div>

            <form @submit.prevent="submit" class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-slate-700 mb-1">
                        Tytuł protokołu <span class="text-slate-400 font-normal">(opcjonalnie)</span>
                    </label>
                    <input
                        id="title"
                        v-model="form.title"
                        type="text"
                        autofocus
                        :placeholder="`Protokół wjazdu — ${property.name}`"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                    <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
                    <p v-if="form.errors.property_id" class="mt-1 text-sm text-red-600">{{ form.errors.property_id }}</p>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-5 py-2.5 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium disabled:opacity-50"
                    >
                        Utwórz protokół
                    </button>
                    <Link :href="route('properties.index')" class="text-sm text-slate-500 hover:text-slate-700">
                        Anuluj
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
