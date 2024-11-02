<template>
    <div class="flex justify-center mb-4">
        <div v-if="isLoading" class="text-blue-500">
            Loading data, please wait...
        </div>
        <div v-if="isFetching && !isLoading" class="text-yellow-500">
            Updating data...
        </div>
        <div v-if="isError" class="text-red-500">
            Error loading data: {{ error.message }}
        </div>
    </div>
</template>

<script>
export default {
    props: {
        isLoading: Boolean,
        isFetching: Boolean,
        isError: Boolean,
        error: Object,
    },
};
</script>
