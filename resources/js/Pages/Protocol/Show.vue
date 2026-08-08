<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    protocol: {
        type: Object,
        required: true,
    },
    catalogs: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const status = computed(() => page.props.flash?.status ?? page.props.status);

/* --- add room --- */
const addRoomOpen = ref(false);
const addRoomForm = useForm({
    catalog_item_id: props.catalogs.room_types[0]?.id ?? '',
    custom_name: '',
});

function submitAddRoom() {
    addRoomForm.post(route('protocols.rooms.store', props.protocol.id), {
        preserveScroll: true,
        onSuccess: () => {
            addRoomForm.reset();
            addRoomOpen.value = false;
        },
    });
}

/* --- edit room --- */
const editingRoomId = ref(null);
const editRoomForm = useForm({ custom_name: '' });

function startEditRoom(room) {
    editingRoomId.value = room.id;
    editRoomForm.custom_name = room.custom_name ?? '';
}

function cancelEditRoom() {
    editingRoomId.value = null;
}

function submitEditRoom(room) {
    editRoomForm.put(route('protocols.rooms.update', [props.protocol.id, room.id]), {
        preserveScroll: true,
        onSuccess: () => {
            editingRoomId.value = null;
        },
    });
}

function deleteRoom(room) {
    router.delete(route('protocols.rooms.destroy', [props.protocol.id, room.id]), {
        preserveScroll: true,
    });
}

/* --- add item --- */
const addItemRoomId = ref(null);
const addItemForm = useForm({
    catalog_item_id: props.catalogs.item_templates[0]?.id ?? '',
    condition_catalog_item_id: props.catalogs.condition_states[0]?.id ?? '',
    quantity: 1,
    custom_name: '',
});

function openAddItem(room) {
    addItemForm.reset();
    addItemRoomId.value = room.id;
}

function cancelAddItem() {
    addItemRoomId.value = null;
}

function submitAddItem(room) {
    addItemForm.post(route('protocols.rooms.items.store', [props.protocol.id, room.id]), {
        preserveScroll: true,
        onSuccess: () => {
            addItemRoomId.value = null;
        },
    });
}

/* --- edit item --- */
const editingItemId = ref(null);
const editItemForm = useForm({
    condition_catalog_item_id: '',
    quantity: 1,
    custom_name: '',
});

function startEditItem(item) {
    editingItemId.value = item.id;
    editItemForm.condition_catalog_item_id = item.condition_catalog_item_id ?? '';
    editItemForm.quantity = item.quantity;
    editItemForm.custom_name = item.custom_name ?? '';
}

function cancelEditItem() {
    editingItemId.value = null;
}

function submitEditItem(room, item) {
    editItemForm.put(route('protocols.rooms.items.update', [props.protocol.id, room.id, item.id]), {
        preserveScroll: true,
        onSuccess: () => {
            editingItemId.value = null;
        },
    });
}

function deleteItem(room, item) {
    router.delete(route('protocols.rooms.items.destroy', [props.protocol.id, room.id, item.id]), {
        preserveScroll: true,
    });
}

/* --- photos --- */
const photoForm = useForm({ photo: null });
const uploadingItemId = ref(null);
const photoErrorItemId = ref(null);

function onPhotoSelected(event, room, item) {
    const file = event.target.files[0];
    if (!file) return;

    photoForm.clearErrors();
    photoForm.photo = file;
    uploadingItemId.value = item.id;
    photoErrorItemId.value = null;

    photoForm.post(route('protocols.rooms.items.photos.store', [props.protocol.id, room.id, item.id]), {
        preserveScroll: true,
        forceFormData: true,
        onError: () => {
            photoErrorItemId.value = item.id;
        },
        onSuccess: () => {
            photoForm.reset();
        },
        onFinish: () => {
            uploadingItemId.value = null;
            event.target.value = '';
        },
    });
}

function deletePhoto(room, item, photo) {
    router.delete(route('protocols.rooms.items.photos.destroy', [props.protocol.id, room.id, item.id, photo.id]), {
        preserveScroll: true,
    });
}

/* --- dev payment (stub for real Przelewy24 flow) --- */
const payForm = useForm({
    product_code: 'WJAZD',
    protocol_id: props.protocol.id,
});

function submitPay() {
    payForm.post(route('billing.dev-grant'), { preserveScroll: true });
}

/* --- submit to counterparty (hard-gated on entitlement server-side) --- */
const submitOpen = ref(false);
const submitForm = useForm({ counterparty_email: '' });

function submitToCounterparty() {
    submitForm.post(route('protocols.submit', props.protocol.id), {
        preserveScroll: true,
        onSuccess: () => {
            submitForm.reset();
            submitOpen.value = false;
        },
    });
}

/* --- initiator sign (guest side lives on Invitation/Accept.vue) --- */
function buildDeviceFingerprint() {
    try {
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;

        return `${navigator.userAgent} | ${screen.width}x${screen.height} | ${tz}`.slice(0, 255);
    } catch {
        return navigator.userAgent.slice(0, 255);
    }
}

const signForm = useForm({ device_fingerprint: '' });

function submitSign() {
    signForm.device_fingerprint = buildDeviceFingerprint();
    signForm.post(route('protocols.sign', props.protocol.id), { preserveScroll: true });
}

/* --- finalize (seal protocol -> completed, generates the frozen PDF) --- */
const finalizeForm = useForm({ unilateral: false });
const unilateralConfirmed = ref(false);

function submitFinalize() {
    finalizeForm.unilateral = false;
    finalizeForm.post(route('protocols.finalize', props.protocol.id), { preserveScroll: true });
}

function submitFinalizeUnilateral() {
    finalizeForm.unilateral = true;
    finalizeForm.post(route('protocols.finalize', props.protocol.id), {
        preserveScroll: true,
        onSuccess: () => {
            unilateralConfirmed.value = false;
        },
    });
}
</script>

<template>
    <Head :title="protocol.title" />
    <AppLayout>
        <div class="max-w-2xl">
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
                    <dt class="text-sm text-slate-500">Status</dt>
                    <dd class="text-sm font-medium text-slate-900">{{ protocol.status_label }}</dd>
                </div>
                <div class="p-5 flex items-center justify-between gap-4">
                    <dt class="text-sm text-slate-500">Inicjator</dt>
                    <dd class="text-sm font-medium text-slate-900">{{ protocol.initiator_name }}</dd>
                </div>
                <div class="p-5 flex items-center justify-between gap-4">
                    <dt class="text-sm text-slate-500">Dostęp (opłata)</dt>
                    <dd class="text-sm font-medium" :class="protocol.has_entitlement ? 'text-emerald-600' : 'text-slate-400'">
                        {{ protocol.has_entitlement ? 'Opłacono' : 'Nieopłacono' }}
                    </dd>
                </div>
                <div v-if="protocol.counterparty_status_label" class="p-5 flex items-center justify-between gap-4">
                    <dt class="text-sm text-slate-500">Druga strona</dt>
                    <dd class="text-sm font-medium text-slate-900">{{ protocol.counterparty_status_label }}</dd>
                </div>
            </dl>

            <!-- Payment + submit to counterparty -->
            <div v-if="protocol.is_draft" class="mt-6 bg-white border border-slate-200 rounded-xl p-5 space-y-4">
                <h2 class="text-lg font-bold text-slate-900">Płatność i wysyłka</h2>

                <div v-if="!protocol.has_entitlement">
                    <p class="text-sm text-slate-600 mb-2">Wysłanie protokołu do drugiej strony wymaga opłaty.</p>
                    <form v-if="protocol.dev_mode_available" @submit.prevent="submitPay">
                        <button
                            type="submit"
                            :disabled="payForm.processing"
                            class="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition font-medium text-sm disabled:opacity-50"
                        >
                            Zapłać (dev)
                        </button>
                    </form>
                </div>
                <p v-else class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2">
                    Dostęp opłacony — możesz wysłać protokół do drugiej strony.
                </p>

                <div>
                    <button
                        v-if="!submitOpen"
                        type="button"
                        @click="submitOpen = true"
                        class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-700 transition font-medium text-sm"
                    >
                        Wyślij do drugiej strony
                    </button>

                    <form v-else @submit.prevent="submitToCounterparty" class="space-y-2 mt-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            E-mail drugiej strony ({{ protocol.counterparty_role_label }})
                        </label>
                        <input
                            v-model="submitForm.counterparty_email"
                            type="email"
                            required
                            :placeholder="`np. ${protocol.counterparty_role_label.toLowerCase()}@example.com`"
                            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                        <p v-if="submitForm.errors.counterparty_email" class="text-sm text-red-600">{{ submitForm.errors.counterparty_email }}</p>
                        <div class="flex items-center gap-3">
                            <button
                                type="submit"
                                :disabled="submitForm.processing"
                                class="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium text-sm disabled:opacity-50"
                            >
                                Wyślij
                            </button>
                            <button type="button" @click="submitOpen = false" class="text-sm text-slate-500 hover:text-slate-700">
                                Anuluj
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="protocol.magic_link" class="mt-4 bg-amber-50 border border-amber-200 rounded-xl p-5">
                <p class="text-sm font-medium text-amber-900 mb-1">Magic-link dla drugiej strony (dev)</p>
                <p class="text-xs text-amber-800 mb-2">
                    W produkcji zostanie wysłany e-mailem — tutaj widoczny tylko w trybie dev.
                </p>
                <a :href="protocol.magic_link" target="_blank" class="text-sm text-emerald-700 hover:text-emerald-800 break-all underline">
                    {{ protocol.magic_link }}
                </a>
            </div>

            <!-- Sign (after being sent — draft-only send form above no longer applies) -->
            <div v-if="!protocol.is_draft" class="mt-6 bg-white border border-slate-200 rounded-xl p-5 space-y-3">
                <h2 class="text-lg font-bold text-slate-900">Podpis</h2>

                <p v-if="protocol.initiator_signed" class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2">
                    Podpisałeś/aś ten protokół.
                </p>

                <form v-else-if="protocol.can_sign" @submit.prevent="submitSign">
                    <p class="text-sm text-slate-600 mb-3">Potwierdź podpis protokołu jako inicjator.</p>
                    <p v-if="signForm.errors.sign" class="mb-2 text-sm text-red-600">{{ signForm.errors.sign }}</p>
                    <button
                        type="submit"
                        :disabled="signForm.processing"
                        class="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium text-sm disabled:opacity-50"
                    >
                        Podpisz
                    </button>
                </form>

                <p v-else class="text-sm text-slate-500">Podpis niedostępny w bieżącym statusie protokołu.</p>
            </div>

            <!-- Finalize + frozen document (M10.6, unilateral fallback M11) -->
            <div
                v-if="protocol.is_completed || protocol.can_finalize_bilateral || protocol.can_finalize_unilateral"
                class="mt-6 bg-white border border-slate-200 rounded-xl p-5 space-y-3"
            >
                <h2 class="text-lg font-bold text-slate-900">Zakończenie protokołu</h2>

                <div v-if="protocol.is_completed" class="space-y-3">
                    <p
                        v-if="protocol.is_unilateral_notice"
                        class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2"
                    >
                        Protokół został zakończony <strong>jednostronnie</strong> ({{ protocol.legal_mode_label }}) —
                        druga strona nie podpisała. To NIE jest dokument dwustronny — struktura i zdjęcia nie mogą być już zmieniane.
                    </p>
                    <p v-else class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2">
                        Protokół został zakończony i zapieczętowany ({{ protocol.legal_mode_label }}) — struktura i zdjęcia nie mogą być już zmieniane.
                    </p>
                    <div class="flex flex-wrap items-center gap-4">
                        <a
                            v-if="protocol.document_url"
                            :href="protocol.document_url"
                            target="_blank"
                            class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-700 transition font-medium text-sm"
                        >
                            Pobierz PDF
                        </a>
                        <a
                            v-if="protocol.qr_url"
                            :href="protocol.qr_url"
                            target="_blank"
                            class="text-sm text-emerald-700 hover:text-emerald-800 underline"
                        >
                            Strona weryfikacji (QR)
                        </a>
                    </div>
                    <p v-if="protocol.document_hash" class="text-xs text-slate-400 break-all">Hash dokumentu: {{ protocol.document_hash }}</p>
                </div>

                <form v-else-if="protocol.can_finalize_bilateral" @submit.prevent="submitFinalize">
                    <p class="text-sm text-slate-600 mb-3">
                        Obie strony podpisały protokół — możesz go zakończyć i wygenerować dokument PDF.
                    </p>
                    <p v-if="finalizeForm.errors.finalize" class="mb-2 text-sm text-red-600">{{ finalizeForm.errors.finalize }}</p>
                    <button
                        type="submit"
                        :disabled="finalizeForm.processing"
                        class="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium text-sm disabled:opacity-50"
                    >
                        Zakończ protokół
                    </button>
                </form>

                <!-- M11 Scenario B: unilateral fallback — druga strona (zazwyczaj
                     wynajmujący) nie podpisała. Wymaga jawnej zgody, żeby nie
                     "ściąć rogu" tam, gdzie podpis dwustronny wciąż jest możliwy. -->
                <form v-else-if="protocol.can_finalize_unilateral" @submit.prevent="submitFinalizeUnilateral" class="space-y-3">
                    <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2">
                        Druga strona ({{ protocol.counterparty_role_label }}) jeszcze nie podpisała protokołu.
                        Możesz zakończyć go <strong>jednostronnie</strong> — dokument będzie wyraźnie oznaczony jako
                        jednostronny, NIE jako pełny protokół dwustronny.
                    </p>
                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input v-model="unilateralConfirmed" type="checkbox" class="mt-0.5 rounded border-slate-300 text-amber-500 focus:ring-amber-500" />
                        <span>Rozumiem, że to jednostronne zakończenie protokołu bez podpisu drugiej strony.</span>
                    </label>
                    <p v-if="finalizeForm.errors.finalize" class="text-sm text-red-600">{{ finalizeForm.errors.finalize }}</p>
                    <button
                        type="submit"
                        :disabled="finalizeForm.processing || !unilateralConfirmed"
                        class="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition font-medium text-sm disabled:opacity-50"
                    >
                        Zakończ jednostronnie
                    </button>
                </form>
            </div>

            <!-- Rooms -->
            <div class="mt-8 flex items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-slate-900">Pomieszczenia</h2>
                <button
                    v-if="protocol.is_draft && !addRoomOpen"
                    type="button"
                    @click="addRoomOpen = true"
                    class="px-3.5 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-700 transition font-medium text-sm whitespace-nowrap"
                >
                    + Dodaj pomieszczenie
                </button>
            </div>

            <form
                v-if="addRoomOpen"
                @submit.prevent="submitAddRoom"
                class="mt-3 bg-white border border-slate-200 rounded-xl p-5 space-y-3"
            >
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Typ pomieszczenia</label>
                    <select
                        v-model="addRoomForm.catalog_item_id"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                        <option v-for="type in catalogs.room_types" :key="type.id" :value="type.id">{{ type.label }}</option>
                    </select>
                    <p v-if="addRoomForm.errors.catalog_item_id" class="mt-1 text-sm text-red-600">{{ addRoomForm.errors.catalog_item_id }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Nazwa własna <span class="text-slate-400 font-normal">(opcjonalnie)</span>
                    </label>
                    <input
                        v-model="addRoomForm.custom_name"
                        type="text"
                        placeholder="np. Pokój dziecięcy"
                        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        :disabled="addRoomForm.processing"
                        class="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium text-sm disabled:opacity-50"
                    >
                        Dodaj
                    </button>
                    <button type="button" @click="addRoomOpen = false" class="text-sm text-slate-500 hover:text-slate-700">Anuluj</button>
                </div>
            </form>

            <p v-if="protocol.rooms.length === 0 && !addRoomOpen" class="mt-3 text-sm text-slate-500">
                Brak pomieszczeń. Dodaj pierwsze powyżej.
            </p>

            <div class="mt-4 space-y-4">
                <div v-for="room in protocol.rooms" :key="room.id" class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <div class="p-4 flex items-center justify-between gap-3 bg-slate-50 border-b border-slate-100">
                        <form
                            v-if="editingRoomId === room.id"
                            @submit.prevent="submitEditRoom(room)"
                            class="flex-1 flex items-center gap-2"
                        >
                            <input
                                v-model="editRoomForm.custom_name"
                                type="text"
                                placeholder="Nazwa własna"
                                class="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            />
                            <button type="submit" :disabled="editRoomForm.processing" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium whitespace-nowrap">
                                Zapisz
                            </button>
                            <button type="button" @click="cancelEditRoom" class="text-sm text-slate-500 hover:text-slate-700 whitespace-nowrap">
                                Anuluj
                            </button>
                        </form>
                        <template v-else>
                            <p class="font-medium text-slate-900">{{ room.display_name }}</p>
                            <div v-if="protocol.is_draft" class="flex items-center gap-3 shrink-0">
                                <button type="button" @click="startEditRoom(room)" class="text-sm text-slate-500 hover:text-slate-700">Edytuj</button>
                                <button type="button" @click="deleteRoom(room)" class="text-sm text-red-600 hover:text-red-700">Usuń</button>
                            </div>
                        </template>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <div v-for="item in room.items" :key="item.id" class="p-4">
                            <form v-if="editingItemId === item.id" @submit.prevent="submitEditItem(room, item)" class="space-y-2">
                                <div class="grid grid-cols-2 gap-2">
                                    <select
                                        v-model="editItemForm.condition_catalog_item_id"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                    >
                                        <option value="">Bez stanu</option>
                                        <option v-for="c in catalogs.condition_states" :key="c.id" :value="c.id">{{ c.label }}</option>
                                    </select>
                                    <input
                                        v-model.number="editItemForm.quantity"
                                        type="number"
                                        min="1"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                    />
                                </div>
                                <input
                                    v-model="editItemForm.custom_name"
                                    type="text"
                                    placeholder="Nazwa własna (opcjonalnie)"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                />
                                <div class="flex items-center gap-3">
                                    <button type="submit" :disabled="editItemForm.processing" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium">
                                        Zapisz
                                    </button>
                                    <button type="button" @click="cancelEditItem" class="text-sm text-slate-500 hover:text-slate-700">Anuluj</button>
                                </div>
                            </form>
                            <div v-else>
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">
                                            {{ item.display_name }}
                                            <span v-if="item.quantity > 1" class="text-slate-400 font-normal">&times;{{ item.quantity }}</span>
                                        </p>
                                        <p v-if="item.condition_name" class="text-xs text-slate-500">{{ item.condition_name }}</p>
                                    </div>
                                    <div v-if="protocol.is_draft" class="flex items-center gap-3 shrink-0">
                                        <button type="button" @click="startEditItem(item)" class="text-sm text-slate-500 hover:text-slate-700">Edytuj</button>
                                        <button type="button" @click="deleteItem(room, item)" class="text-sm text-red-600 hover:text-red-700">Usuń</button>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <div v-if="item.photos.length" class="flex flex-wrap gap-2">
                                        <div v-for="photo in item.photos" :key="photo.id" class="relative">
                                            <img
                                                :src="photo.url"
                                                :alt="photo.original_filename"
                                                class="w-16 h-16 object-cover rounded-lg border border-slate-200"
                                            />
                                            <button
                                                v-if="protocol.is_draft"
                                                type="button"
                                                @click="deletePhoto(room, item, photo)"
                                                title="Usuń zdjęcie"
                                                class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-600 text-white rounded-full text-xs leading-none flex items-center justify-center hover:bg-red-700"
                                            >
                                                &times;
                                            </button>
                                        </div>
                                    </div>

                                    <div v-if="protocol.is_draft" class="mt-2">
                                        <label class="inline-block text-xs text-emerald-600 hover:text-emerald-700 font-medium cursor-pointer">
                                            <span v-if="uploadingItemId === item.id">Przesyłanie...</span>
                                            <span v-else>+ Dodaj zdjęcie</span>
                                            <input
                                                type="file"
                                                accept="image/jpeg,image/png,image/webp,image/heic"
                                                class="hidden"
                                                :disabled="uploadingItemId === item.id"
                                                @change="onPhotoSelected($event, room, item)"
                                            />
                                        </label>
                                        <p v-if="photoErrorItemId === item.id && photoForm.errors.photo" class="mt-1 text-xs text-red-600">
                                            {{ photoForm.errors.photo }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p v-if="room.items.length === 0 && addItemRoomId !== room.id" class="p-4 text-sm text-slate-400">
                            Brak pozycji w tym pomieszczeniu.
                        </p>

                        <form v-if="addItemRoomId === room.id" @submit.prevent="submitAddItem(room)" class="p-4 bg-slate-50 space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <select
                                    v-model="addItemForm.catalog_item_id"
                                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                >
                                    <option v-for="t in catalogs.item_templates" :key="t.id" :value="t.id">{{ t.label }}</option>
                                </select>
                                <select
                                    v-model="addItemForm.condition_catalog_item_id"
                                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                >
                                    <option value="">Bez stanu</option>
                                    <option v-for="c in catalogs.condition_states" :key="c.id" :value="c.id">{{ c.label }}</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input
                                    v-model.number="addItemForm.quantity"
                                    type="number"
                                    min="1"
                                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                />
                                <input
                                    v-model="addItemForm.custom_name"
                                    type="text"
                                    placeholder="Nazwa własna (opcjonalnie)"
                                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                />
                            </div>
                            <p v-if="addItemForm.errors.catalog_item_id" class="text-sm text-red-600">{{ addItemForm.errors.catalog_item_id }}</p>
                            <div class="flex items-center gap-3">
                                <button
                                    type="submit"
                                    :disabled="addItemForm.processing"
                                    class="px-3.5 py-1.5 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium text-sm disabled:opacity-50"
                                >
                                    Dodaj pozycję
                                </button>
                                <button type="button" @click="cancelAddItem" class="text-sm text-slate-500 hover:text-slate-700">Anuluj</button>
                            </div>
                        </form>

                        <button
                            v-if="protocol.is_draft && addItemRoomId !== room.id"
                            type="button"
                            @click="openAddItem(room)"
                            class="w-full p-3 text-sm text-emerald-600 hover:text-emerald-700 font-medium text-left px-4"
                        >
                            + Dodaj pozycję
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
