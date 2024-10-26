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
                </select>

                <!-- Input for date filter -->
                <input
                    v-if="
                        filters[column] &&
                        (filters[column] === 'after' ||
                            filters[column] === 'before')
                    "
                    :placeholder="getFilterPlaceholder(column)"
                    v-model="filterValues[column]"
                    @input="onFilterInputChange(column)"
                    type="date"
                />

                <!-- Input for other filters -->
                <input
                    v-if="
                        filters[column] &&
                        filters[column] !== 'after' &&
                        filters[column] !== 'before'
                    "
                    type="text"
                    v-model="filterValues[column]"
                    placeholder="Enter value..."
                    @input="onFilterInputChange(column)"
                />
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
                <tr v-if="filteredData.length === 0">
                    <td colspan="4">No data available</td>
                </tr>
                <tr v-for="(item, index) in filteredData" :key="index">
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
            const filterQuery = Object.entries(filters)
                .filter(([key, value]) => value) // Only include filters that have a value
                .map(
                    ([key]) =>
                        `&${key}=${encodeURIComponent(
                            filterValues.value[key] || ""
                        )}`
                )
                .join("");
            const searchQueryStr = search
                ? `&search=${encodeURIComponent(search)}`
                : "";

            const response = await fetch(
                `${url}?table=${table}&page=${page}${sortQuery}${filterQuery}${searchQueryStr}`
            );
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
            const relatedTableBase = column.replace("_count", "");
            let relatedTable = relatedTableBase;

            // Convert tableList from an object to an array of keys
            const tableNames = Object.values(tableList); // Use Object.values(tableList) if you want values instead of keys

            // Function to find the closest matching table name
            const findClosestTableName = (baseName, tableNames) => {
                if (!Array.isArray(tableNames)) {
                    console.error(
                        "Expected tableNames to be an array, but got:",
                        tableNames
                    );
                    return ""; // Return an empty string or handle as needed
                }

                let closestMatch = "";
                let highestScore = 0;

                for (let table of tableNames) {
                    // Compare with a basic similarity check
                    const score = getSimilarityScore(baseName, table);
                    if (score > highestScore) {
                        highestScore = score;
                        closestMatch = table;
                    }
                }

                return closestMatch;
            };

            // Basic similarity scoring function
            const getSimilarityScore = (a, b) => {
                const lengthA = a.length;
                const lengthB = b.length;
                const minLength = Math.min(lengthA, lengthB);
                const matches = [...a].reduce((count, char, index) => {
                    return count + (b[index] === char ? 1 : 0);
                }, 0);
                return matches / minLength; // Normalize by the length of the shorter string
            };

            // Find the closest matching table name
            relatedTable = findClosestTableName(relatedTableBase, tableNames);

            // Ensure relatedTable is valid
            if (!relatedTable) {
                console.error("No matching table found for:", relatedTableBase);
                return; // Handle the error as needed
            }

            const foreignKey = `${relatedTable}_id`; // Use the matched table name

            selectedTable.value = relatedTable;

            // Apply filter based on foreign key
            filters.value = { [foreignKey]: item.id };
            filterValues.value = { [foreignKey]: item.id };

            await refetch(); // Refetch data for the new table with the filter
        };

        const isDateString = (str) => {
            // Regular expression to match ISO 8601 date format
            const isoDatePattern = /^\d{4}-\d{2}-\d{2}$/;
            const shortDatePattern = /^\d{4}-\d{2}-\d{2}$/; // Matches YYYY-MM-DD
            return isoDatePattern.test(str) || shortDatePattern.test(str);
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
                    if (typeof firstItem[column] === "string") {
                        columnTypes.value[column] = "string";
                    } else if (typeof firstItem[column] === "number") {
                        columnTypes.value[column] = "number";
                    } else if (
                        typeof firstItem[column] === "string" &&
                        isDateString(firstItem[column])
                    ) {
                        columnTypes.value[column] = "date";
                    }
                });
            } else {
                columnTypes.value = {}; // Clear the column types if the table is empty
            }
        });

        const filteredData = computed(() => {
            if (!data.value || !data.value.columns || !data.value["data"].data)
                return [];

            const searchLower = searchQuery.value.toLowerCase();

            return data.value["data"].data.filter((item) => {
                const matchesSearch = Object.values(item).some((value) =>
                    String(value).toLowerCase().includes(searchLower)
                );

                const matchesFilters = Object.keys(filters.value).every(
                    (column) => {
                        const filterValue = filterValues.value[column];
                        if (!filterValue) return true; // No filter applied for this column
                        const columnType = columnTypes.value[column];

                        switch (filters.value[column]) {
                            case "contains":
                                return String(item[column])
                                    .toLowerCase()
                                    .includes(filterValue.toLowerCase());
                            case "equals":
                                return String(item[column]) === filterValue;
                            case "greaterThan":
                                return (
                                    typeof item[column] === "number" && // Ensure the type is number
                                    Number(item[column]) > Number(filterValue)
                                );
                            case "lessThan":
                                return (
                                    typeof item[column] === "number" && // Ensure the type is number
                                    Number(item[column]) < Number(filterValue)
                                );
                            case "after":
                                // Compare dates
                                return (
                                    isDateString(item[column]) &&
                                    new Date(item[column]) >
                                        new Date(filterValue)
                                );
                            case "before":
                                // Compare dates
                                return (
                                    isDateString(item[column]) &&
                                    new Date(item[column]) <
                                        new Date(filterValue)
                                );
                            default:
                                return true;
                        }
                    }
                );

                return matchesSearch && matchesFilters;
            });
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
            await refetch(); // This will trigger the reactivity for filteredData automatically
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
            refetch(); // Trigger a refetch on search change
        };

        const onFilterChange = (column) => {
            // Reset filter value when filter type changes
            filterValues.value[column] = "";
            refetch(); // Trigger a refetch on filter change
        };

        const onFilterInputChange = (column) => {
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
            filteredData,
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
