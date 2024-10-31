<template>
    <div
        class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50"
        @click.self="$emit('closeFilters')"
    >
        <div
            class="bg-white rounded-lg shadow-lg w-11/12 max-w-3xl p-6 overflow-y-auto"
        >
            <h2 class="text-2xl font-semibold mb-4">Apply Filters</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div
                    v-for="(column, index) in columns"
                    :key="index"
                    class="flex flex-col"
                >
                    <label
                        :for="'filter-' + column"
                        class="mb-1 font-medium text-gray-700"
                        >{{ column }}</label
                    >
                    <select
                        v-model="localTempFilters[column]"
                        :id="'filter-' + column"
                        @change="onFilterTypeChange(column)"
                        class="p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
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
                        v-if="localTempFilters[column] === 'contains'"
                        type="text"
                        v-model="localTempFilterValues[column]"
                        placeholder="Enter value..."
                        class="mt-2 p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    />

                    <!-- Input for numerical filters -->
                    <input
                        v-if="
                            localTempFilters[column] &&
                            (localTempFilters[column] === 'greaterThan' ||
                                localTempFilters[column] === 'lessThan' ||
                                localTempFilters[column] === 'equals')
                        "
                        type="number"
                        v-model="localTempFilterValues[column]"
                        placeholder="Enter numeric value..."
                        class="mt-2 p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    />

                    <!-- Input for date and number 'between' filters -->
                    <input
                        v-if="
                            localTempFilters[column] &&
                            (localTempFilters[column] === 'after' ||
                                localTempFilters[column] === 'before')
                        "
                        type="date"
                        v-model="localTempFilterValues[column]"
                        placeholder="Enter date..."
                        class="mt-2 p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <div
                        v-if="localTempFilters[column] === 'between'"
                        class="mt-2 flex space-x-2"
                    >
                        <input
                            :placeholder="getFilterPlaceholder(column, 'start')"
                            v-model="localTempFilterValues[column].start"
                            @input="onFilterInputChange(column)"
                            :type="
                                columnTypes[column] === 'date'
                                    ? 'date'
                                    : 'number'
                            "
                            class="p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <input
                            :placeholder="getFilterPlaceholder(column, 'end')"
                            v-model="localTempFilterValues[column].end"
                            @input="onFilterInputChange(column)"
                            :type="
                                columnTypes[column] === 'date'
                                    ? 'date'
                                    : 'number'
                            "
                            class="p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                </div>
            </div>
            <!-- Apply and Reset Filter Buttons -->
            <div class="flex justify-end space-x-4 mt-6">
                <button
                    @click="applyFilters"
                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                >
                    Apply Filters
                </button>
                <button
                    @click="resetFilters"
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500"
                >
                    Reset Filters
                </button>
                <button
                    @click="$emit('closeFilters')"
                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</template>

<script>
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
        initialFilters: {
            type: Object,
            default: () => ({}),
        },
        initialFilterValues: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
        return {
            localTempFilters: { ...this.initialFilters },
            localTempFilterValues: { ...this.initialFilterValues },
        };
    },
    emits: ["applyFilters", "resetFilters"],
    methods: {
        onFilterTypeChange(column) {
            if (this.localTempFilters[column] === "between") {
                this.$set(this.localTempFilterValues, column, {
                    start: "",
                    end: "",
                });
            } else {
                this.$set(this.localTempFilterValues, column, "");
            }
        },
        onFilterInputChange(column) {
            // Handle any additional logic when filter inputs change
        },
        applyFilters() {
            this.$emit("applyFilters", {
                filters: this.localTempFilters,
                filterValues: this.localTempFilterValues,
            });
        },
        resetFilters() {
            this.localTempFilters = {};
            this.localTempFilterValues = {};
            this.$emit("resetFilters");
        },
        getFilterPlaceholder(column, part) {
            if (this.localTempFilters[column] === "between") {
                if (this.columnTypes[column] === "date") {
                    return part === "start" ? "Start Date" : "End Date";
                } else if (this.columnTypes[column] === "number") {
                    return part === "start" ? "Minimum Value" : "Maximum Value";
                }
            }
            return this.columnTypes[column] === "date"
                ? "Select date"
                : "Enter value...";
        },
    },
};
</script>

<style scoped>
/* Tailwind CSS handles the styling */
</style>
