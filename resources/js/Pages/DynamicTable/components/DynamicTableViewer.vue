<!-- src/components/DynamicTableViewer.vue -->

<template>
    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-6">Dynamic Table Viewer</h1>

        <!-- Dropdown to select table -->
        <div class="mb-4">
            <label for="table-select" class="block text-sm font-medium text-gray-700 mb-1">Select Table:</label>
            <select
                id="table-select"
                v-model="selectedTable"
                @change="onTableChange"
                class="block w-full pl-3 pr-10 py-2 border border-gray-300 bg-white rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
                <option v-for="table in tableList" :key="table" :value="table">
                    {{ table }}
                </option>
            </select>
        </div>

        <!-- Loading and Error States -->
        <div v-if="isLoading" class="flex flex-col items-center py-4">
            <div class="loader mb-2"></div>
            <p class="text-gray-600">Loading data, please wait...</p>
        </div>
        <div v-else-if="isFetching && !isLoading" class="flex flex-col items-center py-4">
            <div class="loader mb-2"></div>
            <p class="text-gray-600">Updating data...</p>
        </div>
        <div v-else-if="isError" class="flex flex-col items-center py-4 text-red-600">
            <p>Error loading data: {{ error.message }}</p>
        </div>

        <!-- Search and Filters -->
        <div v-if="hasData" class="flex flex-col sm:flex-row items-center justify-between mt-4 space-y-4 sm:space-y-0">
            <!-- Central Search Input -->
            <div class="flex-1">
                <input
                    type="text"
                    v-model="searchQuery"
                    placeholder="Search all columns..."
                    @input="onSearch"
                    class="block w-full pl-3 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                />
            </div>

            <!-- Filters Button -->
            <div class="flex-shrink-0">
                <button
                    @click="showFilters = true"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    Filters
                </button>
            </div>
        </div>

        <!-- Filters Modal -->
        <FiltersModal
            v-if="showFilters"
            :columns="columns"
            :columnTypes="columnTypes"
            v-model:filters="filters"
            v-model:filterValues="filterValues"
            @apply-filters="applyFilters"
            @reset-filters="resetFilters"
            @close="closeFilters"
        />

        <!-- Dynamic Table -->
        <TableComponent
            v-if="hasData"
            :columns="columns"
            :data="tableData"
            :sort="sort"
            :columnTypes="columnTypes"
            :relationshipDetails="relationshipDetails"
            @toggle-sort="toggleSort"
            @relationship-click="handleRelationshipClick"
        />

        <!-- Pagination Controls -->
        <PaginationControls
            v-if="hasData"
            :pagination="pagination"
            @go-to-page="goToPage"
        />
    </div>
</template>

<script>
import { useQuery } from "@tanstack/vue-query";
import pluralize from "pluralize";
import { ref, onMounted, watch, computed } from "vue";
import { debounce } from "lodash";

import FiltersModal from "./FiltersModal.vue";
import TableComponent from "./TableComponent.vue";
import PaginationControls from "./PaginationControls.vue";

export default {
    name: "DynamicTableViewer",
    components: {
        FiltersModal,
        TableComponent,
        PaginationControls,
    },
    setup() {
        // Reactive state
        const selectedTable = ref("users");
        const currentPage = ref(1);
        const tableList = ref([]);
        const sort = ref({
            column: null,
            direction: "asc",
        });
        const filters = ref({});
        const filterValues = ref({});
        const columnTypes = ref({});
        const searchQuery = ref("");

        // Temporary filters for the filter form
        const tempFilters = ref({});
        const tempFilterValues = ref({});

        // Relationship details
        const relationshipDetails = ref({});
        const relatedToParams = ref(null);

        // Modal visibility
        const showFilters = ref(false);

        // Debounce search input to prevent excessive API calls
        const debouncedOnSearch = debounce(() => {
            currentPage.value = 1;
            refetch();
        }, 300);

        const onSearch = () => {
            debouncedOnSearch();
        };

        // Fetch table names on component mount
        const fetchTableNames = async () => {
            try {
                const response = await fetch("/table-names");
                if (!response.ok) {
                    throw new Error(`Error fetching table names: ${response.statusText}`);
                }
                const result = await response.json();
                tableList.value = Object.values(result);

                // Set default to "users" if available, otherwise to the first table in the list
                selectedTable.value = tableList.value.includes("users")
                    ? "users"
                    : tableList.value[0] || "";
            } catch (err) {
                console.error(err);
                // Handle error appropriately
            }
        };

        onMounted(() => {
            fetchTableNames();
        });

        // Query function to fetch table data
        const fetchTableData = async () => {
            let url = `/table-data?table=${encodeURIComponent(selectedTable.value)}&page=${currentPage.value}`;

            if (sort.value.column) {
                const sortParam = sort.value.direction === "desc" ? `-${encodeURIComponent(sort.value.column)}` : encodeURIComponent(sort.value.column);
                url += `&sort=${sortParam}`;
            }

            if (searchQuery.value) {
                url += `&search=${encodeURIComponent(searchQuery.value)}`;
            }

            // Build filter query parameters
            Object.entries(filters.value).forEach(([key, type]) => {
                const value = filterValues.value[key];
                if (type && value !== null && value !== undefined && value !== "") {
                    if (type === "between" && value.start && value.end) {
                        url += `&filter[${encodeURIComponent(key)}][type]=between&filter[${encodeURIComponent(key)}][value][start]=${encodeURIComponent(value.start)}&filter[${encodeURIComponent(key)}][value][end]=${encodeURIComponent(value.end)}`;
                    } else {
                        url += `&filter[${encodeURIComponent(key)}][type]=${encodeURIComponent(type)}&filter[${encodeURIComponent(key)}][value]=${encodeURIComponent(value)}`;
                    }
                }
            });

            if (relatedToParams.value) {
                const { relationship, id, fromTable } = relatedToParams.value;
                url += `&relatedTo[relationship]=${encodeURIComponent(relationship)}&relatedTo[id]=${encodeURIComponent(id)}&relatedTo[fromTable]=${encodeURIComponent(fromTable)}`;
            }

            try {
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(`Error fetching data: ${response.statusText}`);
                }
                const result = await response.json();
                return result;
            } catch (err) {
                throw err;
            }
        };

        // Use useQuery to fetch table data
        const { isLoading, isError, isFetching, data, error, refetch } = useQuery({
            queryKey: [
                "dynamicTable",
                selectedTable.value,
                sort.value.column,
                sort.value.direction,
                JSON.stringify(filters.value),
                JSON.stringify(filterValues.value),
                searchQuery.value,
                currentPage.value,
                JSON.stringify(relatedToParams.value),
            ],
            queryFn: fetchTableData,
            staleTime: 5000,
            keepPreviousData: true,
        });

        // Compute derived states
        const columns = computed(() => data.value?.columns || []);
        const tableData = computed(() => data.value?.data?.data || []);
        const pagination = computed(() => data.value?.data || {});
        const hasData = computed(() => tableData.value.length > 0 && columns.value.length > 0);

        // Handle table selection change
        const onTableChange = async () => {
            currentPage.value = 1;
            filters.value = {};
            filterValues.value = {};
            sort.value = { column: null, direction: "asc" };
            searchQuery.value = "";
            relatedToParams.value = null;
            tempFilters.value = {};
            tempFilterValues.value = {};
            await refetch();
        };

        // Toggle sorting on a column
        const toggleSort = async (column) => {
            if (sort.value.column === column) {
                sort.value.direction = sort.value.direction === "asc" ? "desc" : "asc";
            } else {
                sort.value.column = column;
                sort.value.direction = "asc";
            }
            await refetch();
        };

        // Handle pagination
        const goToPage = async (url) => {
            const params = new URLSearchParams(url.split("?")[1]);
            const page = params.get("page");
            if (page) {
                currentPage.value = parseInt(page);
                await refetch();
            }
        };

        // Apply filters from modal
        const applyFilters = () => {
            currentPage.value = 1;
            showFilters.value = false;
            refetch();
        };

        // Reset filters from modal
        const resetFilters = () => {
            filters.value = {};
            filterValues.value = {};
            currentPage.value = 1;
            showFilters.value = false;
            refetch();
        };

        // Check if a column is a count column (e.g., users_count)
        const isCountColumn = (column) => column.includes("_count");

        // Handle relationship clicks to navigate to related tables
        const handleRelationshipClick = (item, column) => {
            const relatedTableBase = column.replace("_count", "");
            const relatedTable = pluralize(relatedTableBase);
            const fromTable = selectedTable.value;

            relatedToParams.value = {
                relationship: relatedTableBase,
                id: item.id,
                fromTable: fromTable,
            };

            selectedTable.value = relatedTable;
            currentPage.value = 1;
            refetch();
        };

        // Watch for changes in data to update columnTypes and relationshipDetails
        watch(data, (newData) => {
            if (newData && newData.columns && newData.columnTypes && newData.relationshipDetails) {
                columnTypes.value = newData.columnTypes;
                relationshipDetails.value = newData.relationshipDetails;
            } else {
                columnTypes.value = {};
                relationshipDetails.value = {};
            }

            // Synchronize temporary filters with actual filters when data changes externally
            tempFilters.value = { ...filters.value };
            tempFilterValues.value = { ...filterValues.value };
        });

        const getFilterPlaceholder = (column, part) => {
            if (tempFilters.value[column] === "between") {
                if (columnTypes.value[column] === "date") {
                    return part === "start" ? "Start Date" : "End Date";
                } else if (columnTypes.value[column] === "number") {
                    return part === "start" ? "Minimum Value" : "Maximum Value";
                }
            }
            return columnTypes.value[column] === "date"
                ? "Select date"
                : "Enter value...";
        };

        return {
            selectedTable,
            tableList,
            isLoading,
            isError,
            isFetching,
            data,
            error,
            searchQuery,
            tempFilters,
            tempFilterValues,
            columnTypes,
            sort,
            relatedToParams,
            relationshipDetails,
            onTableChange,
            toggleSort,
            goToPage,
            onSearch,
            applyFilters,
            resetFilters,
            closeFilters: () => (showFilters.value = false),
            getFilterPlaceholder,
            isCountColumn,
            handleRelationshipClick,
            showFilters,
        };
    },
};
</script>

<style scoped>
/* Loader Styles */
.loader {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #6366f1; /* Indigo */
    border-radius: 50%;
    width: 36px;
    height: 36px;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
