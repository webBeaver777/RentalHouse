<script setup>
import { Head } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps({
    reason: {
        type: String,
        required: true,
    },
});

const messages = {
    not_found: 'Ten link jest nieprawidłowy.',
    expired: 'Ten link wygasł.',
    used: 'Ten link został już wykorzystany.',
    revoked: 'Ten link został unieważniony.',
};
</script>

<template>
    <Head title="Nieprawidłowy link" />
    <GuestLayout>
        <h1 class="text-2xl font-bold text-white mb-3">Link niedostępny</h1>
        <p class="text-slate-300 text-sm">
            {{ messages[props.reason] ?? 'Ten link jest niedostępny.' }}
        </p>
        <p class="text-slate-400 text-sm mt-2">
            Skontaktuj się z osobą, która wysłała Ci ten link, aby otrzymać nowy.
        </p>
    </GuestLayout>
</template>
