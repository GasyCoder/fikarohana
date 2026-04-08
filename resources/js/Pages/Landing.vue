<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import MarketingLayout from '../Layouts/MarketingLayout.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

defineOptions({
    layout: (h, page) => h(MarketingLayout, { lightHeader: true }, () => page),
});

const features = [
    {
        title: 'Chercher simplement',
        text: 'Entrez un nom, un sujet, un mot-clé ou une université et retrouvez rapidement les références les plus proches.',
    },
    {
        title: 'Ouvrir les bons liens',
        text: 'Chaque résultat vous renvoie vers sa source d’origine pour consulter le document, la notice ou l’archive complète.',
    },
    {
        title: 'Filtrer sans complication',
        text: 'Domaines, sources et années sont là pour aider la recherche, pas pour la rendre plus difficile.',
    },
];

const sourceCards = ['HAL', 'Theses.fr', 'Google Scholar', 'Cairn', 'Horizon IRD', 'Archives institutionnelles'];
</script>

<template>
    <Head title="Accueil" />

    <div class="bg-slate-950 text-white">
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(99,102,241,0.35),transparent_28%),radial-gradient(circle_at_80%_20%,rgba(56,189,248,0.18),transparent_22%),linear-gradient(180deg,#020617_0%,#0f172a_48%,#111827_100%)]" />
            <div class="relative mx-auto max-w-7xl px-4 pb-20 pt-16 sm:px-6 lg:px-8 lg:pb-28 lg:pt-20">
                <div class="grid gap-16 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                    <div class="max-w-3xl">
                        <p class="text-sm font-semibold uppercase tracking-[0.26em] text-indigo-300">
                            Plateforme bibliographique gratuite
                        </p>
                        <h1 class="mt-6 text-5xl font-semibold leading-[1.02] tracking-tight text-white sm:text-6xl">
                            Trouver plus facilement les ressources utiles à la recherche malgache.
                        </h1>
                        <p class="mt-7 max-w-2xl text-lg leading-8 text-slate-300">
                            Fikarohana est pensé pour les étudiants, doctorants, enseignants et chercheurs qui veulent repérer vite des documents, des références et des liens académiques autour de Madagascar.
                        </p>

                        <div class="mt-10 flex flex-col gap-4 sm:flex-row">
                            <Link
                                href="/bibliotheque"
                                class="inline-flex items-center justify-center rounded-full bg-white px-7 py-4 text-base font-semibold text-slate-950 transition hover:bg-slate-100"
                            >
                                Explorer la bibliothèque
                            </Link>
                            <Link
                                :href="user ? '/dashboard' : '/register'"
                                class="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/6 px-7 py-4 text-base font-semibold text-white transition hover:bg-white/10"
                            >
                                {{ user ? 'Ouvrir mon espace' : 'Créer un compte' }}
                            </Link>
                        </div>

                        <div class="mt-10 flex flex-wrap gap-3 text-sm text-slate-300">
                            <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2">Gratuit pour tous</span>
                            <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2">Sources académiques</span>
                            <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2">Recherche par auteur ou sujet</span>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border border-white/10 bg-white/6 p-6 shadow-[0_32px_120px_-45px_rgba(79,70,229,0.55)] backdrop-blur">
                        <div class="rounded-[1.5rem] bg-white p-6 text-slate-900">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">Aperçu</p>
                                    <h2 class="mt-2 text-2xl font-semibold">Une interface sobre et utile</h2>
                                </div>
                                <div class="rounded-2xl bg-indigo-50 px-4 py-3 text-right">
                                    <p class="text-xs uppercase tracking-[0.18em] text-indigo-600">Accès</p>
                                    <p class="mt-1 text-2xl font-semibold">Gratuit</p>
                                </div>
                            </div>

                            <div class="mt-6 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                <p class="text-sm leading-7 text-slate-600">
                                    La bibliothèque permet de chercher par titre, auteur, domaine ou source, puis d’ouvrir directement le lien vers la plateforme d’origine.
                                </p>
                            </div>

                            <div class="mt-6 space-y-3">
                                <div class="rounded-[1.25rem] border border-slate-200 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-indigo-600">HAL</p>
                                            <p class="mt-1 text-base font-semibold">Article scientifique avec lien vers la source d’origine</p>
                                        </div>
                                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">Lien direct</span>
                                    </div>
                                </div>
                                <div class="rounded-[1.25rem] border border-slate-200 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-emerald-600">Theses.fr</p>
                                            <p class="mt-1 text-base font-semibold">Thèse ou mémoire consultable depuis la plateforme source</p>
                                        </div>
                                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Notice</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <main>
        <section id="fonctionnalites" class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-indigo-600">Utilité</p>
                <h2 class="mt-4 text-4xl font-semibold tracking-tight text-slate-950">
                    Une interface pensée pour un vrai usage universitaire.
                </h2>
                <p class="mt-4 text-lg leading-8 text-slate-600">
                    Le but n’est pas de faire une vitrine. Le but est d’aider à trouver plus vite des références sérieuses et à sortir rapidement vers le bon document.
                </p>
            </div>

            <div class="mt-12 grid gap-6 lg:grid-cols-3">
                <article
                    v-for="feature in features"
                    :key="feature.title"
                    class="rounded-[1.75rem] border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-lg"
                >
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-600 text-lg font-black text-white">
                        F
                    </div>
                    <h3 class="mt-6 text-2xl font-semibold text-slate-950">{{ feature.title }}</h3>
                    <p class="mt-4 text-base leading-7 text-slate-600">{{ feature.text }}</p>
                </article>
            </div>
        </section>

        <section id="sources" class="border-y border-slate-200 bg-white">
            <div class="mx-auto max-w-7xl px-4 py-18 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-10 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-indigo-600">Sources</p>
                        <h2 class="mt-4 text-4xl font-semibold tracking-tight text-slate-950">
                            Des plateformes scientifiques connues, regroupées au même endroit.
                        </h2>
                    </div>
                    <Link href="/bibliotheque" class="inline-flex items-center justify-center rounded-full bg-slate-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Voir la bibliothèque
                    </Link>
                </div>

                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="source in sourceCards"
                        :key="source"
                        class="rounded-[1.5rem] border border-slate-200 bg-slate-50 px-5 py-5 text-lg font-semibold text-slate-900"
                    >
                        {{ source }}
                    </div>
                </div>
            </div>
        </section>

        <section id="gratuit" class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-8 shadow-sm sm:p-10">
                <div class="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div class="max-w-3xl">
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-indigo-600">Accès libre</p>
                        <h2 class="mt-4 text-4xl font-semibold tracking-tight text-slate-950">
                            Fikarohana est gratuit.
                        </h2>
                        <p class="mt-4 text-lg leading-8 text-slate-600">
                            Il n’y a pas de plan payant, pas de tableau de prix, pas de formule cachée. La priorité est de faciliter l’accès aux ressources bibliographiques, pas de vendre un abonnement.
                        </p>
                    </div>

                    <Link
                        href="/bibliotheque"
                        class="inline-flex items-center justify-center rounded-full bg-slate-900 px-7 py-4 text-base font-semibold text-white transition hover:bg-slate-800"
                    >
                        Entrer dans la bibliothèque
                    </Link>
                </div>
            </div>
        </section>
    </main>
</template>
