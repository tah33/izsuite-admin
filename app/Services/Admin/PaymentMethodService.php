<?php

namespace App\Services\Admin;

use App\Models\Billing\PaymentMethod;
use App\Repositories\Admin\PaymentMethodRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PaymentMethodService
{
    public function __construct(
        protected PaymentMethodRepository $methodRepo,
    ) {}

    public function getPaginated(string $type, ?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->methodRepo->getPaginated($type, $search, $perPage);
    }

    public function getByType(string $type): Collection
    {
        return $this->methodRepo->getByType($type);
    }

    public function find(int $id): ?PaymentMethod
    {
        return $this->methodRepo->find($id);
    }

    public function create(array $data): PaymentMethod
    {
        return $this->methodRepo->create($data);
    }

    public function update(PaymentMethod $method, array $data): PaymentMethod
    {
        return $this->methodRepo->update($method, $data);
    }

    public function delete(PaymentMethod $method): bool
    {
        return $this->methodRepo->delete($method);
    }
}
