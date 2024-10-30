<!-- src/components/SearchBar.vue -->
<template>
    <div class="flex items-center">
        <input
            type="text"
            v-model="searchValue"
            @input="onInput"
            placeholder="Search all columns..."
            class="block w-full pl-3 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
        />
    </div>
</template>

<script>
export default {
    name: "SearchBar",
    props: {
        modelValue: {
            type: String,
            default: "",
        },
    },
    emits: ["update:modelValue", "search"],
    computed: {
        searchValue: {
            get() {
                return this.modelValue;
            },
            set(value) {
                this.$emit("update:modelValue", value);
            },
        },
    },
    methods: {
        onInput() {
            this.$emit("search");
        },
    },
};
</script>

<style scoped>
/* No additional styles needed */
</style>
