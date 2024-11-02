import { ref } from 'vue';

export default function usePagination(refetch) {
    const currentPage = ref(1);

    const goToPage = (url) => {
        const page = new URL(url, window.location.origin).searchParams.get("page");
        if (page) {
            currentPage.value = parseInt(page);
            refetch();
        }
    };

    return {
        currentPage,
        goToPage,
    };
}
