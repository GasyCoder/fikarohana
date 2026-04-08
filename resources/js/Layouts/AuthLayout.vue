<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        required: true,
    },
});

const page = usePage();
const appName = computed(() => page.props.appName ?? 'Fikarohana');
</script>

<template>
    <Head :title="props.title" />

    <div class="min-h-screen bg-[linear-gradient(180deg,#eef2ff_0%,#f8fafc_48%,#ffffff_100%)]">
        <div class="mx-auto grid min-h-screen max-w-7xl lg:grid-cols-[1.1fr_0.9fr]">
            <section class="hidden px-10 py-12 lg:flex lg:flex-col lg:justify-between">
                <Link href="/" class="inline-flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-600 text-xl font-black text-white">
                        F
                    </div>
                    <div>
                        <p class="text-sm font-semibold tracking-[0.2em] text-indigo-600">FIKAROHANA</p>
                        <p class="text-xs text-slate-500">{{ appName }}</p>
                    </div>
                </Link>

                <div class="max-w-xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">Academic SaaS Platform</p>
                    <h1 class="mt-4 text-5xl font-semibold leading-tight text-slate-950">
                        Construisez votre accès rapide aux ressources de recherche malgaches.
                    </h1>
                    <p class="mt-6 text-lg leading-8 text-slate-600">
                        Une interface pensée pour les étudiants, doctorants et chercheurs qui veulent centraliser, filtrer et retrouver vite les bons liens documentaires.
                    </p>
                </div>

                <div class="grid max-w-xl grid-cols-3 gap-4">
                    <div class="rounded-3xl bg-white/80 p-5 shadow-sm ring-1 ring-slate-200">
                        <p class="text-2xl font-semibold text-slate-950">HAL</p>
                        <p class="mt-2 text-sm text-slate-500">Sources académiques indexées</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 p-5 shadow-sm ring-1 ring-slate-200">
                        <p class="text-2xl font-semibold text-slate-950">Vue 3</p>
                        <p class="mt-2 text-sm text-slate-500">Navigation rapide Inertia</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 p-5 shadow-sm ring-1 ring-slate-200">
                        <p class="text-2xl font-semibold text-slate-950">Simple</p>
                        <p class="mt-2 text-sm text-slate-500">Pensé pour la recherche réelle</p>
                    </div>
                </div>
            </section>

            <section class="flex items-center justify-center px-4 py-10 sm:px-6 lg:px-10">
                <div class="w-full max-w-md rounded-[2rem] border border-slate-200 bg-white p-8 shadow-[0_24px_80px_-40px_rgba(15,23,42,0.35)] sm:p-10">
                    <div class="mb-8 lg:hidden">
                        <Link href="/" class="inline-flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-600 text-xl font-black text-white">
                                F
                            </div>
                            <div>
                                <p class="text-sm font-semibold tracking-[0.2em] text-indigo-600">FIKAROHANA</p>
                                <p class="text-xs text-slate-500">{{ appName }}</p>
                            </div>
                        </Link>
                    </div>

                    <h2 class="text-3xl font-semibold tracking-tight text-slate-950">{{ props.title }}</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">{{ props.subtitle }}</p>

                    <div class="mt-8">
                        <slot />
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
