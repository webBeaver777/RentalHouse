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
            </dl>

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
                            <div v-else class="flex items-center justify-between gap-3">
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
