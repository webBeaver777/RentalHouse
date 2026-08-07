<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    declarationTypes: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: '',
    street: '',
    building_number: '',
    apartment_number: '',
    city: '',
    postal_code: '',
    country: 'PL',
    declaration_type: props.declarationTypes[0]?.value ?? 'owner',
    description: '',
});

function submit() {
    form.post(route('properties.store'));
}
</script>

<template>
    <Head title="Nowy obiekt" />
    <AppLayout>
        <div class="max-w-xl">
            <h1 class="text-2xl font-bold text-slate-900 mb-1">Nowy obiekt</h1>
            <p class="text-slate-600 text-sm mb-6">
                Dodaj nieruchomość, dla której będziesz tworzyć protokoły zdawczo-odbiorcze.
            </p>

            <form @submit.prevent="submit" class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Nazwa</label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        autofocus
                        placeholder="np. Mieszkanie przy ul. Testowej"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <label for="street" class="block text-sm font-medium text-slate-700 mb-1">Ulica</label>
                        <input
                            id="street"
                            v-model="form.street"
                            type="text"
                            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                        <p v-if="form.errors.street" class="mt-1 text-sm text-red-600">{{ form.errors.street }}</p>
                    </div>
                    <div>
                        <label for="building_number" class="block text-sm font-medium text-slate-700 mb-1">Numer</label>
                        <input
                            id="building_number"
                            v-model="form.building_number"
                            type="text"
                            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                        <p v-if="form.errors.building_number" class="mt-1 text-sm text-red-600">{{ form.errors.building_number }}</p>
                    </div>
                </div>

                <div>
                    <label for="apartment_number" class="block text-sm font-medium text-slate-700 mb-1">
                        Numer mieszkania <span class="text-slate-400 font-normal">(opcjonalnie)</span>
                    </label>
                    <input
                        id="apartment_number"
                        v-model="form.apartment_number"
                        type="text"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                    <p v-if="form.errors.apartment_number" class="mt-1 text-sm text-red-600">{{ form.errors.apartment_number }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="city" class="block text-sm font-medium text-slate-700 mb-1">Miasto</label>
                        <input
                            id="city"
                            v-model="form.city"
                            type="text"
                            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                        <p v-if="form.errors.city" class="mt-1 text-sm text-red-600">{{ form.errors.city }}</p>
                    </div>
                    <div>
                        <label for="postal_code" class="block text-sm font-medium text-slate-700 mb-1">Kod pocztowy</label>
                        <input
                            id="postal_code"
                            v-model="form.postal_code"
                            type="text"
                            placeholder="00-001"
                            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                        <p v-if="form.errors.postal_code" class="mt-1 text-sm text-red-600">{{ form.errors.postal_code }}</p>
                    </div>
                </div>

                <div>
                    <label for="declaration_type" class="block text-sm font-medium text-slate-700 mb-1">Typ zgłoszenia</label>
                    <select
                        id="declaration_type"
                        v-model="form.declaration_type"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                        <option v-for="type in declarationTypes" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>
                    <p v-if="form.errors.declaration_type" class="mt-1 text-sm text-red-600">{{ form.errors.declaration_type }}</p>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700 mb-1">
                        Opis <span class="text-slate-400 font-normal">(opcjonalnie)</span>
                    </label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    ></textarea>
                    <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-5 py-2.5 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium disabled:opacity-50"
                    >
                        Zapisz obiekt
                    </button>
                    <Link :href="route('properties.index')" class="text-sm text-slate-500 hover:text-slate-700">
                        Anuluj
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
