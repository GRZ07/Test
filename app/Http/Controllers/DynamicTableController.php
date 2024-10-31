<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\DynamicTableService;
use App\Http\Requests\GetTableDataRequest;

class DynamicTableController extends Controller
{
    protected $dynamicTableService;

    public function __construct(DynamicTableService $dynamicTableService)
    {
        $this->dynamicTableService = $dynamicTableService;
    }

    /**
     * Retrieve all table names mapped to their respective models.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllTableNames()
    {
        $modelMap = $this->dynamicTableService->getAllModelTableNames();
        return response()->json($modelMap);
    }

    /**
     * Retrieve table data with dynamic filtering, sorting, and pagination.
     *
     * @param GetTableDataRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTableData(GetTableDataRequest $request)
    {
        try {
            $tableData = $this->dynamicTableService->getTableData($request);
            return response()->json($tableData);
        } catch (\Exception $e) {
            Log::error('Error retrieving table data: ' . $e->getMessage());
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['error' => $e->getMessage()], $status);
        }
    }
}
