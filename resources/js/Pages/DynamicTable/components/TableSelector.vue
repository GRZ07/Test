<template>
    <div class="flex justify-center mb-4">
        <select
            v-model="InternalSelectedTable"
            @change="onTableChange"
            class="block w-full max-w-xs p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
        >
            <option v-for="table in tableList" :key="table" :value="table">
                {{ table }}
            </option>
        </select>
    </div>
</template>

<script>
export default {
    name: "TableSelector",
    props: {
        tableList: {
            type: Array,
            required: true,
        },
        selectedTable: {
            type: String,
            required: true,
        },
    },
    emits: ["update:selectedTable"],
    computed: {
        InternalSelectedTable: {
            get() {
                return this.selectedTable;
            },
            set(value) {
                this.$emit("update:selectedTable", value);
            },
        },
    },
    methods: {
        onTableChange(event) {
            this.$emit("update:selectedTable", event.target.value);
        },
    },
};
</script>

<style scoped>
/* Tailwind CSS handles the styling */
</style>
