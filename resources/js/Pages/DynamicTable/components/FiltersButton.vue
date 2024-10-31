<template>
    <div class="flex justify-center mb-4">
        <button
            @click="$emit('openFilters')"
            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
            Filters
        </button>
    </div>
</template>

<script>
export default {
    name: "FiltersButton",
};
</script>

<style scoped>
/* Tailwind CSS handles the styling */
</style>
