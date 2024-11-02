<template>
    <div class="p-6 bg-gray-100 min-h-screen">
        <h1 class="text-3xl font-bold mb-6 text-center text-gray-800">
            Dynamic Table Viewer
        </h1>

        <!-- Dropdown to select table -->
        <div class="flex justify-center mb-4">
            <select
                v-model="selectedTable"
                @change="onTableChange"
                class="block w-full max-w-xs p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            >
                <option v-for="table in tableList" :key="table" :value="table">
                    {{ table }}
                </option>
            </select>
        </div>

        <!-- Filters, Loading and Error States -->
        <LoadingError
            :isLoading="isLoading"
            :isFetching="isFetching"
            :isError="isError"
            :error="error"
        />
        <SearchInput
            v-if="data && data.data && !isError"
            :searchQuery="searchQuery"
            @input="onSearch"
        />

        <!-- Filter & Reset Buttons -->
        <FiltersButton
            :tempFilters="tempFilters"
            :sort="sort"
            :relatedToParams="relatedToParams"
            @showFilters="showFilters = true"
            @resetFilters="resetFilters"
        />

        <!-- Filters Modal -->
        <FiltersModal
            v-if="showFilters"
            :columns="data.columns"
            :tempFilters="tempFilters"
            :tempFilterValues="tempFilterValues"
            :columnTypes="columnTypes"
            @applyFilters="applyFilters"
            @closeFilters="closeFilters"
        />

        <!-- Dynamic Table -->
        <DataTable
            :data="data"
            :columns="data.columns"
            :sort="sort"
            :columnTypes="columnTypes"
            @toggleSort="toggleSort"
            @handleRelationshipClick="handleRelationshipClick"
        />

        <!-- Pagination Controls -->
        <Pagination
            :currentPage="currentPage"
            :lastPage="data['data'].last_page"
            :prevPageUrl="data['data'].prev_page_url"
            :nextPageUrl="data['data'].next_page_url"
            @goToPage="goToPage"
        />
    </div>
</template>

<script>
import { ref, onMounted, watch } from "vue";
import { useQuery } from "@tanstack/vue-query";
import { debounce } from "lodash";

// Import composables
import useFilters from "./composables/useFilters.js";
import usePagination from "./composables/usePagination.js";
import useSort from "./composables/useSort.js";

// Import components
import LoadingError from "./components/LoadingError.vue";
import SearchInput from "./components/SearchInput.vue";
import FiltersButton from "./components/FiltersButton.vue";
import FiltersModal from "./components/FiltersModal.vue";
import DataTable from "./components/DataTable.vue";
import Pagination from "./components/Pagination.vue";

export default {
    setup() {
        // Step 1: Initialize basic reactive values
        const selectedTable = ref("users");
        const tableList = ref([]);
        const searchQuery = ref("");
        const relatedToParams = ref(null);
        const showFilters = ref(false);

        const columnTypes = ref({}); // For column types received from the backend

        // Step 2: Initialize useQuery after basic reactive variables
        const { isLoading, isError, isFetching, data, error, refetch } = useQuery({
            queryKey: [
                "dynamicTable",
                selectedTable.value,
                sort.value.column,
                sort.value.direction,
                JSON.stringify(filters.value),
                searchQuery.value,
                currentPage.value,
                JSON.stringify(relatedToParams.value),
            ],
            queryFn: async () => {
                const result = await fetcher(
                    "/table-data",
                    selectedTable.value,
                    sort.value,
                    filters.value,
                    searchQuery.value,
                    currentPage.value,
                    relatedToParams.value
                );
                return result;
            },
            staleTime: 5000,
            keepPreviousData: true,
        });

        // Step 3: Initialize composables that rely on refetch
        // Destructure composables to avoid redeclaration of reactive variables
        const { filters, filterValues, tempFilters, tempFilterValues, applyFilters, resetFilters, onTempFilterChange } = useFilters(refetch, columnTypes);
        const { currentPage, goToPage } = usePagination(refetch);
        const { sort, toggleSort } = useSort(refetch);

        // Fetch table names helper
        const fetchTableNames = async () => {
            isLoading.value = true;
            try {
                const response = await fetch("/table-names");
                const result = await response.json();
                tableList.value = Object.values(result);
                selectedTable.value = tableList.value.includes("users")
                    ? "users"
                    : tableList.value[0] || "";
            } catch (err) {
                error.value = err;
            } finally {
                isLoading.value = false;
            }
        };

        // Function to handle table changes
        const onTableChange = async () => {
            currentPage.value = 1;
            filters.value = {};
            filterValues.value = {};
            sort.value = { column: null, direction: "asc" };
            searchQuery.value = "";
            relatedToParams.value = null;
            refetch();
        };

        // Search functionality with debounce
        const debouncedOnSearch = debounce(() => {
            currentPage.value = 1;
            refetch();
        }, 300);

        const onSearch = () => {
            debouncedOnSearch();
        };

        // Watcher to update column types
        watch(data, (newData) => {
            if (newData?.data?.columns && Array.isArray(newData.columns)) {
                columnTypes.value = newData.columnTypes;
            } else {
                columnTypes.value = {};
            }
        });

        onMounted(() => {
            fetchTableNames();
        });

        return {
            selectedTable,
            tableList,
            searchQuery,
            relatedToParams,
            showFilters,
            isLoading,
            isError,
            isFetching,
            data,
            error,
            applyFilters,
            resetFilters,
            onTableChange,
            toggleSort,
            goToPage,
            onSearch,
            currentPage,
            sort,
            filters,
            tempFilters,
            tempFilterValues,
            filterValues,
        };
    },
};
</script>
