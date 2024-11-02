import { ref } from 'vue';

export default function useFilters(refetch, columnTypes) {
    const filters = ref({});
    const filterValues = ref({});
    const tempFilters = ref({});
    const tempFilterValues = ref({});

    const applyFilters = () => {
        filters.value = { ...tempFilters.value };
        filterValues.value = { ...tempFilterValues.value };
        refetch();
    };

    const resetFilters = () => {
        filters.value = {};
        filterValues.value = {};
        tempFilters.value = {};
        tempFilterValues.value = {};
        refetch();
    };

    const onTempFilterChange = (column) => {
        if (tempFilters.value[column] === "between") {
            tempFilterValues.value[column] = { start: "", end: "" };
        } else {
            tempFilterValues.value[column] = "";
        }
    };

    return {
        filters,
        filterValues,
        tempFilters,
        tempFilterValues,
        applyFilters,
        resetFilters,
        onTempFilterChange,
    };
}
