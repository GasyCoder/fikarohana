<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { Menu } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import FikarohanaLogo from './FikarohanaLogo.vue';

const props = defineProps({
    light: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const mobileOpen = ref(false);

const user = computed(() => page.props.auth?.user ?? null);
const currentUrl = computed(() => page.url ?? '/');

const navItems = [
    { label: 'Accueil', href: '/', match: '/' },
    { label: 'Bibliothèque', href: '/bibliotheque', match: '/bibliotheque' },
    { label: 'Sources', href: '/#sources', match: '/#sources' },
    { label: 'Utilité', href: '/#fonctionnalites', match: '/#fonctionnalites' },
    { label: 'Accès libre', href: '/#gratuit', match: '/#gratuit' },
];

const logout = () => {
    router.post('/logout');
};

const isActive = (href) => {
    if (href.startsWith('/#')) {
        return currentUrl.value === '/' && window?.location?.hash === href.slice(1);
    }

    return currentUrl.value === href;
};
</script>

<template>
    <header
        class="sticky top-0 z-40 border-b backdrop-blur-xl"
        :class="props.light ? 'border-white/10 bg-slate-950/75 text-white' : 'border-slate-100 bg-white/80 text-slate-900'"
    >
        <div class="mx-auto flex max-w-[1536px] items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <Link href="/" class="flex items-center gap-3">
                <FikarohanaLogo compact />
                <div>
                    <p class="text-sm font-semibold tracking-tight" :class="props.light ? 'text-white' : 'text-slate-950'">Fikarohana</p>
                    <p class="text-xs" :class="props.light ? 'text-slate-300' : 'text-slate-500'">Plateforme bibliographique publique</p>
                </div>
            </Link>

            <nav class="hidden items-center gap-2 rounded-full border border-slate-100 bg-white/90 p-1 shadow-sm lg:flex">
                <Link
                    v-for="item in navItems"
                    :key="item.label"
                    :href="item.href"
                    class="rounded-full px-4 py-2 text-sm transition"
                    :class="
                        isActive(item.match)
                            ? props.light
                                ? 'bg-white/10 text-white'
                                : 'bg-slate-950 text-white shadow-sm'
                            : props.light
                                ? 'text-slate-300 hover:text-white'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'
                    "
                >
                    {{ item.label }}
                </Link>
            </nav>

            <div class="hidden items-center gap-3 lg:flex">
                <template v-if="user">
                    <span class="text-sm" :class="props.light ? 'text-slate-300' : 'text-slate-500'">
                        {{ user.name }}
                    </span>
                    <button
                        type="button"
                        class="rounded-full px-4 py-2 text-sm font-medium transition"
                        :class="props.light ? 'bg-white text-slate-950 hover:bg-slate-100' : 'border border-slate-200 bg-white text-slate-900 hover:bg-slate-50'"
                        @click="logout"
                    >
                        Déconnexion
                    </button>
                </template>

                <template v-else>
                    <Link
                        href="/login"
                        class="rounded-full px-4 py-2 text-sm font-medium transition"
                        :class="props.light ? 'text-white hover:bg-white/10' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                    >
                        Connexion
                    </Link>
                    <Link
                        href="/register"
                        class="rounded-full px-4 py-2 text-sm font-medium transition"
                        :class="props.light ? 'bg-white text-slate-950 hover:bg-slate-100' : 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-500'"
                    >
                        Créer un compte
                    </Link>
                </template>
            </div>

            <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-full lg:hidden"
                :class="props.light ? 'bg-white/10 text-white' : 'border border-slate-200 bg-white text-slate-900 shadow-sm'"
                @click="mobileOpen = !mobileOpen"
            >
                <Menu :size="18" :stroke-width="1.5" />
            </button>
        </div>

        <div
            v-if="mobileOpen"
            class="border-t px-4 py-4 lg:hidden"
            :class="props.light ? 'border-white/10 bg-slate-950 text-white' : 'border-slate-100 bg-white text-slate-900'"
        >
            <div class="flex flex-col gap-2">
                <Link
                    v-for="item in navItems"
                    :key="`mobile-${item.label}`"
                    :href="item.href"
                    class="rounded-2xl px-4 py-3 text-sm font-medium"
                    :class="props.light ? 'hover:bg-white/10' : 'hover:bg-slate-50'"
                    @click="mobileOpen = false"
                >
                    {{ item.label }}
                </Link>

                <template v-if="user">
                    <button
                        type="button"
                        class="mt-2 rounded-2xl px-4 py-3 text-left text-sm font-medium"
                        :class="props.light ? 'bg-white text-slate-950' : 'bg-slate-950 text-white'"
                        @click="logout"
                    >
                        Déconnexion
                    </button>
                </template>

                <template v-else>
                    <Link href="/login" class="mt-2 rounded-2xl px-4 py-3 text-sm font-medium" :class="props.light ? 'bg-white/10' : 'bg-slate-50 text-slate-700'">
                        Connexion
                    </Link>
                    <Link href="/register" class="rounded-2xl bg-indigo-600 px-4 py-3 text-sm font-medium text-white" :class="props.light ? 'bg-white text-slate-950' : 'bg-indigo-600 text-white shadow-sm'">
                        Créer un compte
                    </Link>
                </template>
            </div>
        </div>
    </header>
</template>
