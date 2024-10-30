<!-- src/components/FiltersButton.vue -->
<template>
    <button
        @click="$emit('click')"
        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
    >
        Filters
    </button>
</template>

<script>
export default {
    name: "FiltersButton",
};
</script>

<style scoped>
/* No additional styles needed */
</style>
