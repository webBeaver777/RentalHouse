<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
});

function submit() {
    form.post(route('password.email'));
}
</script>

<template>
    <Head title="Odzyskiwanie hasła" />
    <GuestLayout>
        <h1 class="text-2xl font-bold text-white mb-2">Nie pamiętasz hasła?</h1>
        <p class="text-slate-400 text-sm mb-6">
            Podaj adres e-mail, a wyślemy Ci link do zresetowania hasła.
        </p>

        <div v-if="status" class="mb-4 text-sm text-emerald-400">{{ status }}</div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-1">Adres e-mail</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    autofocus
                    class="w-full rounded-lg bg-slate-900 border border-slate-700 text-white px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-400">{{ form.errors.email }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full py-2.5 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium disabled:opacity-50"
            >
                Wyślij link resetujący
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-400">
            <Link :href="route('login')" class="text-emerald-400 hover:text-emerald-300">Wróć do logowania</Link>
        </p>
    </GuestLayout>
</template>
