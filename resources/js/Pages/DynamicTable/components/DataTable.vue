<template>
    <div class="flex justify-center items-center space-x-4 mt-4">
      <button
        v-if="prevPageUrl"
        @click="$emit('goToPage', prevPageUrl)"
        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
      >
        Previous
      </button>
      <button
        v-if="nextPageUrl"
        @click="$emit('goToPage', nextPageUrl)"
        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
      >
        Next
      </button>
      <span class="text-gray-700">Page {{ currentPage }} of {{ lastPage }}</span>
    </div>
  </template>

  <script>
  export default {
    props: {
      currentPage: Number,
      lastPage: Number,
      prevPageUrl: String,
      nextPageUrl: String,
    },
    emits: ["goToPage"],
  };
  </script>
