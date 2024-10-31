<template>
    <div class="flex justify-center mb-4">
        <input
            type="text"
            v-model="internalSearchQuery"
            placeholder="Search all columns..."
            class="w-full max-w-md p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
        />
    </div>
</template>

<script>
export default {
    name: "SearchInput",
    props: {
        searchQuery: {
            type: String,
            required: true,
        },
    },
    emits: ["update:searchQuery"],
    computed: {
        internalSearchQuery: {
            get() {
                return this.searchQuery;
            },
            set(value) {
                this.$emit("update:searchQuery", value);
            },
        },
    },
};
</script>

<style scoped>
/* Tailwind CSS handles the styling */
</style>
