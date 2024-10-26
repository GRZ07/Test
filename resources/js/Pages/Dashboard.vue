<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { Link } from "@inertiajs/vue3";
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Dashboard for you
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        You're absolutely logged in!
                    </div>
                    <a
                        href="/users"
                        class="block w-full py-2 text-center bg-blue-500 text-white rounded-md hover:bg-blue-600"
                    >
                        Go to User List
                    </a>
                    <a
                        href="/tables"
                        class="block w-full py-2 text-center bg-blue-500 text-white rounded-md hover:bg-blue-600"
                    >
                        Go to tables List
                    </a>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
