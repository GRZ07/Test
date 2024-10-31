<template>
    <div class="flex justify-center mb-4">
        <div v-if="isLoading" class="text-blue-500">
            Loading data, please wait...
        </div>
        <div v-if="isFetching && !isLoading" class="text-yellow-500">
            Updating data...
        </div>
        <div v-if="isError" class="text-red-500">
            Error loading data: {{ errorMessage }}
        </div>
    </div>
</template>

<script>
export default {
    name: "LoadingIndicator",
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
        errorMessage: {
            type: String,
            default: "",
        },
    },
};
</script>

<style scoped>
/* Tailwind CSS handles the styling */
</style>
