<!-- src/components/FiltersModal.vue -->

<template>
    <div
        class="fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 z-50"
    >
        <div
            class="bg-white p-6 rounded-lg w-full max-w-lg max-h-4/5 overflow-y-auto"
        >
            <h2 class="text-xl font-semibold mb-4">Apply Filters</h2>
            <div class="space-y-4">
                <div
                    v-for="(column, index) in columns"
                    :key="index"
                    class="flex flex-col"
                >
                    <label
                        :for="'filter-' + column"
                        class="mb-1 text-sm font-medium text-gray-700"
                        >{{ column }}</label
                    >
                    <select
                        v-model="localFilters[column]"
                        :id="'filter-' + column"
                        @change="onFilterTypeChange(column)"
                        class="block w-full pl-3 pr-10 py-2 border border-gray-300 bg-white rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    >
                        <option value="" selected>Select Filter</option>
                        <option
                            v-if="columnTypes[column] === 'string'"
                            value="contains"
                        >
                            Contains
                        </option>
                        <option
                            v-if="columnTypes[column] === 'number'"
                            value="equals"
                        >
                            Equals
                        </option>
                        <option
                            v-if="columnTypes[column] === 'number'"
                            value="greaterThan"
                        >
                            Greater than
                        </option>
                        <option
                            v-if="columnTypes[column] === 'number'"
                            value="lessThan"
                        >
                            Less than
                        </option>
                        <option
                            v-if="columnTypes[column] === 'date'"
                            value="after"
                        >
                            After
                        </option>
                        <option
                            v-if="columnTypes[column] === 'date'"
                            value="before"
                        >
                            Before
                        </option>
                        <option
                            v-if="columnTypes[column] === 'date'"
                            value="between"
                        >
                            Between
                        </option>
                        <!-- Add 'between' option for number columns -->
                        <option
                            v-if="columnTypes[column] === 'number'"
                            value="between"
                        >
                            Between
                        </option>
                    </select>

                    <!-- Input for 'contains' -->
                    <input
                        v-if="localFilters[column] === 'contains'"
                        type="text"
                        v-model="localFilterValues[column]"
                        placeholder="Enter value..."
                        class="mt-2 block w-full pl-3 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    />

                    <!-- Input for numerical filters -->
                    <input
                        v-if="
                            localFilters[column] &&
                            (localFilters[column] === 'greaterThan' ||
                                localFilters[column] === 'lessThan' ||
                                localFilters[column] === 'equals')
                        "
                        type="number"
                        v-model="localFilterValues[column]"
                        placeholder="Enter numeric value..."
                        class="mt-2 block w-full pl-3 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    />

                    <!-- Input for date and number 'between' filters -->
                    <div
                        v-if="localFilters[column] === 'between'"
                        class="mt-2 flex space-x-2"
                    >
                        <input
                            :placeholder="getFilterPlaceholder(column, 'start')"
                            v-model="localFilterValues[column].start"
                            @input="onFilterValueChange(column)"
                            :type="
                                columnTypes[column] === 'date'
                                    ? 'date'
                                    : 'number'
                            "
                            class="block w-1/2 pl-3 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        />
                        <input
                            :placeholder="getFilterPlaceholder(column, 'end')"
                            v-model="localFilterValues[column].end"
                            @input="onFilterValueChange(column)"
                            :type="
                                columnTypes[column] === 'date'
                                    ? 'date'
                                    : 'number'
                            "
                            class="block w-1/2 pl-3 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        />
                    </div>
                </div>
            </div>
            <!-- Apply and Reset Filter Buttons -->
            <div class="flex justify-end space-x-3 mt-6">
                <button
                    @click="applyFilters"
                    class="inline-flex justify-center rounded-md border border-transparent px-4 py-2 bg-indigo-600 text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    Apply Filters
                </button>
                <button
                    @click="resetFilters"
                    class="inline-flex justify-center rounded-md border border-gray-300 px-4 py-2 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    Reset Filters
                </button>
                <button
                    @click="$emit('close')"
                    class="inline-flex justify-center rounded-md border border-gray-300 px-4 py-2 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, watch, computed } from "vue";

export default {
    name: "FiltersModal",
    props: {
        columns: {
            type: Array,
            required: true,
        },
        columnTypes: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            default: () => ({}),
        },
        filterValues: {
            type: Object,
            default: () => ({}),
        },
    },
    emits: [
        "update:filters",
        "update:filterValues",
        "apply-filters",
        "reset-filters",
        "close",
    ],
    setup(props, { emit }) {
        const localFilters = ref({ ...props.filters });
        const localFilterValues = ref({ ...props.filterValues });

        watch(
            () => props.filters,
            (newFilters) => {
                localFilters.value = { ...newFilters };
            }
        );

        watch(
            () => props.filterValues,
            (newValues) => {
                localFilterValues.value = { ...newValues };
            }
        );

        const getFilterPlaceholder = (column, part) => {
            if (localFilters.value[column] === "between") {
                if (props.columnTypes[column] === "date") {
                    return part === "start" ? "Start Date" : "End Date";
                } else if (props.columnTypes[column] === "number") {
                    return part === "start" ? "Minimum Value" : "Maximum Value";
                }
            }
            return props.columnTypes[column] === "date"
                ? "Select date"
                : "Enter value...";
        };

        const onFilterTypeChange = (column) => {
            if (localFilters.value[column] === "between") {
                localFilterValues.value[column] = { start: "", end: "" };
            } else {
                localFilterValues.value[column] = "";
            }
        };

        const onFilterValueChange = (column) => {
            // No additional logic needed for now
        };

        const applyFilters = () => {
            emit("update:filters", { ...localFilters.value });
            emit("update:filterValues", { ...localFilterValues.value });
            emit("apply-filters");
        };

        const resetFilters = () => {
            localFilters.value = {};
            localFilterValues.value = {};
            emit("update:filters", {});
            emit("update:filterValues", {});
            emit("reset-filters");
        };

        return {
            localFilters,
            localFilterValues,
            getFilterPlaceholder,
            onFilterTypeChange,
            onFilterValueChange,
            applyFilters,
            resetFilters,
        };
    },
};
</script>

<style scoped>
/* No additional styles needed as Tailwind CSS handles styling */
</style>
