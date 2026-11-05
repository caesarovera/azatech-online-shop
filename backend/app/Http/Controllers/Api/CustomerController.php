<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Requests\IndexCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class CustomerController extends Controller
{
    public function index(IndexCustomerRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->when($request->search(), function ($q, string $search): void {
                $q->where(function ($q) use ($search): void {
                    $q->where('customer_name', 'ILIKE', "%{$search}%")
                        ->orWhere('email', 'ILIKE', "%{$search}%")
                        ->orWhere('phone', 'ILIKE', "%{$search}%");
                });
            })
            ->orderBy($request->sortColumn(), $request->sortDirection())
            ->orderBy('id')
            ->paginate($request->perPage());

        return ApiResponse::paginated($customers, CustomerResource::class);
    }

    public function store(CustomerRequest $request, DashboardService $dashboard): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        $customer = Customer::create($request->validated());
        $dashboard->forget();

        return ApiResponse::success(new CustomerResource($customer), 'Pelanggan berhasil dibuat', 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);

        return ApiResponse::success(new CustomerResource($customer));
    }

    public function update(CustomerRequest $request, Customer $customer): JsonResponse
    {
        Gate::authorize('update', $customer);

        $customer->update($request->validated());

        return ApiResponse::success(new CustomerResource($customer), 'Pelanggan berhasil diperbarui');
    }

    public function destroy(Customer $customer, DashboardService $dashboard): JsonResponse
    {
        Gate::authorize('delete', $customer);

        $customer->delete();
        $dashboard->forget();

        return response()->json(null, 204);
    }
}
