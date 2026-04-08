<script setup>
const props = defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="flex items-center gap-3">
        <div
            class="relative overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-sm"
            :class="props.compact ? 'h-11 w-11' : 'h-14 w-14'"
        >
            <div class="absolute inset-x-2 top-2 h-3 rounded-full bg-slate-950/90" />
            <div class="absolute bottom-2 left-2 h-6 w-6 rounded-full bg-indigo-600/12 ring-1 ring-indigo-600/20" />
            <div class="absolute bottom-2 right-2 h-6 w-6 rounded-full bg-emerald-500/12 ring-1 ring-emerald-500/20" />
            <div
                class="absolute left-1/2 top-1/2 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-2xl bg-slate-950 text-white"
                :class="props.compact ? 'h-7 w-7 text-xs font-semibold' : 'h-8 w-8 text-sm font-semibold'"
            >
                Fi
            </div>
        </div>
    </div>
</template>
