<?php

namespace App\Services\Admin;

use App\Models\Billing\PaymentMethod;
use App\Repositories\Admin\PaymentMethodRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Support\ImageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class PaymentMethodService
{
    public function __construct(
        protected PaymentMethodRepository $methodRepo,
        protected ImageService $imageService,
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

    /** A new offline method. An uploaded $data['image'] becomes its logo. */
    public function createOffline(array $data): PaymentMethod
    {
        $data['type']       = 'offline';
        $data['slug']       = Str::slug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $method = $this->create($this->withLogo($data, null));

        ActivityLogService::record('created', "Created offline payment method \"{$method->name}\"", $method);

        return $method;
    }

    /** An offline method's details. An uploaded $data['image'] replaces its logo. */
    public function updateOffline(PaymentMethod $method, array $data): PaymentMethod
    {
        $data['slug']       = Str::slug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $updated = $this->update($method, $this->withLogo($data, $method->logo_url));

        ActivityLogService::record('updated', "Updated offline payment method \"{$method->name}\"", $method);

        return $updated;
    }

    /** An online gateway's switches and credentials - all that changes on one. */
    public function updateOnline(PaymentMethod $method, array $data): PaymentMethod
    {
        $updated = $this->update($method, [
            'is_active'   => $data['is_active'],
            'is_sandbox'  => $data['is_sandbox'],
            'credentials' => $data['credentials'] ?? [],
        ]);

        ActivityLogService::record('updated', "Updated online gateway \"{$method->name}\"", $method);

        return $updated;
    }

    /** Why $method cannot be deleted, if it cannot: a gateway can only be switched off. */
    public function deletionProblem(PaymentMethod $method): ?string
    {
        return $method->type === 'online' ? 'Online gateways cannot be deleted — only deactivated.' : null;
    }

    /** Deletes an offline method, and its logo. */
    public function deleteOffline(PaymentMethod $method): void
    {
        $name = $method->name;
        $this->imageService->deletePublic($method->logo_url);
        $this->delete($method);

        ActivityLogService::record('deleted', "Deleted offline payment method \"{$name}\"");
    }

    /** $data without its upload: an uploaded image is stored as the logo instead, replacing $oldLogo. */
    private function withLogo(array $data, ?string $oldLogo): array
    {
        $image = $data['image'] ?? null;
        unset($data['image']);

        if ($image instanceof UploadedFile && $image->isValid()) {
            $data['logo_url'] = $this->imageService->storePublic($image, 'payment-methods', $oldLogo);
        }

        return $data;
    }
}
