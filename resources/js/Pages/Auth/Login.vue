<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import AuthLayout from '../../Layouts/AuthLayout.vue';

const page = usePage();
const status = computed(() => page.props.flash?.status);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};

defineOptions({
    layout: (h, page) =>
        h(AuthLayout, {
            title: 'Connexion',
            subtitle: 'Connectez-vous pour accéder à votre espace et reprendre votre exploration des ressources.',
        }, () => page),
});
</script>

<template>
    <Head title="Connexion" />

    <div>
        <div v-if="status" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ status }}
        </div>

        <form class="space-y-5" @submit.prevent="submit">
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
                    autocomplete="current-password"
                    class="w-full rounded-2xl border border-slate-300 px-4 py-3.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                    placeholder="Votre mot de passe"
                >
                <p v-if="form.errors.password" class="mt-2 text-sm text-rose-600">{{ form.errors.password }}</p>
            </div>

            <label class="flex items-center gap-3 text-sm text-slate-600">
                <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                Se souvenir de moi
            </label>

            <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-2xl bg-indigo-600 px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="form.processing"
            >
                Se connecter
            </button>
        </form>

        <p class="mt-6 text-sm text-slate-500">
            Pas encore de compte ?
            <Link href="/register" class="font-semibold text-indigo-600 hover:text-indigo-500">Créer un compte</Link>
        </p>
    </div>
</template>
