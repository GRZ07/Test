<!-- src/components/TableSelector.vue -->
<template>
    <div class="mb-4">
        <label
            for="table-select"
            class="block text-sm font-medium text-gray-700 mb-1"
            >Select Table:</label
        >
        <select
            id="table-select"
            v-model="table"
            @change="$emit('table-change')"
            class="block w-full pl-3 pr-10 py-2 border border-gray-300 bg-white rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
        >
            <option
                v-for="tableName in tableList"
                :key="tableName"
                :value="tableName"
            >
                {{ tableName }}
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
        modelValue: {
            type: String,
            default: "",
        },
    },
    emits: ["update:modelValue", "table-change"],
    computed: {
        table: {
            get() {
                return this.modelValue;
            },
            set(value) {
                this.$emit("update:modelValue", value);
            },
        },
    },
};
</script>

<style scoped>
/* No additional styles needed */
</style>
