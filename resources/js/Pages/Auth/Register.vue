<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

import AuthLayout from '../../Layouts/AuthLayout.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

defineOptions({
    layout: (h, page) =>
        h(AuthLayout, {
            title: 'Créer un compte',
            subtitle: 'Rejoignez Fikarohana pour préparer un espace personnel autour de vos recherches et références.',
        }, () => page),
});
</script>

<template>
    <Head title="Créer un compte" />

    <form class="space-y-5" @submit.prevent="submit">
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Nom complet</label>
            <input
                v-model="form.name"
                type="text"
                autocomplete="name"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                placeholder="Votre nom"
            >
            <p v-if="form.errors.name" class="mt-2 text-sm text-rose-600">{{ form.errors.name }}</p>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Email</label>
            <input
                v-model="form.email"
                type="email"
                autocomplete="email"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                placeholder="vous@universite.mg"
            >
            <p v-if="form.errors.email" class="mt-2 text-sm text-rose-600">{{ form.errors.email }}</p>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Mot de passe</label>
            <input
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                placeholder="Au moins 8 caractères"
            >
            <p v-if="form.errors.password" class="mt-2 text-sm text-rose-600">{{ form.errors.password }}</p>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Confirmation du mot de passe</label>
            <input
                v-model="form.password_confirmation"
                type="password"
                autocomplete="new-password"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                placeholder="Retapez votre mot de passe"
            >
        </div>

        <button
            type="submit"
            class="inline-flex w-full items-center justify-center rounded-2xl bg-indigo-600 px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="form.processing"
        >
            Créer mon compte
        </button>
    </form>

    <p class="mt-6 text-sm text-slate-500">
        Déjà inscrit ?
        <Link href="/login" class="font-semibold text-indigo-600 hover:text-indigo-500">Se connecter</Link>
    </p>
</template>
