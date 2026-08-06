<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps({
    email: String,
    token: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Nowe hasło" />
    <GuestLayout>
        <h1 class="text-2xl font-bold text-white mb-6">Ustaw nowe hasło</h1>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-1">Adres e-mail</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    class="w-full rounded-lg bg-slate-900 border border-slate-700 text-white px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-400">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-300 mb-1">Nowe hasło</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    autofocus
                    class="w-full rounded-lg bg-slate-900 border border-slate-700 text-white px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-400">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-300 mb-1">Powtórz nowe hasło</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    class="w-full rounded-lg bg-slate-900 border border-slate-700 text-white px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
                <p v-if="form.errors.password_confirmation" class="mt-1 text-sm text-red-400">{{ form.errors.password_confirmation }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full py-2.5 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium disabled:opacity-50"
            >
                Zapisz nowe hasło
            </button>
        </form>
    </GuestLayout>
</template>
