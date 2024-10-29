<template>
    <div>
        <h1>Dynamic Table Viewer</h1>

        <!-- Dropdown to select table -->
        <select v-model="selectedTable" @change="onTableChange">
            <option v-for="table in tableList" :key="table" :value="table">
                {{ table }}
            </option>
        </select>

        <!-- Loading state -->
        <div v-if="isLoading">Loading data, please wait...</div>
        <div v-if="isFetching && !isLoading">Updating data...</div>
        <div v-if="isError">Error loading data: {{ error.message }}</div>

        <!-- Central Search Input -->
        <div v-if="data && data.data && !isError">
            <input
                type="text"
                v-model="searchQuery"
                placeholder="Search all columns..."
                @input="onSearch"
            />
        </div>

        <!-- Filters Button -->
        <div
            v-if="data && data.data && !isError"
            class="filters-button-container"
        >
            <button @click="showFilters = true">Filters</button>
        </div>

        <!-- Filters Modal -->
        <div
            v-if="showFilters"
            class="modal-overlay"
            @click.self="closeFilters"
        >
            <div class="modal">
                <h2>Apply Filters</h2>
                <div class="filters">
                    <div
                        v-for="(column, index) in data.columns"
                        :key="index"
                        class="filter"
                    >
                        <label :for="'filter-' + column">{{ column }}</label>
                        <select
                            v-model="tempFilters[column]"
                            :id="'filter-' + column"
                            @change="onTempFilterChange(column)"
                        >
                            <option value="" selected>Select Filter</option>
                            <option
                                v-if="columnTypes[column] === 'string'"
                                value="contains"
                            >
                                Contains
                            </option>
                            <option
                                v-if="columnTypes[column] === 'number'"
                                value="equals"
                            >
                                Equals
                            </option>
                            <option
                                v-if="columnTypes[column] === 'number'"
                                value="greaterThan"
                            >
                                Greater than
                            </option>
                            <option
                                v-if="columnTypes[column] === 'number'"
                                value="lessThan"
                            >
                                Less than
                            </option>
                            <option
                                v-if="columnTypes[column] === 'date'"
                                value="after"
                            >
                                After
                            </option>
                            <option
                                v-if="columnTypes[column] === 'date'"
                                value="before"
                            >
                                Before
                            </option>
                            <option
                                v-if="columnTypes[column] === 'date'"
                                value="between"
                            >
                                Between
                            </option>
                            <!-- Add 'between' option for number columns -->
                            <option
                                v-if="columnTypes[column] === 'number'"
                                value="between"
                            >
                                Between
                            </option>
                        </select>

                        <!-- Input for 'contains' -->
                        <input
                            v-if="tempFilters[column] === 'contains'"
                            type="text"
                            v-model="tempFilterValues[column]"
                            placeholder="Enter value..."
                        />

                        <!-- Input for numerical filters -->
                        <input
                            v-if="
                                tempFilters[column] &&
                                (tempFilters[column] === 'greaterThan' ||
                                    tempFilters[column] === 'lessThan' ||
                                    tempFilters[column] === 'equals')
                            "
                            type="number"
                            v-model="tempFilterValues[column]"
                            placeholder="Enter numeric value..."
                        />

                        <!-- Input for date and number 'between' filters -->
                        <div v-if="tempFilters[column] === 'between'">
                            <input
                                :placeholder="
                                    getFilterPlaceholder(column, 'start')
                                "
                                v-model="tempFilterValues[column].start"
                                @input="onTempFilterInputChange(column)"
                                :type="
                                    columnTypes[column] === 'date'
                                        ? 'date'
                                        : 'number'
                                "
                            />
                            <input
                                :placeholder="
                                    getFilterPlaceholder(column, 'end')
                                "
                                v-model="tempFilterValues[column].end"
                                @input="onTempFilterInputChange(column)"
                                :type="
                                    columnTypes[column] === 'date'
                                        ? 'date'
                                        : 'number'
                                "
                            />
                        </div>
                    </div>
                </div>
                <!-- Apply and Reset Filter Buttons -->
                <div class="filter-buttons">
                    <button @click="applyFilters">Apply Filters</button>
                    <button @click="resetFilters">Reset Filters</button>
                    <button @click="closeFilters">Close</button>
                </div>
            </div>
        </div>

        <!-- Dynamic Table -->
        <table v-if="data && data.data && data.columns && !isError">
            <thead>
                <tr>
                    <th
                        v-for="(value, index) in data.columns"
                        :key="index"
                        @click="toggleSort(value)"
                    >
                        {{ value }}
                        <span v-if="sort.column === value">
                            <span v-if="sort.direction === 'asc'">▲</span>
                            <span v-else>▼</span>
                        </span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="data.data.data.length === 0">
                    <td :colspan="data.columns.length">No data available</td>
                </tr>
                <tr v-for="(item, index) in data.data.data" :key="index">
                    <td v-for="(value, column) in item" :key="column">
                        <button
                            v-if="isCountColumn(column)"
                            @click="
                                handleRelationshipClick(item, column, tableList)
                            "
                        >
                            {{ value }}
                        </button>
                        <span v-else>
                            {{ value }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination Controls -->
        <div class="pagination" v-if="data && data.data && !isError">
            <button
                v-if="data['data'].prev_page_url"
                @click="goToPage(data['data'].prev_page_url)"
            >
                Previous
            </button>
            <button
                v-if="data['data'].next_page_url"
                @click="goToPage(data['data'].next_page_url)"
            >
                Next
            </button>
            {{ data["relationshipDetails"]["roles"] }}
        </div>
    </div>
</template>

<script>
import { useQuery } from "@tanstack/vue-query";
import pluralize from "pluralize";
import { ref, onMounted, watch } from "vue";
import { debounce } from "lodash";
import { pull } from "lodash";

export default {
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

        // Temporary filters for the filter form
        const tempFilters = ref({});
        const tempFilterValues = ref({});

        // Relationship details
        const relationshipDetails = ref({});
        const relatedToParams = ref(null); // Add this line

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
                .filter(
                    ([key, type]) =>
                        type &&
                        filterValues.value[key] !== "" &&
                        filterValues.value[key] !== null &&
                        ((type === "between" &&
                            filterValues.value[key].start &&
                            filterValues.value[key].end) ||
                            type !== "between")
                )
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

        const onRelationshipClick = async (item, column, tableList) => {
            // This function replaces 'onCountColumnClick' and is related to relationships
            filters.value = {};
            filterValues.value = {};
            columnTypes.value = {};

            // Determine the relationship type from relationshipDetails

            // Extract the base name by removing '_count'
            const relatedTableBase = column.replace("_count", "");

            let relatedTable = pluralize(relatedTableBase);
            let previousTable = selectedTable.value.slice(0, -1); // Assuming plural to singular

            // Get the list of table names
            const tableNames = Object.values(tableList);

            // Find the closest matching table name
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

            // Find the related table based on the clicked column
            relatedTable = findClosestTableName(relatedTableBase, tableNames);

            if (!relatedTable) {
                return;
            }

            selectedTable.value = relatedTable;

            // Trigger a refetch to get fresh data
            await refetch();

            // Dynamically determine the foreign key based on the related table
            const foreignKeyField = `${previousTable}_id`; // Ensures the foreign key is named as "{relatedTableBase}_id" with singular noun

            let filterField, columnToApplyFilters;

            let thisTable = relatedTable.slice(0, -1) + "_id";

            console.log(
                "out thisColumn",
                columnToApplyFilters,
                "foreignKeyField",
                foreignKeyField,
                "thisTable",
                thisTable
            );

            // Handle one-to-one and one-to-many relationships
            const isOneTable = data.value?.columns.includes(foreignKeyField);

            if (isOneTable) {
                filterField = "id"; // Filter by 'id' of the related table
                columnToApplyFilters = foreignKeyField; // Apply filters to the foreign key
                console.log(
                    "1:1 thisColumn",
                    columnToApplyFilters,
                    "foreignKeyField",
                    foreignKeyField
                );
                filterValues.value = {
                    [columnToApplyFilters]: item.id.toString(), // Pass the ID from the many table
                };
            } else {
                filterField = foreignKeyField;
                columnToApplyFilters = "id";
                console.log(
                    "else thisColumn",
                    columnToApplyFilters,
                    "foreignKeyField",
                    foreignKeyField,
                    "thisTable",
                    thisTable
                );
                // Ensure that item[thisTable] exists
                const relatedId = item[thisTable];
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

        const onTableChange = async () => {
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

            await refetch(); // This will trigger the reactivity for data automatically
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

        const applyFilters = () => {
            // Transfer temporary filters to actual filters
            filters.value = { ...tempFilters.value };
            filterValues.value = { ...tempFilterValues.value };
            currentPage.value = 1; // Reset to first page
            showFilters.value = false; // Close the modal
            refetch(); // Trigger a refetch with new filters
        };

        const resetFilters = () => {
            // Clear both temporary and actual filters
            tempFilters.value = {};
            tempFilterValues.value = {};
            filters.value = {};
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

        const handleRelationshipClick = (item, column, tableList) => {
            let columnValue = column.replace("_count", "");
            columnValue = pluralize(columnValue); // Ensure it's plural

            // Correctly access relationshipDetails using .value
            const relationshipType = relationshipDetails.value;
            const relationship = relationshipType[columnValue];

            if (relationship === "many-to-many") {
                console.log("Many-to-many relationship handled differently");
                // Implement your logic for many-to-many relationships here
                handleManyToManyRelationship(item, column, tableList);
            } else {
                console.log("Single-to-many: ", relationship);
                onRelationshipClick(item, column, tableList);
            }
        };

        const handleManyToManyRelationship = async (
            item,
            column,
            tableList
        ) => {
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
            onTableChange,
            toggleSort,
            goToPage,
            onSearch,
            applyFilters,
            resetFilters,
            closeFilters,
            getFilterPlaceholder,
            isCountColumn: isCountColumn,
            onRelationshipClick,
            showFilters,
            onTempFilterChange,
            onTempFilterInputChange,
            handleRelationshipClick,
            handleManyToManyRelationship,
        };
    },
};
</script>

<style scoped>
.table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    border: 1px solid #ccc;
    padding: 8px;
    text-align: left;
}

th {
    cursor: pointer;
    background-color: #f4f4f4;
}

.filters-button-container {
    margin-top: 1em;
}

.filters {
    margin-bottom: 1em;
    display: flex;
    flex-wrap: wrap;
}

.filter {
    margin-right: 1em;
    margin-bottom: 0.5em;
}

.filter-buttons {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}

.pagination {
    margin-top: 1em;
}

button {
    padding: 5px 10px;
    cursor: pointer;
}

/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal {
    background: white;
    padding: 20px;
    border-radius: 8px;
    width: 80%;
    max-width: 600px;
    max-height: 80%;
    overflow-y: auto;
}

.modal h2 {
    margin-top: 0;
}

.modal .filters {
    display: flex;
    flex-direction: column;
}

.modal .filter {
    margin-bottom: 1em;
}

.modal .filter-buttons {
    justify-content: flex-end;
}
</style>
