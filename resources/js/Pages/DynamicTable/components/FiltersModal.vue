<template>
    <div v-if="show" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50" @click.self="close">
      <div class="bg-white rounded-lg shadow-lg w-11/12 max-w-3xl p-6 overflow-y-auto">
        <h2 class="text-2xl font-semibold mb-4">Apply Filters</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="(column, index) in columns" :key="index" class="flex flex-col">
            <label :for="'filter-' + column" class="mb-1 font-medium text-gray-700">{{ column }}</label>
            <select
              v-model="tempFilters[column]"
              :id="'filter-' + column"
              @change="onTempFilterChange(column)"
              class="p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            >
              <option value="" selected>Select Filter</option>
              <option v-if="columnTypes[column] === 'string'" value="contains">Contains</option>
              <option v-if="columnTypes[column] === 'number'" value="equals">Equals</option>
              <option v-if="columnTypes[column] === 'number'" value="greaterThan">Greater than</option>
              <option v-if="columnTypes[column] === 'number'" value="lessThan">Less than</option>
              <option v-if="columnTypes[column] === 'date'" value="after">After</option>
              <option v-if="columnTypes[column] === 'date'" value="before">Before</option>
              <option v-if="columnTypes[column] === 'date' || columnTypes[column] === 'number'" value="between">Between</option>
            </select>

            <!-- Input for 'contains' -->
            <input
              v-if="tempFilters[column] === 'contains'"
              type="text"
              v-model="tempFilterValues[column]"
              placeholder="Enter value..."
              class="mt-2 p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            />

            <!-- Input for numerical filters -->
            <input
              v-if="tempFilters[column] && ['greaterThan', 'lessThan', 'equals'].includes(tempFilters[column])"
              type="number"
              v-model="tempFilterValues[column]"
              placeholder="Enter numeric value..."
              class="mt-2 p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            />

            <!-- Input for date and number 'between' filters -->
            <div v-if="tempFilters[column] === 'between'" class="mt-2 flex space-x-2">
              <input
                :placeholder="getPlaceholder(column, 'start')"
                v-model="tempFilterValues[column].start"
                type="date"
                class="p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
              />
              <input
                :placeholder="getPlaceholder(column, 'end')"
                v-model="tempFilterValues[column].end"
                type="date"
                class="p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>
          </div>
        </div>
        <div class="flex justify-end space-x-4 mt-6">
          <button @click="applyFilters" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">Apply Filters</button>
          <button @click="resetFilters" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">Reset Filters</button>
          <button @click="close" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">Close</button>
        </div>
      </div>
    </div>
  </template>

  <script>
  export default {
    props: {
      columns: Array,
      tempFilters: Object,
      tempFilterValues: Object,
      columnTypes: Object,
      show: Boolean,
    },
    emits: ["applyFilters", "close", "resetFilters"],
    methods: {
      onTempFilterChange(column) {
        this.$emit("onTempFilterChange", column);
      },
      applyFilters() {
        this.$emit("applyFilters");
      },
      resetFilters() {
        this.$emit("resetFilters");
      },
      close() {
        this.$emit("close");
      },
      getPlaceholder(column, part) {
        return columnTypes[column] === "date"
          ? part === "start" ? "Start Date" : "End Date"
          : part === "start" ? "Minimum Value" : "Maximum Value";
      },
    },
  };
  </script>
