<!-- src/components/TableComponent.vue -->

<template>
    <div class="mt-6 overflow-x-auto">
        <table class="min-w-full bg-white rounded-lg shadow overflow-hidden">
            <thead class="bg-gray-50">
                <tr>
                    <th
                        v-for="(column, index) in columns"
                        :key="index"
                        @click="onToggleSort(column)"
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                    >
                        <div class="flex items-center">
                            {{ column }}
                            <svg
                                v-if="sort.column === column"
                                :class="
                                    sort.direction === 'asc'
                                        ? 'transform rotate-180'
                                        : ''
                                "
                                class="ml-1 h-4 w-4 text-gray-400"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path d="M5 15l7-7 7 7" />
                            </svg>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="data.length === 0">
                    <td
                        :colspan="columns.length"
                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"
                    >
                        No data available
                    </td>
                </tr>
                <tr
                    v-for="(item, index) in data"
                    :key="index"
                    class="hover:bg-gray-50"
                >
                    <td
                        v-for="(value, column) in item"
                        :key="column"
                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"
                    >
                        <button
                            v-if="isCountColumn(column)"
                            @click="handleRelationshipClick(item, column)"
                            class="text-indigo-600 hover:text-indigo-900 focus:outline-none"
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
    name: "TableComponent",
    props: {
        columns: {
            type: Array,
            required: true,
        },
        data: {
            type: Array,
            required: true,
        },
        sort: {
            type: Object,
            required: true,
        },
        columnTypes: {
            type: Object,
            required: true,
        },
        relationshipDetails: {
            type: Object,
            required: true,
        },
    },
    emits: ["toggle-sort", "relationship-click"],
    setup(props, { emit }) {
        const isCountColumn = (column) => column.includes("_count");

        const handleRelationshipClick = (item, column) => {
            emit("relationship-click", item, column);
        };

        const onToggleSort = (column) => {
            emit("toggle-sort", column);
        };

        return {
            isCountColumn,
            handleRelationshipClick,
            onToggleSort,
        };
    },
};
</script>

<style scoped>
/* No additional styles needed as Tailwind CSS handles styling */
</style>
