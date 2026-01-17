<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    documents: {
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
    document: null,
    prompt_version: props.defaultPromptVersion,
    model: props.defaultModel,
});

const submit = () => {
    form.post('/documents', {
        forceFormData: true,
    });
};

const onFileChange = (event) => {
    const [file] = event.target.files;
    form.document = file ?? null;
};

const deleteDocument = (doc) => {
    if (!doc?.id) return;
    const confirmed = window.confirm(`Delete "${doc.original_name}"?`);
    if (!confirmed) return;
    router.delete(`/documents/${doc.id}`);
};
</script>

<template>
    <AppLayout>
        <div class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr]">
            <section
                class="rounded-3xl border border-slate-900/10 bg-white/70 p-8 shadow-[0_20px_50px_-35px_rgba(15,23,42,0.35)] backdrop-blur"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.35em] text-sky-700">
                    Upload &amp; Analyze
                </p>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">
                    DocuSnitch practices PDF-Driven Development.
                </h2>
                <p class="mt-3 text-sm text-slate-600">
DocuSnitch reads your API documentation and tells you what it’s actually saying  endpoints, fields, webhooks, and all the lies in between.
                </p>

                <form class="mt-6 grid gap-4" @submit.prevent="submit">
                    <label
                        class="flex cursor-pointer flex-col gap-2 rounded-2xl border border-dashed border-slate-300 bg-slate-50/80 px-5 py-6 text-sm text-slate-600 transition hover:border-sky-400 hover:text-slate-900"
                    >
                        <span class="text-sm font-medium text-slate-700">
                            PDF file
                        </span>
                        <input type="file" class="hidden" accept="application/pdf" @change="onFileChange" />
                        <span v-if="form.document" class="text-xs text-slate-500">
                            Selected: {{ form.document.name }}
                        </span>
                        <span v-else class="text-xs text-slate-500">
                            Drag a file here or click to browse.
                        </span>
                    </label>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                            Prompt version
                            <select
                                v-model="form.prompt_version"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            >
                                <option v-for="version in promptVersions" :key="version" :value="version">
                                    {{ version }}
                                </option>
                            </select>
                        </label>
                        <label class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                            Model
                            <input
                                v-model="form.model"
                                type="text"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            />
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-between rounded-2xl bg-sky-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-sky-600/30 transition hover:bg-sky-500 disabled:cursor-not-allowed disabled:bg-slate-300"
                        :disabled="form.processing || !form.document"
                    >
                        <span>Start analysis</span>
                        <span v-if="form.processing" class="text-xs">Uploading...</span>
                    </button>

                    <p v-if="form.errors.document" class="text-xs text-rose-600">
                        {{ form.errors.document }}
                    </p>
                </form>
            </section>

            <section class="rounded-3xl border border-slate-900/10 bg-white/70 p-8 backdrop-blur">
                <h3 class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-500">
                    Recent uploads
                </h3>
                <div v-if="documents.length" class="mt-6 space-y-4">
                    <div
                        v-for="doc in documents"
                        :key="doc.id"
                        class="rounded-2xl border border-slate-200 bg-white px-4 py-4"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ doc.original_name }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    Status: <span class="font-medium">{{ doc.status }}</span>
                                </p>
                                <p v-if="doc.latest_analysis" class="text-xs text-slate-500">
                                    Model: {{ doc.latest_analysis.model }} · Prompt
                                    {{ doc.latest_analysis.prompt_version }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="`/documents/${doc.id}`"
                                    class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-sky-400 hover:text-sky-700"
                                >
                                    View
                                </Link>
                                <button
                                    type="button"
                                    class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 hover:border-rose-400 hover:text-rose-700"
                                    @click="deleteDocument(doc)"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="mt-6 text-sm text-slate-500">
                    No documents uploaded yet. Drop a PDF to begin.
                </p>
            </section>
        </div>
    </AppLayout>
</template>
