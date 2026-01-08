<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },
    analysis: {
        type: Object,
        default: null,
    },
    analyses: {
        type: Array,
        default: () => [],
    },
    promptVersions: {
        type: Array,
        default: () => [],
    },
    defaultPromptVersion: {
        type: String,
        default: 'v1',
    },
    defaultModel: {
        type: String,
        default: 'gpt-4o-mini',
    },
});

const form = useForm({
    prompt_version: props.defaultPromptVersion,
    model: props.defaultModel,
});

const rerun = () => {
    form.post(`/documents/${props.document.id}/analyze`);
};

const result = computed(() => props.analysis?.result ?? null);
const fields = computed(() => {
    if (!result.value?.fields) return [];
    return Object.entries(result.value.fields).map(([key, value]) => ({
        key,
        label: key.replace(/_/g, ' '),
        ...value,
    })).filter((field) => !['room_number', 'room_type', 'rate'].includes(field.key));
});
const credentials = computed(() => result.value?.credentials ?? []);
const credentialHint = computed(() => ({
    available: result.value?.credentials_available ?? false,
    types: result.value?.credential_types ?? [],
}));
const getReservationsEndpoints = computed(() => result.value?.get_reservations_endpoints ?? []);
const getReservationsEndpointNames = computed(
    () => result.value?.get_reservations_endpoint_names ?? []
);
const keyFiltersTitle = computed(() => {
    if (getReservationsEndpoints.value.length) {
        return `Key filters — ${getReservationsEndpoints.value.join(', ')}`;
    }
    if (getReservationsEndpointNames.value.length) {
        return `Key filters — ${getReservationsEndpointNames.value.join(', ')}`;
    }
    return 'Key filters';
});
const getReservationsAvailableFilters = computed(
    () => result.value?.get_reservations_available_filters ?? []
);
const reservationFilterFlags = computed(() => {
    const filters = result.value?.get_reservations_filters ?? {};
    const entries = [
        { key: 'check_in_date', label: 'Check-in date' },
        { key: 'check_out_date', label: 'Check-out date' },
        { key: 'status', label: 'Status' },
    ];
    return entries.map((entry) => {
        const filter = filters?.[entry.key] ?? {};
        const sourceFields = Array.isArray(filter.source_fields) ? filter.source_fields : [];
        return {
            ...entry,
            available: filter.available ?? false,
            docLabel: sourceFields.join(', '),
        };
    });
});
const showRawCredentials = ref(false);
const canShowRawCredentials = computed(() =>
    credentials.value.some(
        (credential) => typeof credential.raw_value === 'string' && credential.raw_value.length
    )
);
const showTicketModal = ref(false);
const ticketForm = reactive({
    summary: '',
    description: '',
    include_result: true,
});
const ticketState = reactive({
    generating: false,
    submitting: false,
    error: null,
    success: null,
});
const ticketIssue = ref(null);
const defaultTicketSummary = computed(() => {
    const pmsName = result.value?.pms_name?.trim();
    if (pmsName) {
        return `PMS analysis: ${pmsName}`;
    }
    return `PMS analysis: ${props.document.original_name}`;
});
const pmsResultJson = computed(() =>
    result.value ? JSON.stringify(result.value, null, 2) : ''
);

const polling = {
    interval: null,
};

const shouldPoll = computed(() => {
    const status = props.analysis?.status;
    return status === 'queued' || status === 'processing';
});

onMounted(() => {
    if (!shouldPoll.value) return;
    polling.interval = setInterval(() => {
        router.reload({ only: ['analysis', 'analyses', 'document'] });
    }, 3000);
});

watch(
    () => props.analysis?.status,
    (status) => {
        const active = status === 'queued' || status === 'processing';
        if (!active && polling.interval) {
            clearInterval(polling.interval);
            polling.interval = null;
            return;
        }

        if (active && !polling.interval) {
            polling.interval = setInterval(() => {
                router.reload({ only: ['analysis', 'analyses', 'document'] });
            }, 3000);
        }
    }
);

onBeforeUnmount(() => {
    if (polling.interval) {
        clearInterval(polling.interval);
    }
});

const openTicketModal = () => {
    if (!result.value || !props.analysis?.id) return;
    showTicketModal.value = true;
    ticketState.error = null;
    ticketState.success = null;
    ticketIssue.value = null;
    ticketForm.summary = defaultTicketSummary.value;
    ticketForm.description = '';
    ticketForm.include_result = true;
};

const closeTicketModal = () => {
    showTicketModal.value = false;
};

const generateTicketDescription = async () => {
    if (!props.analysis?.id) return;
    ticketState.generating = true;
    ticketState.error = null;

    try {
        const response = await window.axios.post(
            `/analyses/${props.analysis.id}/youtrack/description`,
            {
                summary: ticketForm.summary,
            }
        );
        ticketForm.description = response?.data?.description ?? '';
    } catch (error) {
        ticketState.error =
            error?.response?.data?.message ?? 'Failed to generate description.';
    } finally {
        ticketState.generating = false;
    }
};

const createYouTrackTicket = async () => {
    if (!props.analysis?.id) return;
    ticketState.submitting = true;
    ticketState.error = null;
    ticketState.success = null;

    try {
        const response = await window.axios.post(
            `/analyses/${props.analysis.id}/youtrack`,
            {
                summary: ticketForm.summary,
                description: ticketForm.description,
                include_result: ticketForm.include_result,
            }
        );
        ticketIssue.value = response?.data ?? null;
        const issueId = response?.data?.issue_idReadable || response?.data?.issue_id;
        ticketState.success = issueId
            ? `Created ${issueId}.`
            : 'Ticket created.';
    } catch (error) {
        ticketState.error =
            error?.response?.data?.message ?? 'Failed to create YouTrack ticket.';
    } finally {
        ticketState.submitting = false;
    }
};
</script>

<template>
    <AppLayout>
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-slate-500">
                    Document detail
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ document.original_name }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Status: <span class="font-semibold">{{ document.status }}</span>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <Link
                    href="/"
                    class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-emerald-400 hover:text-emerald-700"
                >
                    Back to uploads
                </Link>
                <a
                    v-if="analysis?.result"
                    :href="`/analyses/${analysis.id}/download`"
                    class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white"
                >
                    Download JSON
                </a>
                <a
                    v-if="analysis?.result"
                    :href="`/analyses/${analysis.id}/download/pdf`"
                    class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-emerald-400 hover:text-emerald-700"
                >
                    Download PDF
                </a>
                <button
                    type="button"
                    class="rounded-full bg-amber-500 px-4 py-2 text-xs font-semibold text-white shadow-lg shadow-amber-500/30 hover:bg-amber-400"
                    :disabled="!analysis?.result"
                    @click="openTicketModal"
                >
                    Create YouTrack ticket
                </button>
            </div>
        </div>

        <section
            class="rounded-3xl border border-slate-900/10 bg-white/70 p-6 shadow-[0_18px_45px_-35px_rgba(15,23,42,0.35)] backdrop-blur"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Analysis run</h3>
                    <p v-if="analysis" class="text-xs text-slate-500">
                        Prompt {{ analysis.prompt_version }} · {{ analysis.model }}
                    </p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="rerun">
                        <label class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-500">
                            Prompt
                            <select
                                v-model="form.prompt_version"
                                class="mt-2 w-36 rounded-full border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700"
                            >
                                <option v-for="version in promptVersions" :key="version" :value="version">
                                    {{ version }}
                                </option>
                            </select>
                        </label>
                        <label class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-500">
                            Model
                            <input
                                v-model="form.model"
                                class="mt-2 w-40 rounded-full border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700"
                            />
                        </label>
                        <button
                            type="submit"
                            class="rounded-full bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-lg shadow-emerald-600/30"
                            :disabled="form.processing"
                        >
                            Re-run
                        </button>
                    </form>
                    <Link
                        href="/"
                        class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-emerald-400 hover:text-emerald-700"
                    >
                        Back to home
                    </Link>
                </div>
            </div>

            <div v-if="analysis" class="mt-6">
                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>Status: {{ analysis.status }}</span>
                    <span>{{ analysis.progress }}%</span>
                </div>
                <div class="mt-2 h-2 w-full rounded-full bg-slate-200">
                    <div
                        class="h-2 rounded-full bg-emerald-500 transition-all"
                        :style="{ width: `${analysis.progress}%` }"
                    ></div>
                </div>
                <p v-if="analysis.error_message" class="mt-2 text-xs text-rose-600">
                    {{ analysis.error_message }}
                </p>
            </div>
        </section>

        <section class="mt-8 grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
            <div class="rounded-3xl border border-slate-900/10 bg-white/70 p-6 backdrop-blur">
                <h3 class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                    Field coverage
                </h3>
                <div v-if="result" class="mt-5 space-y-3">
                    <div
                        v-for="field in fields"
                        :key="field.key"
                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3"
                    >
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold capitalize text-slate-800">
                                {{ field.label }}
                            </p>
                            <span
                                class="rounded-full px-3 py-1 text-[0.65rem] font-semibold uppercase"
                                :class="field.available ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                            >
                                {{ field.available ? 'available' : 'missing' }}
                            </span>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                            <span>Confidence</span>
                            <span>{{ Math.round(field.confidence * 100) }}%</span>
                        </div>
                        <div class="mt-2 h-1.5 w-full rounded-full bg-slate-200">
                            <div
                                class="h-1.5 rounded-full bg-slate-900"
                                :style="{ width: `${Math.round(field.confidence * 100)}%` }"
                            ></div>
                        </div>
                        <p v-if="field.source_fields?.length" class="mt-2 text-xs text-slate-500">
                            Source fields: {{ field.source_fields.join(', ') }}
                        </p>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-slate-500">
                    Analysis results will appear here once processing completes.
                </p>
            </div>

            <div class="space-y-6">
                <div class="rounded-3xl border border-slate-900/10 bg-white/70 p-6 backdrop-blur">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                        Capability snapshot
                    </h3>
                    <div v-if="result" class="mt-4 space-y-3">
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3">
                            <p class="text-sm text-slate-700">Get Reservations endpoint</p>
                            <span class="text-sm font-semibold text-slate-900">
                                {{ result.has_get_reservations_endpoint ? 'Yes' : 'No' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3">
                            <p class="text-sm text-slate-700">Webhook support</p>
                            <span class="text-sm font-semibold text-slate-900">
                                {{ result.supports_webhooks ? 'Yes' : 'No' }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">
                        Waiting on AI analysis.
                    </p>
                </div>

                <div class="rounded-3xl border border-slate-900/10 bg-white/70 p-6 backdrop-blur">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                        Reservations
                    </h3>
                    <div v-if="result" class="mt-4 space-y-4">
                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                Key filters
                            </p>
                            <div class="mt-3 space-y-2">
                                <div
                                    v-for="filter in reservationFilterFlags"
                                    :key="filter.key"
                                    class="flex items-center justify-between gap-4"
                                >
                                    <div class="text-sm text-slate-700">
                                        <span>{{ filter.label }}</span>
                                        <span v-if="filter.docLabel" class="ml-2 text-xs text-slate-400">
                                            ({{ filter.docLabel }})
                                        </span>
                                    </div>
                                    <span
                                        class="rounded-full px-3 py-1 text-[0.65rem] font-semibold uppercase"
                                        :class="filter.available ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                    >
                                        {{ filter.available ? 'available' : 'missing' }}
                                    </span>
                                </div>
                            </div>
                            <p v-if="getReservationsAvailableFilters.length" class="mt-3 text-xs text-slate-500">
                                All filters: {{ getReservationsAvailableFilters.join(', ') }}
                            </p>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">
                        Waiting on AI analysis.
                    </p>
                </div>

                <div class="rounded-3xl border border-slate-900/10 bg-white/70 p-6 backdrop-blur">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                        Reservation statuses
                    </h3>
                    <div v-if="result?.reservation_statuses?.length" class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="status in result.reservation_statuses"
                            :key="status"
                            class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800"
                        >
                            {{ status }}
                        </span>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">
                        No statuses detected yet.
                    </p>
                </div>

                <div class="rounded-3xl border border-slate-900/10 bg-white/70 p-6 backdrop-blur">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                            Credentials detected
                        </h3>
                        <button
                            v-if="credentials.length && canShowRawCredentials"
                            type="button"
                            class="rounded-full border border-slate-200 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-600 hover:border-emerald-400 hover:text-emerald-700"
                            @click="showRawCredentials = !showRawCredentials"
                        >
                            {{ showRawCredentials ? 'Hide values' : 'Show values' }}
                        </button>
                    </div>
                    <p v-if="result" class="mt-2 text-xs text-slate-500">
                        Docs mention credentials: {{ credentialHint.available ? 'Yes' : 'No' }}
                        <span v-if="credentialHint.types.length"> · {{ credentialHint.types.join(', ') }}</span>
                    </p>
                    <div v-if="credentials.length" class="mt-4 space-y-3">
                        <div
                            v-for="credential in credentials"
                            :key="`${credential.type}-${credential.value}`"
                            class="rounded-2xl border border-slate-200 bg-white px-4 py-3"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ credential.label }}
                                </p>
                                <span
                                    class="rounded-full bg-slate-100 px-3 py-1 text-[0.65rem] font-semibold uppercase text-slate-500"
                                >
                                    {{ Math.round((credential.confidence ?? 0) * 100) }}%
                                </span>
                            </div>
                            <p class="mt-2 text-sm font-mono text-slate-700">
                                {{ showRawCredentials && credential.raw_value ? credential.raw_value : credential.value }}
                            </p>
                            <p v-if="credential.source_line" class="mt-2 text-xs text-slate-500">
                                Source: {{ credential.source_line }}
                            </p>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">
                        <span v-if="credentialHint.available">
                            Docs mention credentials, but no concrete values were detected in the PDF text.
                        </span>
                        <span v-else>No credentials detected in the PDF text.</span>
                    </p>
                </div>

                <div class="rounded-3xl border border-slate-900/10 bg-white/70 p-6 backdrop-blur">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                        Notes
                    </h3>
                    <ul v-if="result?.notes?.length" class="mt-4 space-y-2 text-sm text-slate-600">
                        <li v-for="note in result.notes" :key="note" class="rounded-2xl bg-white px-4 py-3">
                            {{ note }}
                        </li>
                    </ul>
                    <p v-else class="mt-4 text-sm text-slate-500">
                        Notes will show up when the model provides context.
                    </p>
                </div>
            </div>
        </section>

        <section class="mt-10 rounded-3xl border border-slate-900/10 bg-white/70 p-6 backdrop-blur">
            <h3 class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                Analysis history
            </h3>
            <div v-if="analyses.length" class="mt-4 grid gap-3">
                <div
                    v-for="entry in analyses"
                    :key="entry.id"
                    class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600"
                >
                    <div>
                        <p class="font-semibold text-slate-800">
                            {{ entry.prompt_version }} · {{ entry.model }}
                        </p>
                        <p class="text-xs text-slate-500">
                            Status: {{ entry.status }} · Progress {{ entry.progress }}%
                        </p>
                    </div>
                    <a
                        v-if="entry.result"
                        :href="`/analyses/${entry.id}/download`"
                        class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-emerald-400 hover:text-emerald-700"
                    >
                        JSON
                    </a>
                    <a
                        v-if="entry.result"
                        :href="`/analyses/${entry.id}/download/pdf`"
                        class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-emerald-400 hover:text-emerald-700"
                    >
                        PDF
                    </a>
                </div>
            </div>
            <p v-else class="mt-4 text-sm text-slate-500">
                No analyses recorded yet.
            </p>
        </section>

        <div
            v-if="showTicketModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
        >
            <div class="w-full max-w-4xl rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-500">
                            YouTrack
                        </p>
                        <h3 class="mt-2 text-lg font-semibold text-slate-900">
                            Create ticket
                        </h3>
                    </div>
                    <button
                        type="button"
                        class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-rose-400 hover:text-rose-600"
                        @click="closeTicketModal"
                    >
                        Close
                    </button>
                </div>

                <div class="mt-6 space-y-4">
                    <label class="block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                        Summary
                        <input
                            v-model="ticketForm.summary"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700"
                            placeholder="PMS analysis ticket"
                        />
                    </label>

                    <label class="block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                        Description
                        <textarea
                            v-model="ticketForm.description"
                            rows="6"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700"
                            placeholder="Add details for the ticket."
                        ></textarea>
                    </label>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                            <input
                                v-model="ticketForm.include_result"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-emerald-600"
                            />
                            Include PMS result in ticket
                        </label>
                        <button
                            type="button"
                            class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-emerald-400 hover:text-emerald-700"
                            :disabled="ticketState.generating"
                            @click="generateTicketDescription"
                        >
                            {{ ticketState.generating ? 'Generating...' : 'Generate with OpenAI' }}
                        </button>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                            PMS result
                        </p>
                        <pre class="mt-3 max-h-64 overflow-auto rounded-xl bg-white p-4 text-xs text-slate-700">{{ pmsResultJson }}</pre>
                    </div>

                    <p v-if="ticketState.error" class="text-sm text-rose-600">
                        {{ ticketState.error }}
                    </p>
                    <div v-if="ticketIssue" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        <p>{{ ticketState.success }}</p>
                        <a
                            v-if="ticketIssue.issue_url"
                            :href="ticketIssue.issue_url"
                            class="mt-2 inline-flex text-xs font-semibold text-emerald-700 underline"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Open in YouTrack
                        </a>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-slate-300"
                            @click="closeTicketModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="rounded-full bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-lg shadow-emerald-600/30"
                            :disabled="ticketState.submitting"
                            @click="createYouTrackTicket"
                        >
                            {{ ticketState.submitting ? 'Creating...' : 'Create ticket' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
