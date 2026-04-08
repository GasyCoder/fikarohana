<script setup>
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpenText,
    Building2,
    CalendarRange,
    ChevronLeft,
    ChevronRight,
    Command,
    ExternalLink,
    RefreshCw,
    Search,
    Sparkles,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

import FikarohanaLogo from '../../Components/FikarohanaLogo.vue';
import ResourceCard from '../../Components/ResourceCard.vue';
import MarketingLayout from '../../Layouts/MarketingLayout.vue';

defineOptions({
    layout: (h, page) => h(MarketingLayout, {}, () => page),
});

const props = defineProps({
    appName: {
        type: String,
        default: 'Fikarohana',
    },
    endpoint: {
        type: String,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    resources: {
        type: Object,
        required: true,
    },
    options: {
        type: Object,
        required: true,
    },
});

const form = reactive({
    search: props.filters.search ?? '',
    domains: [...(props.filters.domains ?? [])],
    universities: [...(props.filters.universities ?? [])],
    types: [...(props.filters.types ?? [])],
    sources: [...(props.filters.sources ?? [])],
    years: {
        from: props.filters.years?.from ?? '',
        to: props.filters.years?.to ?? '',
    },
});

const searchInput = ref(null);
const isLoading = ref(false);
const skeletonItems = Array.from({ length: 6 }, (_, index) => index);
let debounceTimer = null;
let syncingFromServer = false;
let removeStartListener = null;
let removeFinishListener = null;
let keydownHandler = null;

const typeLabels = {
    thesis: 'Thèses',
    article: 'Articles',
};

watch(
    () => props.filters,
    (filters) => {
        syncingFromServer = true;
        form.search = filters.search ?? '';
        form.domains = [...(filters.domains ?? [])];
        form.universities = [...(filters.universities ?? [])];
        form.types = [...(filters.types ?? [])];
        form.sources = [...(filters.sources ?? [])];
        form.years.from = filters.years?.from ?? '';
        form.years.to = filters.years?.to ?? '';
        syncingFromServer = false;
    },
    { deep: true },
);

watch(
    form,
    () => {
        if (syncingFromServer) {
            return;
        }

        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => {
            router.get(
                props.endpoint,
                {
                    search: form.search || undefined,
                    domains: form.domains.length ? form.domains : undefined,
                    universities: form.universities.length ? form.universities : undefined,
                    types: form.types.length ? form.types : undefined,
                    sources: form.sources.length ? form.sources : undefined,
                    years:
                        form.years.from || form.years.to
                            ? {
                                  from: form.years.from || undefined,
                                  to: form.years.to || undefined,
                              }
                            : undefined,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    only: ['filters', 'resources', 'options'],
                },
            );
        }, 220);
    },
    { deep: true },
);

onMounted(() => {
    keydownHandler = (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            nextTick(() => searchInput.value?.focus());
        }
    };

    window.addEventListener('keydown', keydownHandler);
    removeStartListener = router.on('start', () => {
        isLoading.value = true;
    });
    removeFinishListener = router.on('finish', () => {
        isLoading.value = false;
    });
});

onUnmounted(() => {
    if (keydownHandler) {
        window.removeEventListener('keydown', keydownHandler);
    }

    window.clearTimeout(debounceTimer);
    removeStartListener?.();
    removeFinishListener?.();
});

const activeFilterCount = computed(
    () =>
        form.domains.length +
        form.universities.length +
        form.types.length +
        form.sources.length +
        Number(Boolean(form.search)) +
        Number(Boolean(form.years.from || form.years.to)),
);

const activeFilters = computed(() => [
    ...form.domains.map((value) => ({ key: 'domains', value, label: value })),
    ...form.universities.map((value) => ({ key: 'universities', value, label: value })),
    ...form.types.map((value) => ({
        key: 'types',
        value,
        label: typeLabels[value] ?? value,
    })),
    ...form.sources.map((value) => ({ key: 'sources', value, label: value })),
    ...(form.years.from ? [{ key: 'years.from', value: form.years.from, label: `Depuis ${form.years.from}` }] : []),
    ...(form.years.to ? [{ key: 'years.to', value: form.years.to, label: `Jusqu’à ${form.years.to}` }] : []),
]);

const resultLabel = computed(() =>
    form.search?.trim() ? `Résultats pour “${form.search.trim()}”` : 'Toutes les ressources disponibles',
);

const featuredStats = computed(() => [
    { label: 'Documents indexés', value: props.resources.total },
    { label: 'Sources suivies', value: props.options.sources.length },
    { label: 'Institutions', value: props.options.universities.length },
]);

const visibleSources = computed(() => props.options.sources.slice(0, 6));

const visibleTypes = computed(() =>
    props.options.types.map((type) => ({
        ...type,
        label: typeLabels[type.type] ?? type.type,
    })),
);

const externalLinks = computed(() => {
    const query = form.search?.trim();

    if (!query) {
        return [];
    }

    const encoded = encodeURIComponent(query);

    return [
        { label: 'Google Scholar', href: `https://scholar.google.com/scholar?q=${encoded}` },
        { label: 'HAL', href: `https://hal.science/search/index/?q=${encoded}` },
        { label: 'OpenAlex', href: `https://explore.openalex.org/works?filter=default.search:${encoded}` },
    ];
});

const resetFilters = () => {
    form.search = '';
    form.domains = [];
    form.universities = [];
    form.types = [];
    form.sources = [];
    form.years.from = '';
    form.years.to = '';
};

const toggleFilter = (key, value) => {
    const current = new Set(form[key]);

    if (current.has(value)) {
        current.delete(value);
    } else {
        current.add(value);
    }

    form[key] = [...current];
};

const removeFilter = (filter) => {
    if (['domains', 'universities', 'types', 'sources'].includes(filter.key)) {
        toggleFilter(filter.key, filter.value);
        return;
    }

    if (filter.key === 'years.from') {
        form.years.from = '';
    }

    if (filter.key === 'years.to') {
        form.years.to = '';
    }
};

const visitLink = (url) => {
    if (!url) {
        return;
    }

    router.visit(url, { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <Head title="Bibliothèque" />

    <div class="relative min-h-screen overflow-hidden">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-80 bg-[radial-gradient(circle_at_top,_rgba(79,70,229,0.10),_transparent_55%)]" />
        <div class="pointer-events-none absolute right-0 top-40 h-72 w-72 rounded-full bg-emerald-400/10 blur-3xl" />

        <div class="mx-auto max-w-[1536px] px-4 py-8 sm:px-6 lg:px-8">
            <div class="grid gap-8 xl:grid-cols-[280px_minmax(0,1fr)]">
                <aside class="h-full xl:sticky xl:top-24 xl:self-start">
                    <div class="surface-panel overflow-hidden rounded-[32px] border border-slate-100 shadow-sm">
                        <div class="border-b border-slate-100 px-7 py-7">
                            <div class="flex items-center gap-4">
                                <FikarohanaLogo />
                                <div>
                                    <p class="text-base font-semibold tracking-tight text-slate-950">Fikarohana</p>
                                    <p class="mt-1 text-sm leading-6 text-slate-500">
                                        Bibliographie publique pour retrouver plus vite les travaux de recherche malagasy.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="border-b border-slate-100 px-7 py-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-950">Facettes</p>
                                    <p class="mt-1 text-sm text-slate-500">Affinez sans perdre le fil de votre recherche.</p>
                                </div>
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500">
                                    {{ activeFilterCount }} actif(s)
                                </span>
                            </div>
                        </div>

                        <div class="px-7 py-7">
                            <div class="space-y-8">
                                <section>
                                    <div class="flex items-center gap-2">
                                        <BookOpenText :size="16" :stroke-width="1.5" class="text-slate-400" />
                                        <h2 class="text-sm font-semibold text-slate-950">Domaines</h2>
                                    </div>
                                    <div class="scrollbar-soft mt-4 max-h-72 space-y-2 overflow-y-auto pr-1">
                                        <label
                                            v-for="domain in props.options.domains"
                                            :key="domain.domain"
                                            class="flex items-center justify-between gap-3 rounded-2xl px-3 py-2.5 transition hover:bg-slate-50"
                                        >
                                            <span class="flex items-center gap-3 text-sm text-slate-700">
                                                <input
                                                    :checked="form.domains.includes(domain.domain)"
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                    @change="toggleFilter('domains', domain.domain)"
                                                >
                                                {{ domain.domain }}
                                            </span>
                                            <span class="text-xs text-slate-400">{{ domain.total }}</span>
                                        </label>
                                    </div>
                                </section>

                                <section>
                                    <div class="flex items-center gap-2">
                                        <Building2 :size="16" :stroke-width="1.5" class="text-slate-400" />
                                        <h2 class="text-sm font-semibold text-slate-950">Institutions</h2>
                                    </div>
                                    <div v-if="props.options.universities.length" class="scrollbar-soft mt-4 max-h-72 space-y-2 overflow-y-auto pr-1">
                                        <label
                                            v-for="university in props.options.universities"
                                            :key="university.institution"
                                            class="flex items-center justify-between gap-3 rounded-2xl px-3 py-2.5 transition hover:bg-slate-50"
                                        >
                                            <span class="flex items-center gap-3 text-sm text-slate-700">
                                                <input
                                                    :checked="form.universities.includes(university.institution)"
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                    @change="toggleFilter('universities', university.institution)"
                                                >
                                                {{ university.institution }}
                                            </span>
                                            <span class="text-xs text-slate-400">{{ university.total }}</span>
                                        </label>
                                    </div>
                                    <p v-else class="mt-4 text-sm leading-6 text-slate-400">
                                        Les institutions apparaîtront ici au fur et à mesure de l’indexation.
                                    </p>
                                </section>

                                <section>
                                    <div class="flex items-center gap-2">
                                        <CalendarRange :size="16" :stroke-width="1.5" class="text-slate-400" />
                                        <h2 class="text-sm font-semibold text-slate-950">Années</h2>
                                    </div>
                                    <div class="mt-4 grid grid-cols-2 gap-3">
                                        <label class="space-y-2">
                                            <span class="text-xs font-medium uppercase tracking-[0.12em] text-slate-400">Depuis</span>
                                            <input
                                                v-model="form.years.from"
                                                type="number"
                                                inputmode="numeric"
                                                :min="props.options.years.min ?? undefined"
                                                :max="props.options.years.max ?? undefined"
                                                placeholder="2018"
                                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-200 focus:ring-4 focus:ring-indigo-50"
                                            >
                                        </label>
                                        <label class="space-y-2">
                                            <span class="text-xs font-medium uppercase tracking-[0.12em] text-slate-400">Jusqu’à</span>
                                            <input
                                                v-model="form.years.to"
                                                type="number"
                                                inputmode="numeric"
                                                :min="props.options.years.min ?? undefined"
                                                :max="props.options.years.max ?? undefined"
                                                placeholder="2026"
                                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-200 focus:ring-4 focus:ring-indigo-50"
                                            >
                                        </label>
                                    </div>
                                    <p class="mt-3 text-sm text-slate-400">
                                        Couverture indexée:
                                        {{ props.options.years.min ?? '...' }} à {{ props.options.years.max ?? '...' }}
                                    </p>
                                </section>
                            </div>

                            <button
                                type="button"
                                class="mt-8 inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-white"
                                @click="resetFilters"
                            >
                                <RefreshCw :size="16" :stroke-width="1.5" />
                                Réinitialiser les filtres
                            </button>
                        </div>
                    </div>
                </aside>

                <main class="min-w-0">
                    <section class="surface-panel rounded-[32px] border border-slate-100 px-6 py-8 shadow-sm sm:px-8">
                        <div class="flex flex-col gap-8">
                            <div class="max-w-4xl">
                                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium uppercase tracking-[0.14em] text-slate-500">
                                    <Sparkles :size="14" :stroke-width="1.5" />
                                    Plateforme publique et gratuite
                                </span>
                                <h1 class="mt-5 text-3xl font-semibold tracking-tight text-slate-950 lg:text-5xl">
                                    Trouver rapidement les mémoires, thèses, articles et notices utiles aux étudiants et chercheurs malagasy.
                                </h1>
                                <p class="mt-4 max-w-3xl text-base leading-8 text-slate-500">
                                    Cherchez par sujet, auteur, institution ou source. Fikarohana centralise des liens académiques publics sans abonnement ni tarification.
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div
                                    v-for="stat in featuredStats"
                                    :key="stat.label"
                                    class="rounded-[28px] border border-slate-100 bg-white px-5 py-5 shadow-sm"
                                >
                                    <p class="text-2xl font-semibold tracking-tight text-slate-950">{{ stat.value }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ stat.label }}</p>
                                </div>
                            </div>

                            <div class="mx-auto w-full max-w-4xl rounded-[30px] border border-slate-100 bg-white p-3 shadow-sm">
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-5 top-1/2 -translate-y-1/2 text-slate-400" :size="20" :stroke-width="1.5" />
                                    <input
                                        ref="searchInput"
                                        v-model="form.search"
                                        type="search"
                                        placeholder="Chercher un auteur, un titre, une institution, une thématique"
                                        class="w-full rounded-[24px] border border-transparent bg-slate-50 py-5 pl-14 pr-28 text-base text-slate-950 outline-none transition focus:border-indigo-200 focus:bg-white focus:ring-4 focus:ring-indigo-50"
                                    >
                                    <div class="absolute right-4 top-1/2 hidden -translate-y-1/2 items-center gap-1 rounded-2xl border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-400 sm:flex">
                                        <Command :size="13" :stroke-width="1.5" />
                                        K
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col gap-5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-slate-500">Types</span>
                                    <button
                                        v-for="type in visibleTypes"
                                        :key="type.type"
                                        type="button"
                                        class="inline-flex items-center rounded-full border px-3.5 py-2 text-sm font-medium transition"
                                        :class="
                                            form.types.includes(type.type)
                                                ? 'border-indigo-200 bg-indigo-50 text-indigo-700'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50'
                                        "
                                        @click="toggleFilter('types', type.type)"
                                    >
                                        {{ type.label }}
                                    </button>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-slate-500">Sources</span>
                                    <button
                                        v-for="source in visibleSources"
                                        :key="source.source_name"
                                        type="button"
                                        class="inline-flex items-center rounded-full border px-3.5 py-2 text-sm font-medium transition"
                                        :class="
                                            form.sources.includes(source.source_name)
                                                ? 'border-slate-900 bg-slate-950 text-white'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50'
                                        "
                                        @click="toggleFilter('sources', source.source_name)"
                                    >
                                        {{ source.source_name }}
                                    </button>
                                </div>

                                <div v-if="activeFilters.length" class="flex flex-wrap gap-2">
                                    <button
                                        v-for="filter in activeFilters"
                                        :key="`${filter.key}-${filter.value}`"
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:border-indigo-100 hover:bg-indigo-50 hover:text-indigo-700"
                                        @click="removeFilter(filter)"
                                    >
                                        {{ filter.label }}
                                        <span aria-hidden="true">×</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="mt-8">
                        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-500">Bibliothèque</p>
                                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">
                                    {{ resultLabel }}
                                </h2>
                                <p class="mt-2 text-sm leading-6 text-slate-500">
                                    {{ props.resources.total }} résultat(s) indexé(s) dans des sources académiques publiques.
                                </p>
                            </div>

                            <div
                                v-if="props.resources.from && props.resources.to"
                                class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm text-slate-500 shadow-sm"
                            >
                                Affichage {{ props.resources.from }}–{{ props.resources.to }} sur {{ props.resources.total }}
                            </div>
                        </div>

                        <div
                            v-if="isLoading"
                            class="grid grid-cols-1 gap-8 lg:grid-cols-2 2xl:grid-cols-3"
                            aria-busy="true"
                            aria-live="polite"
                        >
                            <div
                                v-for="item in skeletonItems"
                                :key="item"
                                class="surface-panel animate-pulse rounded-[28px] border border-slate-100 p-8 shadow-sm"
                            >
                                <div class="h-5 w-32 rounded-full bg-slate-100" />
                                <div class="mt-6 h-6 w-4/5 rounded-full bg-slate-100" />
                                <div class="mt-3 h-6 w-3/5 rounded-full bg-slate-100" />
                                <div class="mt-6 flex items-center gap-3">
                                    <div class="h-12 w-12 rounded-2xl bg-slate-100" />
                                    <div class="flex-1 space-y-2">
                                        <div class="h-4 w-2/3 rounded-full bg-slate-100" />
                                        <div class="h-4 w-1/2 rounded-full bg-slate-100" />
                                    </div>
                                </div>
                                <div class="mt-6 h-18 rounded-[24px] bg-slate-100" />
                                <div class="mt-6 flex justify-between border-t border-slate-100 pt-5">
                                    <div class="h-4 w-24 rounded-full bg-slate-100" />
                                    <div class="h-10 w-32 rounded-full bg-slate-100" />
                                </div>
                            </div>
                        </div>

                        <div
                            v-else-if="props.resources.data.length"
                            class="grid grid-cols-1 gap-8 lg:grid-cols-2 2xl:grid-cols-3"
                        >
                            <ResourceCard
                                v-for="resource in props.resources.data"
                                :key="resource.id"
                                :resource="resource"
                            />
                        </div>

                        <div
                            v-else
                            class="surface-panel rounded-[32px] border border-dashed border-slate-200 px-8 py-16 text-center shadow-sm"
                        >
                            <p class="text-xl font-semibold text-slate-950">Aucun document trouvé</p>
                            <p class="mx-auto mt-3 max-w-2xl text-sm leading-7 text-slate-500">
                                Essayez un autre terme, élargissez les années, ou lancez votre recherche sur une source externe si le document n’est pas encore indexé ici.
                            </p>

                            <div v-if="externalLinks.length" class="mt-7 flex flex-wrap justify-center gap-3">
                                <a
                                    v-for="link in externalLinks"
                                    :key="link.href"
                                    :href="link.href"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"
                                >
                                    Chercher sur {{ link.label }}
                                    <ExternalLink :size="15" :stroke-width="1.5" />
                                </a>
                            </div>
                        </div>

                        <div class="mt-8 flex flex-col gap-4 rounded-[28px] border border-slate-100 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-sm leading-6 text-slate-500">
                                Pagination discrète pour rester fluide, même avec plusieurs facettes actives.
                            </div>

                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!props.resources.prev_page_url"
                                    @click="visitLink(props.resources.prev_page_url)"
                                >
                                    <ChevronLeft :size="16" :stroke-width="1.5" />
                                    Précédent
                                </button>

                                <div class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-500">
                                    Page {{ props.resources.current_page }} / {{ props.resources.last_page }}
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-full bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!props.resources.next_page_url"
                                    @click="visitLink(props.resources.next_page_url)"
                                >
                                    Suivant
                                    <ChevronRight :size="16" :stroke-width="1.5" />
                                </button>
                            </div>
                        </div>
                    </section>

                    <footer class="mt-12 rounded-[32px] border border-slate-100 bg-white px-8 py-8 shadow-sm">
                        <div class="grid gap-8 md:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <p class="text-sm font-semibold text-slate-950">Fikarohana</p>
                                <p class="mt-3 text-sm leading-7 text-slate-400">
                                    Une mission simple: rendre les ressources académiques malagasy plus visibles, plus accessibles et plus faciles à retrouver.
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-950">Ressources</p>
                                <ul class="mt-3 space-y-2 text-sm text-slate-400">
                                    <li>HAL</li>
                                    <li>OpenAlex</li>
                                    <li>Horizon IRD</li>
                                    <li>Theses.fr</li>
                                </ul>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-950">Partenaires</p>
                                <ul class="mt-3 space-y-2 text-sm text-slate-400">
                                    <li>Université de Mahajanga</li>
                                    <li>Communauté scientifique malagasy</li>
                                    <li>Sources académiques ouvertes</li>
                                </ul>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-950">Légal</p>
                                <ul class="mt-3 space-y-2 text-sm text-slate-400">
                                    <li>Projet gratuit</li>
                                    <li>MIT License</li>
                                    <li>Liens vers les sources d’origine</li>
                                </ul>
                            </div>
                        </div>

                        <div class="mt-8 flex flex-col gap-3 border-t border-slate-100 pt-5 text-sm text-slate-400 md:flex-row md:items-center md:justify-between">
                            <span>Plateforme publique et gratuite - Université de Mahajanga</span>
                            <span class="inline-flex items-center gap-2">
                                Explorer la bibliothèque
                                <ArrowRight :size="15" :stroke-width="1.5" />
                            </span>
                        </div>
                    </footer>
                </main>
            </div>
        </div>
    </div>
</template>
