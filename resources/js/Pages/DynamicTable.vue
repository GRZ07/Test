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
                    <option value="">Select Filter</option>
                    <option
                        v-if="columnTypes[column] === 'string'"
                        value="contains"
                    >
                        Contains
                    </option>
                    <option
                        v-if="columnTypes[column] === 'number'"
                        value="equals"
                        selected
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
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="data.data.data.length === 0">
                    <td colspan="4">No data available</td>
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
import { computed, onMounted, ref, watch } from "vue";
export default {
    setup() {
        const selectedTable = ref("users");
        const currentPage = ref(1);
        const tableList = ref([]);
        const sort = ref([]);
        const filters = ref({});
        const filterValues = ref({});
        const columnTypes = ref({});
        const searchQuery = ref("");

        const fetchTableNames = async () => {
            isLoading.value = true;
            try {
                const response = await fetch("/table-names");
                const result = await response.json();
                tableList.value = result;

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
            const sortQuery = sort.length ? `&sort=${sort.join(",")}` : "";

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
                    sort.value,
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
            // Define the related table based on the clicked column
            const relatedTableBase = column.replace("_count", ""); // e.g., "user" for "user_count"
            let relatedTable = relatedTableBase;
            let previousTable = selectedTable.value.slice(0, -1);

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

            // Determine if we're in a one-to-one or one-to-many relationship
            const isOneTable = data.value?.columns.includes(foreignKeyField);

            let filterField, columnToApplyFilters;

            let thisTable = relatedTable.slice(0, -1) + "_id";

            // Determine the relationship type
            if (isOneTable) {
                // For one-to-many relationships, set filter fields dynamically
                filterField = "id"; // Filter by 'id' of the related table
                columnToApplyFilters = foreignKeyField; // Apply filters to the foreign key
                filterValues.value = {
                    [columnToApplyFilters]: item.id.toString(), // Pass the ID from the many table
                };
            } else {
                filterField = foreignKeyField;
                columnToApplyFilters = "id";
                filterValues.value = {
                    [columnToApplyFilters]: item[thisTable].toString(),
                };
            }

            // Set the filter parameters
            columnTypes.value[filterField] = "number"; // Define the type of the filter
            filters.value = { [columnToApplyFilters]: "equals" }; // Apply filters to the correct column

            // Reset to the first page after applying a new filter
            currentPage.value = 1;
            await refetch();
        };

        const isDateString = (str) => {
            const date = new Date(str);
            return !isNaN(date.getTime());
        };

        watch(data, (newData) => {
            if (
                newData &&
                newData.data &&
                Array.isArray(newData.columns) &&
                newData["data"].data.length > 0
            ) {
                const firstItem = newData["data"].data[0];
                columnTypes.value = {}; // Reset column types

                newData["columns"].forEach((column) => {
                    if (
                        typeof firstItem[column] === "string" &&
                        isDateString(firstItem[column])
                    ) {
                        columnTypes.value[column] = "date";
                    } else if (typeof firstItem[column] === "string") {
                        columnTypes.value[column] = "string";
                    } else if (typeof firstItem[column] === "number") {
                        columnTypes.value[column] = "number";
                    }
                });
            } else {
                columnTypes.value = {}; // Clear the column types if the table is empty
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
            sort.value = []; // Reset sorting
            searchQuery.value = ""; // Clear search query
            columnTypes.value = {}; // Clear column types so it gets recalculated
            await refetch(); // This will trigger the reactivity for data automatically
        };

        const toggleSort = async (column) => {
            if (sort.value[0] === column) {
                sort.value = []; // Remove sort if already sorted
            } else {
                sort.value = [column]; // Sort by this column
            }
            await refetch(); // Fetch again after sorting
        };

        const goToPage = (url) => {
            // Implement pagination logic
            currentPage.value = new URL(url).searchParams.get("page");
            refetch(); // Refetch data for the new page
        };

        const onSearch = () => {
            currentPage.value = 1; // Reset to first page on search
            refetch(); // Trigger a refetch on search change
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
            isError: error,
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
