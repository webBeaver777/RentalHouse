<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    token: {
        type: String,
        required: true,
    },
    already_signed: {
        type: Boolean,
        default: false,
    },
    participant: {
        type: Object,
        required: true,
    },
    protocol: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const status = computed(() => page.props.flash?.status ?? page.props.status);

function buildDeviceFingerprint() {
    try {
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;

        return `${navigator.userAgent} | ${screen.width}x${screen.height} | ${tz}`.slice(0, 255);
    } catch {
        return navigator.userAgent.slice(0, 255);
    }
}

const form = useForm({
    consent: false,
    device_fingerprint: '',
});

function submitSign() {
    form.device_fingerprint = buildDeviceFingerprint();
    form.post(route('invitation.sign', props.token), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="protocol.title" />
    <div class="min-h-screen bg-slate-50">
        <header class="bg-slate-900 border-b border-slate-800">
            <div class="container mx-auto px-4 sm:px-6 py-4 flex items-center gap-2">
                <div class="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <span class="text-lg font-bold text-white">Rent2Proof</span>
                <span class="ml-3 text-xs px-2.5 py-1 bg-slate-800 text-slate-300 rounded-full">
                    Widok gościa — {{ participant.role_label }}
                </span>
            </div>
        </header>

        <main class="container mx-auto px-4 sm:px-6 py-8 max-w-2xl">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-2xl font-bold text-slate-900">{{ protocol.title }}</h1>
                <span class="shrink-0 text-xs px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full">
                    {{ protocol.status_label }}
                </span>
            </div>
            <p class="text-slate-600 text-sm mt-1">{{ protocol.type_label }}</p>

            <div v-if="status" class="mt-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm">
                {{ status }}
            </div>

            <dl class="mt-6 bg-white border border-slate-200 rounded-xl divide-y divide-slate-100">
                <div class="p-5 flex items-center justify-between gap-4">
                    <dt class="text-sm text-slate-500">Obiekt</dt>
                    <dd class="text-sm font-medium text-slate-900 text-right">
                        {{ protocol.property.name }}
                        <span class="block text-xs font-normal text-slate-500">{{ protocol.property.full_address }}</span>
                    </dd>
                </div>
                <div class="p-5 flex items-center justify-between gap-4">
                    <dt class="text-sm text-slate-500">Inicjator</dt>
                    <dd class="text-sm font-medium text-slate-900">{{ protocol.initiator_name }}</dd>
                </div>
            </dl>

            <!-- Accept + sign -->
            <div class="mt-6 bg-white border border-slate-200 rounded-xl p-5 space-y-3">
                <h2 class="text-lg font-bold text-slate-900">Podpis</h2>

                <p v-if="already_signed || participant.has_signed" class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2">
                    Dziękujemy — protokół został przez Ciebie podpisany.
                </p>

                <form v-else @submit.prevent="submitSign" class="space-y-3">
                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input v-model="form.consent" type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-500 focus:ring-emerald-500" />
                        <span>
                            Zapoznałem/am się z treścią protokołu i akceptuję jego treść jako {{ participant.role_label.toLowerCase() }}.
                        </span>
                    </label>
                    <p v-if="form.errors.consent" class="text-sm text-red-600">{{ form.errors.consent }}</p>

                    <button
                        type="submit"
                        :disabled="form.processing || !form.consent"
                        class="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium text-sm disabled:opacity-50"
                    >
                        Podpisz
                    </button>
                </form>
            </div>

            <!-- Rooms (read-only) -->
            <div class="mt-8">
                <h2 class="text-lg font-bold text-slate-900 mb-3">Pomieszczenia</h2>

                <p v-if="protocol.rooms.length === 0" class="text-sm text-slate-500">Brak pomieszczeń.</p>

                <div class="space-y-4">
                    <div v-for="room in protocol.rooms" :key="room.id" class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                        <div class="p-4 bg-slate-50 border-b border-slate-100">
                            <p class="font-medium text-slate-900">{{ room.display_name }}</p>
                        </div>

                        <div class="divide-y divide-slate-100">
                            <div v-for="item in room.items" :key="item.id" class="p-4">
                                <p class="text-sm font-medium text-slate-900">
                                    {{ item.display_name }}
                                    <span v-if="item.quantity > 1" class="text-slate-400 font-normal">&times;{{ item.quantity }}</span>
                                </p>
                                <p v-if="item.condition_name" class="text-xs text-slate-500">{{ item.condition_name }}</p>

                                <div v-if="item.photos.length" class="mt-2 flex flex-wrap gap-2">
                                    <img
                                        v-for="photo in item.photos"
                                        :key="photo.id"
                                        :src="photo.url"
                                        :alt="photo.original_filename"
                                        class="w-16 h-16 object-cover rounded-lg border border-slate-200"
                                    />
                                </div>
                            </div>

                            <p v-if="room.items.length === 0" class="p-4 text-sm text-slate-400">Brak pozycji w tym pomieszczeniu.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
