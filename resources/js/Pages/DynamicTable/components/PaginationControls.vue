<template>
    <div
        class="flex justify-center items-center space-x-4 mt-4"
        v-if="data && data.data && !isError"
    >
        <button
            v-if="data['data'].prev_page_url"
            @click="$emit('go-to-page', data['data'].prev_page_url)"
            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
            Previous
        </button>
        <button
            v-if="data['data'].next_page_url"
            @click="$emit('go-to-page', data['data'].next_page_url)"
            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
            Next
        </button>
        <!-- Page Indicator -->
        <span class="text-gray-700">
            Page {{ currentPage }} of {{ data["data"].last_page }}
        </span>
    </div>
</template>

<script>
export default {
    name: "PaginationControls",
    props: {
        data: {
            type: Object,
            required: true,
        },
        isError: {
            type: Boolean,
            required: true,
        },
        currentPage: {
            type: Number,
            required: true,
        },
    },
    emits: ["go-to-page"],
};
</script>

<style scoped>
/* Tailwind CSS handles the styling */
</style>
