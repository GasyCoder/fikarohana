<script setup>
import { ArrowUpRight } from 'lucide-vue-next';

const props = defineProps({
    resource: {
        type: Object,
        required: true,
    },
});

const typeBadgeClasses = {
    thesis: 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100',
    article: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100',
};
</script>

<template>
    <article
        class="surface-panel group flex h-full flex-col rounded-[28px] border border-slate-100 p-8 shadow-sm transition duration-200 ease-in-out hover:-translate-y-1 hover:border-slate-200 hover:shadow-lg"
    >
        <div class="flex items-start justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em]"
                    :class="typeBadgeClasses[props.resource.type] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200'"
                >
                    {{ props.resource.type === 'thesis' ? 'Thèse' : 'Article' }}
                </span>
                <span class="inline-flex rounded-full border border-slate-100 px-3 py-1 text-[11px] font-medium uppercase tracking-[0.12em] text-slate-500">
                    {{ props.resource.source_name }}
                </span>
            </div>

            <p class="text-sm font-medium text-slate-500">
                {{ props.resource.year }}
            </p>
        </div>

        <div class="mt-6">
            <h3 class="line-clamp-2 text-lg font-semibold leading-snug text-slate-950">
                {{ props.resource.title }}
            </h3>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-sm font-semibold text-slate-700">
                {{ props.resource.author_initials || 'RM' }}
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-slate-900">{{ props.resource.author }}</p>
                <p class="truncate text-sm text-slate-500">
                    {{ props.resource.institution || props.resource.domain }}
                </p>
            </div>
        </div>

        <p v-if="props.resource.abstract" class="mt-6 line-clamp-3 text-sm leading-7 text-slate-500">
            {{ props.resource.abstract }}
        </p>

        <div class="mt-auto pt-6">
            <div class="mb-6 flex flex-wrap gap-2">
                <span
                    v-for="tag in props.resource.tags?.slice(0, 3) ?? []"
                    :key="tag"
                    class="rounded-full bg-slate-50 px-2.5 py-1 text-xs text-slate-500"
                >
                    {{ tag }}
                </span>
            </div>

            <div class="flex items-center justify-between gap-4 border-t border-slate-100 pt-5">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Lien d’origine</p>
                    <p class="mt-1 truncate text-sm text-slate-500">
                        {{ props.resource.source_host ?? props.resource.source_name }}
                    </p>
                </div>

                <a
                    :href="props.resource.source_url"
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-indigo-50 hover:text-indigo-600"
                    :aria-label="`Open ${props.resource.title}`"
                >
                    Lire l'original
                    <ArrowUpRight :size="16" :stroke-width="1.5" />
                </a>
            </div>
        </div>
    </article>
</template>
