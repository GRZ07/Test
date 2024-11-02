<template>
    <div class="flex justify-center mb-4">
      <input
        type="text"
        :value="searchQuery"
        placeholder="Search all columns..."
        @input="$emit('update:searchQuery', $event.target.value)"  <!-- Emit the update event on input -->
        class="w-full max-w-md p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
      />
    </div>
  </template>

  <script>
  export default {
    props: {
      searchQuery: String
    },
  };
  </script>
