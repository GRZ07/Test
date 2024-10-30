<!-- src/components/PaginationControls.vue -->

<template>
    <div class="flex items-center justify-between mt-4">
        <!-- Mobile Pagination -->
        <div class="flex-1 flex justify-between sm:hidden">
            <button
                v-if="pagination.prev_page_url"
                @click="goToPreviousPage"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
                Previous
            </button>
            <button
                v-if="pagination.next_page_url"
                @click="goToNextPage"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
                Next
            </button>
        </div>
        <!-- Desktop Pagination -->
        <div
            class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between"
        >
            <div>
                <p class="text-sm text-gray-700">
                    Showing
                    <span class="font-medium">{{ pagination.from }}</span>
                    to
                    <span class="font-medium">{{ pagination.to }}</span>
                    of
                    <span class="font-medium">{{ pagination.total }}</span>
                    results
                </p>
            </div>
            <div>
                <nav
                    class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px"
                    aria-label="Pagination"
                >
                    <button
                        v-if="pagination.prev_page_url"
                        @click="goToPreviousPage"
                        class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                    >
                        <span class="sr-only">Previous</span>
                        <!-- Heroicon name: chevron-left -->
                        <svg
                            class="h-5 w-5"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button
                        v-if="pagination.next_page_url"
                        @click="goToNextPage"
                        class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                    >
                        <span class="sr-only">Next</span>
                        <!-- Heroicon name: chevron-right -->
                        <svg
                            class="h-5 w-5"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </nav>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "PaginationControls",
    props: {
        pagination: {
            type: Object,
            required: true,
        },
    },
    emits: ["go-to-page"],
    methods: {
        goToPreviousPage() {
            const prevPage = this.pagination.current_page - 1;
            this.$emit(
                "go-to-page",
                `/table-data?table=${encodeURIComponent(
                    this.pagination.table
                )}&page=${prevPage}`
            );
        },
        goToNextPage() {
            const nextPage = this.pagination.current_page + 1;
            this.$emit(
                "go-to-page",
                `/table-data?table=${encodeURIComponent(
                    this.pagination.table
                )}&page=${nextPage}`
            );
        },
    },
};
</script>

<style scoped>
/* No additional styles needed as Tailwind CSS handles styling */
</style>
