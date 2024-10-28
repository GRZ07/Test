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

        <!-- Column Filters -->
        <div v-if="data && data.data && !isError" class="filters">
            <div
                v-for="(column, index) in data.columns"
                :key="index"
                class="filter"
            >
                <label :for="'filter-' + column">{{ column }}</label>
                <select
                    v-model="filters[column]"
                    :id="'filter-' + column"
                    @change="onFilterChange(column)"
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
                    <option v-if="columnTypes[column] === 'date'" value="after">
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
                </select>
                <input
                    v-if="filters[column] && filters[column] === 'contains'"
                    type="string"
                    v-model="filterValues[column]"
                    placeholder="Enter value..."
                    @input="onFilterInputChange(column)"
                />
                <!-- Input for other filter -->
                <input
                    v-if="
                        filters[column] &&
                        (filters[column] === 'greaterThan' ||
                            filters[column] === 'lessThan' ||
                            filters[column] === 'equals')
                    "
                    type="number"
                    v-model="filterValues[column]"
                    placeholder="Enter numeric value..."
                    @input="onFilterInputChange(column)"
                />

                <!-- Input for date filters -->
                <div v-if="filters[column]">
                    <input
                        v-if="
                            filters[column] === 'after' ||
                            filters[column] === 'before'
                        "
                        :placeholder="getFilterPlaceholder(column)"
                        v-model="filterValues[column]"
                        @input="onFilterInputChange(column)"
                        type="date"
                    />

                    <div v-else-if="filters[column] === 'between'">
                        <input
                            :placeholder="
                                'Start ' + getFilterPlaceholder(column)
                            "
                            v-model="filterValues[column].start"
                            @input="onFilterInputChange(column)"
                            type="date"
                        />
                        <input
                            :placeholder="'End ' + getFilterPlaceholder(column)"
                            v-model="filterValues[column].end"
                            @input="onFilterInputChange(column)"
                            type="date"
                        />
                    </div>
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
                            @click="onCountColumnClick(item, column, tableList)"
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
        </div>
    </div>
</template>

<script>
import { useQuery } from "@tanstack/vue-query";
import { ref, onMounted, watch } from "vue";
import { debounce } from "lodash";

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
                selectedTable.value = result.includes("users")
                    ? "users"
                    : result[0] || "";
            } catch (err) {
                error.value = err;
            } finally {
                isLoading.value = false;
            }
        };

        onMounted(() => {
            fetchTableNames(); // Fetch table names when the component is mounted
        });

        const fetcher = async (url, table, sort, filters, search, page) => {
            let sortQuery = "";
            if (sort.column) {
                sortQuery =
                    sort.direction === "desc"
                        ? `&sort=-${sort.column}`
                        : `&sort=${sort.column}`;
            }

            // Build filter query with type and value
            const filterQuery = Object.entries(filters)
                .filter(
                    ([key, type]) =>
                        type &&
                        filterValues.value[key] !== "" &&
                        filterValues.value[key] !== null
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

            const finalUrl = `${url}?table=${table}&page=${page}${sortQuery}${filterQuery}${searchQueryStr}`;

            console.log("Fetching data with URL:", finalUrl); // Debugging line

            const response = await fetch(finalUrl);
            return response.json();
        };

        const { isLoading, isError, isFetching, data, error, refetch } =
            useQuery({
                queryKey: [
                    "dynamicTable",
                    selectedTable.value,
                    sort.value.column,
                    sort.value.direction,
                    filters.value,
                    searchQuery.value,
                    currentPage.value,
                ],
                queryFn: async () => {
                    const result = await fetcher(
                        "/table-data",
                        selectedTable.value,
                        sort.value,
                        filters.value,
                        searchQuery.value,
                        currentPage.value
                    );
                    return result;
                },
                staleTime: 5000,
                keepPreviousData: true,
            });

        const isCountColumn = (column) => column.includes("_count"); // Adjust as per your column naming convention

        const onCountColumnClick = async (item, column, tableList) => {
            filters.value = {};
            filterValues.value = {};
            columnTypes.value = {};

            // Determine the relationship type from relationshipDetails
            const relationshipType = data.value.relationshipDetails
                ? data.value.relationshipDetails[column]
                : null;

            // Extract the base name by removing '_count'
            const relatedTableBase = column.replace("_count", "");

            let relatedTable = relatedTableBase;
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

            if (relationshipType === "many-to-many") {
                // Handle many-to-many relationship
                // Assume pivot table is named alphabetically or follow a naming convention
                // Example: for users and roles, pivot table is role_user

                // Find the pivot table
                const pivotTable = tableNames.find(
                    (tbl) =>
                        tbl === `${relatedTableBase}_pivot` ||
                        tbl === `${relatedTableBase}_${previousTable}` ||
                        tbl === `${previousTable}_${relatedTableBase}`
                );

                if (!pivotTable) {
                    console.warn(`Pivot table for relationship not found.`);
                    return;
                }

                // Determine the foreign key in the pivot table
                const relatedForeignKey = `${previousTable}_id`;
                const pivotForeignKey = `${relatedTableBase}_id`;

                // Apply filters based on pivot table
                filterField = pivotForeignKey;
                columnToApplyFilters = filterField;

                filterValues.value = {
                    [columnToApplyFilters]: item.id.toString(), // Pass the ID from the current table
                };
            } else {
                // Handle one-to-one and one-to-many relationships
                const isOneTable =
                    data.value?.columns.includes(foreignKeyField);

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
                    filterValues.value = {
                        [columnToApplyFilters]: item[thisTable].toString(),
                    };
                }
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
                Array.isArray(newData.columns)
            ) {
                columnTypes.value = newData.columnTypes; // Assign column types from backend response
            } else {
                columnTypes.value = {}; // Clear the column types if the table is empty or data is incomplete
            }
        });

        const getFilterPlaceholder = (column) => {
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
            const page = new URL(url).searchParams.get("page");
            if (page) {
                currentPage.value = parseInt(page);
                refetch(); // Refetch data for the new page
            }
        };

        const onFilterChange = (column) => {
            if (filters.value[column] === "between") {
                filterValues.value[column] = { start: "", end: "" };
            } else {
                filterValues.value[column] = "";
            }
            currentPage.value = 1; // Reset to first page
            refetch(); // Trigger a refetch on filter change
        };

        const onFilterInputChange = (column) => {
            currentPage.value = 1; // Reset to first page
            refetch(); // Trigger a refetch on filter input change
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
            filters,
            filterValues,
            columnTypes,
            onTableChange,
            toggleSort,
            goToPage,
            onSearch,
            onFilterChange,
            onFilterInputChange,
            getFilterPlaceholder,
            isCountColumn,
            onCountColumnClick,
            sort,
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

.filters {
    margin-bottom: 1em;
}

.filter {
    margin-right: 1em;
}

.pagination {
    margin-top: 1em;
}
</style>
