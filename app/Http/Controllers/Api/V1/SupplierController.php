<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Supplier::query();

        if ($search = $request->query('q')) {
            $query->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode_supplier', 'like', "%{$search}%");
        }

        return SupplierResource::collection($query->latest()->paginate(15));
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create($request->validated());

        return (new SupplierResource($supplier))
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $id): SupplierResource
    {
        $supplier = Supplier::findOrFail($id);
        return new SupplierResource($supplier);
    }
}
