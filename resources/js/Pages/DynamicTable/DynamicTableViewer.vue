<template>
    <div class="p-6 bg-gray-100 min-h-screen">
        <h1 class="text-3xl font-bold mb-6 text-center text-gray-800">
            Dynamic Table Viewer
        </h1>

        <!-- Table Selector -->
        <TableSelector
            :tableList="tableList"
            v-model:selectedTable="selectedTable"
        />

        <!-- Loading and Error Indicators -->
        <LoadingIndicator
            :isLoading="isLoading"
            :isFetching="isFetching"
            :isError="isError"
            :errorMessage="error"
        />

        <!-- Search Input -->
        <SearchInput
            v-if="data && data.data && !isError"
            v-model:searchQuery="searchQuery"
        />

        <!-- Filters Button -->
        <FiltersButton
            v-if="data && data.data && !isError"
            @openFilters="showFilters = true"
        />

        <!-- Filters Modal -->
        <FiltersModal
            v-if="showFilters"
            :columns="data.columns"
            :columnTypes="data.columnTypes"
            :initialFilters="filters"
            :initialFilterValues="filterValues"
            @applyFilters="applyFilters"
            @resetFilters="resetFilters"
            @closeFilters="closeFilters"
        />

        <!-- Dynamic Table -->
        <DynamicTable
            v-if="data && data.data && data.columns && !isError"
            :data="data"
            :isError="isError"
            :sort="sort"
            @toggleSort="toggleSort"
            @relationship-click="handleRelationshipClick"
        />

        <!-- Pagination Controls -->
        <PaginationControls
            v-if="data && data.data && !isError"
            :data="data"
            :isError="isError"
            :currentPage="currentPage"
            @go-to-page="goToPage"
        />
    </div>
</template>

<script>
import { useQuery } from "@tanstack/vue-query";
import pluralize from "pluralize";
import { ref, onMounted, watch } from "vue";
import { debounce } from "lodash";


// Import child components
import TableSelector from "./components/TableSelector.vue";
import LoadingIndicator from "./components/LoadingIndicator.vue";
import SearchInput from "./components/SearchInput.vue";
import FiltersButton from "./components/FiltersButton.vue";
import FiltersModal from "./components/FiltersModal.vue";
import DynamicTable from "./components/DynamicTable.vue";
import PaginationControls from "./components/PaginationControls.vue";

export default {
    name: "DynamicTableViewer",
    components: {
        TableSelector,
        LoadingIndicator,
        SearchInput,
        FiltersButton,
        FiltersModal,
        DynamicTable,
        PaginationControls,
    },
    setup() {
        const selectedTable = ref("users");
        const currentPage = ref(1);
        const tableList = ref([]);
        const sort = ref({
            column: null,
            direction: "asc", // 'asc' or 'desc'
        });
        const filters = ref({});
        const filterValues = ref({});
        const columnTypes = ref({});
        const searchQuery = ref("");
        const tempFilters =  ref({});
        const tempFilterValues =  ref({});

        // Relationship details
        const relationshipDetails = ref({});
        const relatedToParams = ref(null);

        // Modal visibility
        const showFilters = ref(false);

        const debouncedOnSearch = debounce(() => {
            currentPage.value = 1; // Reset to first page on search
            refetch(); // Trigger a refetch on search change
        }, 300); // Adjust the delay as needed

        const onSearch = () => {
            debouncedOnSearch();
        };

        const fetchTableNames = async () => {
            isLoading.value = true;
            try {
                const response = await fetch("/table-names");
                const result = await response.json();
                tableList.value = Object.values(result); // Ensure it's an array

                // Set default to "users" if available, otherwise to the first table in the list
                selectedTable.value = tableList.value.includes("users")
                    ? "users"
                    : tableList.value[0] || "";
            } catch (err) {
                error.value = err;
            } finally {
                isLoading.value = false;
            }
        };

        onMounted(() => {
            fetchTableNames(); // Fetch table names when the component is mounted
        });

        const fetcher = async (
            url,
            table,
            sort,
            filters,
            search,
            page,
            relatedTo
        ) => {
            let sortQuery = "";
            if (sort.column) {
                sortQuery =
                    sort.direction === "desc"
                        ? `&sort=-${encodeURIComponent(sort.column)}`
                        : `&sort=${encodeURIComponent(sort.column)}`;
            }

            // Build filter query with type and value
            const filterQuery = Object.entries(filters)
                .filter(([key, type]) => {
                    if (!type) return false;
                    const value = filterValues.value[key];
                    if (value === "" || value === null || value === undefined)
                        return false;
                    if (type === "between") {
                        const val = filterValues.value[key];
                        return (
                            val.start !== "" &&
                            val.start !== null &&
                            val.end !== "" &&
                            val.end !== null
                        );
                    }
                    return true;
                })
                .map(([key, type]) => {
                    if (
                        type === "between" &&
                        typeof filterValues.value[key] === "object"
                    ) {
                        const { start, end } = filterValues.value[key];
                        return `&filter[${encodeURIComponent(
                            key
                        )}][type]=between&filter[${encodeURIComponent(
                            key
                        )}][value][start]=${encodeURIComponent(
                            start
                        )}&filter[${encodeURIComponent(
                            key
                        )}][value][end]=${encodeURIComponent(end)}`;
                    } else {
                        return `&filter[${encodeURIComponent(
                            key
                        )}][type]=${encodeURIComponent(
                            type
                        )}&filter[${encodeURIComponent(
                            key
                        )}][value]=${encodeURIComponent(
                            filterValues.value[key]
                        )}`;
                    }
                })
                .join("");

            const searchQueryStr = search
                ? `&search=${encodeURIComponent(search)}`
                : "";

            let relatedToQuery = "";
            if (relatedTo) {
                relatedToQuery = `&relatedTo[relationship]=${encodeURIComponent(
                    relatedTo.relationship
                )}&relatedTo[id]=${encodeURIComponent(
                    relatedTo.id
                )}&relatedTo[fromTable]=${encodeURIComponent(
                    relatedTo.fromTable
                )}`;
            }

            const finalUrl = `${url}?table=${encodeURIComponent(
                table
            )}&page=${page}${sortQuery}${filterQuery}${searchQueryStr}${relatedToQuery}`;

            console.log("Fetching data with URL:", finalUrl); // Debugging line

            const response = await fetch(finalUrl);
            if (!response.ok) {
                throw new Error(`Error fetching data: ${response.statusText}`);
            }
            return response.json();
        };

        const { isLoading, isError, isFetching, data, error, refetch } =
            useQuery({
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
                        relatedToParams.value // Pass relatedToParams.value here
                    );
                    return result;
                },
                staleTime: 5000,
                keepPreviousData: true,
            });

        const isCountColumn = (column) => column.includes("_count"); // Adjust as per your column naming convention

        const handleRelationshipClick = (item, column) => {
            onRelationshipClick(item, column, tableList);
        };

        const onRelationshipClick = async (item, column, tableList) => {
            console.log("onRelationshipClick called with:", {
                item,
                column,
                tableList: tableList.value,
            });

            // Validate tableList.value
            if (!Array.isArray(tableList.value)) {
                console.error(
                    "tableList.value is not an array:",
                    tableList.value
                );
                return;
            }

            if (tableList.value.length === 0) {
                console.warn("tableList.value is an empty array.");
                return;
            }

            // Extract the base name by removing '_count'
            const relatedTableBase = column.replace("_count", "");
            const relatedTable = pluralize(relatedTableBase);
            const previousTable = selectedTable.value.slice(0, -1); // Assuming plural to singular

            // Find the closest matching table name
            const finalRelatedTable = findClosestTableName(
                relatedTableBase,
                tableList.value
            );

            if (!finalRelatedTable) {
                console.warn(
                    `No related table found for base name: ${relatedTableBase}`
                );
                return;
            }

            selectedTable.value = finalRelatedTable;

            // Trigger a refetch to get fresh data
            await refetch();

            // Dynamically determine the foreign key based on the related table
            const foreignKeyField = `${previousTable}_id`; // e.g., 'user_id'

            let filterField, columnToApplyFilters;

            // Handle one-to-one and one-to-many relationships
            const isOneTable = data.value?.columns.includes(foreignKeyField);

            if (isOneTable) {
                filterField = "id"; // Filter by 'id' of the related table
                columnToApplyFilters = foreignKeyField; // Apply filters to the foreign key
                filterValues.value = {
                    [columnToApplyFilters]: item.id.toString(), // Pass the ID from the original table
                };
            } else {
                filterField = foreignKeyField;
                columnToApplyFilters = "id";
                const relatedId = item[foreignKeyField];
                filterValues.value = {
                    [columnToApplyFilters]: relatedId
                        ? relatedId.toString()
                        : "",
                };
            }

            // Set the filter parameters
            columnTypes.value[filterField] = "number"; // Define the type of the filter
            filters.value = { [columnToApplyFilters]: "equals" }; // Apply filters to the correct column

            // Reset to the first page after applying a new filter
            currentPage.value = 1;
            await refetch();
        };

        const findClosestTableName = (baseName, tableNames) => {
            let closestMatch = "";
            let highestScore = 0;

            for (const table of tableNames) {
                const score = getSimilarityScore(baseName, table);
                if (score > highestScore) {
                    highestScore = score;
                    closestMatch = table;
                }
            }
            return closestMatch;
        };

        const getSimilarityScore = (a, b) => {
            const minLength = Math.min(a.length, b.length);
            const matches = [...a].reduce((count, char, index) => {
                return count + (b[index] === char ? 1 : 0);
            }, 0);
            const score = matches / minLength;
            return score;
        };

        watch(data, (newData) => {
            if (
                newData &&
                newData.data &&
                newData.columns &&
                newData.columnTypes &&
                newData.relationshipDetails &&
                Array.isArray(newData.columns)
            ) {
                columnTypes.value = newData.columnTypes; // Assign column types from backend response
                relationshipDetails.value = newData.relationshipDetails; // Assign relationship types
            } else {
                columnTypes.value = {}; // Clear the column types if the table is empty or data is incomplete
                relationshipDetails.value = {}; // Clear relationship details if data is incomplete
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

        const toggleSort = async (column) => {
            if (sort.value.column === column) {
                // Toggle direction
                sort.value.direction =
                    sort.value.direction === "asc" ? "desc" : "asc";
            } else {
                // Set new sort column and default to ascending
                sort.value.column = column;
                sort.value.direction = "asc";
            }

            await refetch(); // Fetch again after sorting
        };

        const goToPage = (url) => {
            // Implement pagination logic
            const page = new URL(url, window.location.origin).searchParams.get(
                "page"
            );
            if (page) {
                currentPage.value = parseInt(page);
                refetch(); // Refetch data for the new page
            }
        };

        // Methods for handling temporary filter changes
        const onTempFilterChange = (column) => {
            console.log(
                `Filter type changed for column: ${column}`,
                tempFilters.value[column]
            );
            if (tempFilters.value[column] === "between") {
                tempFilterValues.value[column] = { start: "", end: "" };
            } else {
                tempFilterValues.value[column] = "";
            }
            // No refetch here; filters are applied when "Apply Filters" is clicked
        };

        const onTableChange = async (newTable) => {
    selectedTable.value = newTable;
    currentPage.value = 1; // Reset to the first page
    filters.value = {}; // Reset filters
    filterValues.value = {}; // Reset filter values
    sort.value = {
        column: null,
        direction: "asc",
    }; // Reset sorting
    searchQuery.value = ""; // Clear search query
    columnTypes.value = {}; // Clear column types so it gets recalculated
    relationshipDetails.value = {}; // Clear relationship details
    relatedToParams.value = null; // Reset relatedToParams

    // Also reset temporary filters
    tempFilters.value = {};
    tempFilterValues.value = {};

    await refetch(); // Trigger the refetch to load the new table data
};


        const onTempFilterInputChange = (column) => {
            // Ensure that tempFilterValues[column] is an object when 'between' is selected
            if (
                tempFilters.value[column] === "between" &&
                typeof tempFilterValues.value[column] !== "object"
            ) {
                tempFilterValues.value[column] = { start: "", end: "" };
            }
            console.log(
                `Filter input changed for column: ${column}`,
                tempFilterValues.value[column]
            );
            // No refetch here; filters are applied when "Apply Filters" is clicked
        };

        const applyFilters = ({
            filters: newFilters,
            filterValues: newFilterValues,
        }) => {
            // Transfer temporary filters to actual filters
            filters.value = { ...newFilters };
            filterValues.value = { ...newFilterValues };
            currentPage.value = 1; // Reset to first page
            showFilters.value = false; // Close the modal
            refetch(); // Trigger a refetch with new filters
        };

        const resetFilters = () => {
            // Clear both temporary and actual filters
            tempFilters.value = {};
            tempFilterValues.value = {};
            filters.value = {};
            sort.value = {
                column: null,
                direction: "asc",
            };
            filterValues.value = {};
            relationshipDetails.value = {}; // Clear relationship details
            relatedToParams.value = null; // Reset relatedToParams
            currentPage.value = 1; // Reset to first page
            showFilters.value = false; // Close the modal
            refetch(); // Trigger a refetch with cleared filters
        };

        const closeFilters = () => {
            showFilters.value = false;
        };

        const handleManyToManyRelationship = async (item, column) => {
            // Extract the base name by removing '_count'
            const relatedTableBase = column.replace("_count", "");
            const relatedTable = pluralize(relatedTableBase);

            // Store the current table before changing it
            const fromTable = selectedTable.value;

            // Set the selected table to the related table
            selectedTable.value = relatedTable;

            // Set up the relatedTo parameter
            relatedToParams.value = {
                id: item.id, // The ID of the item in the original table
                relationship: relatedTableBase, // The name of the relationship in the original model (singular form)
                fromTable: fromTable, // The original table
            };

            // Reset filters, sort, and search
            filters.value = {};
            filterValues.value = {};
            sort.value = {
                column: null,
                direction: "asc",
            };
            searchQuery.value = "";
            currentPage.value = 1;

            // Refetch data
            await refetch();
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
            showFilters,
            currentPage,
            onTableChange,
            toggleSort,
            goToPage,
            onSearch,
            applyFilters,
            resetFilters,
            closeFilters,
            getFilterPlaceholder,
            handleRelationshipClick,
            handleManyToManyRelationship,
        };
    },
};
</script>

<style scoped>
/* Tailwind CSS handles the styling */
</style>
