<template>
    <div class="flex justify-center mb-4">
      <button @click="$emit('showFilters')" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        Filters
      </button>
      <button
        v-if="Object.keys(tempFilters).length > 0 || sort.column || relatedToParams"
        @click="$emit('resetFilters')"
        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 ml-4"
      >
        Reset Filters
      </button>
    </div>
  </template>

  <script>
  export default {
    props: {
      tempFilters: Object,
      sort: Object,
      relatedToParams: Object
    }
  };
  </script>
