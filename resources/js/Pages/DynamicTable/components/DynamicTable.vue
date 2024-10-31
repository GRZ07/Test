<!-- DynamicTable.vue -->
<template>
    <div class="overflow-x-auto">
        <table v-if="data && data.data && data.columns && !isError" class="min-w-full bg-white rounded-lg shadow overflow-hidden">
            <thead>
                <!-- Table Headers -->
            </thead>
            <tbody>
                <tr v-if="data.data.data.length === 0" class="text-center">
                    <td :colspan="data.columns.length" class="py-4 text-gray-500">
                        No data available
                    </td>
                </tr>
                <tr v-for="(item, index) in data.data.data" :key="index" class="hover:bg-gray-50">
                    <td v-for="(value, column) in item" :key="column" class="px-4 py-2 border-t">
                        <button
                            v-if="isCountColumn(column)"
                            @click="$emit('relationship-click', item, column)"
                            class="text-blue-600 hover:underline focus:outline-none"
                        >
                            {{ value }}
                        </button>
                        <span v-else>
                            {{ value }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
export default {
    name: "DynamicTable",
    props: {
        data: {
            type: Object,
            required: true,
        },
        isError: {
            type: Boolean,
            required: true,
        },
        sort: {
            type: Object,
            required: true,
        },
    },
    emits: ["toggleSort", "relationship-click"],
    methods: {
        toggleSort(column) {
            this.$emit("toggleSort", column);
        },
        isCountColumn(column) {
            return column.includes("_count");
        },
    },
};
</script>

<style scoped>
/* Tailwind CSS handles the styling */
</style>
