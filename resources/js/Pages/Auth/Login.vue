<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Logowanie" />
    <GuestLayout>
        <h1 class="text-2xl font-bold text-white mb-6">Zaloguj się</h1>

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

            <div>
                <label for="password" class="block text-sm font-medium text-slate-300 mb-1">Hasło</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    class="w-full rounded-lg bg-slate-900 border border-slate-700 text-white px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-400">{{ form.errors.password }}</p>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-slate-400">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-700 bg-slate-900 text-emerald-500 focus:ring-emerald-500" />
                    Zapamiętaj mnie
                </label>
                <Link :href="route('password.request')" class="text-sm text-emerald-400 hover:text-emerald-300">
                    Nie pamiętasz hasła?
                </Link>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full py-2.5 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium disabled:opacity-50"
            >
                Zaloguj się
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-400">
            Nie masz konta?
            <Link :href="route('register')" class="text-emerald-400 hover:text-emerald-300">Zarejestruj się</Link>
        </p>
    </GuestLayout>
</template>
