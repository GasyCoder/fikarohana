<script setup>
import AppHeader from '../Components/AppHeader.vue';

defineProps({
    lightHeader: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="min-h-screen bg-transparent">
        <AppHeader :light="lightHeader" />
        <slot />
    </div>
</template>
