import { ref } from 'vue';

export default function useSort(refetch) {
    const sort = ref({ column: null, direction: 'asc' });

    const toggleSort = async (column) => {
        if (sort.value.column === column) {
            sort.value.direction = sort.value.direction === 'asc' ? 'desc' : 'asc';
        } else {
            sort.value.column = column;
            sort.value.direction = 'asc';
        }
        refetch();
    };

    return {
        sort,
        toggleSort,
    };
}
