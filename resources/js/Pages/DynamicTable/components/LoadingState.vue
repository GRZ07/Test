<!-- src/components/LoadingState.vue -->
<template>
    <div>
        <div v-if="isLoading" class="flex flex-col items-center py-4">
            <div class="loader mb-2"></div>
            <p class="text-gray-600">Loading data, please wait...</p>
        </div>
        <div
            v-else-if="isFetching && !isLoading"
            class="flex flex-col items-center py-4"
        >
            <div class="loader mb-2"></div>
            <p class="text-gray-600">Updating data...</p>
        </div>
        <div
            v-else-if="isError"
            class="flex flex-col items-center py-4 text-red-600"
        >
            <p>Error loading data: {{ error.message }}</p>
        </div>
    </div>
</template>

<script>
export default {
    name: "LoadingState",
    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
        isFetching: {
            type: Boolean,
            required: true,
        },
        isError: {
            type: Boolean,
            required: true,
        },
        error: {
            type: Object,
            default: () => ({}),
        },
    },
};
</script>

<style scoped>
.loader {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #6366f1; /* Indigo */
    border-radius: 50%;
    width: 36px;
    height: 36px;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
