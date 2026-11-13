<?php

namespace App\Services\Admin;

use App\Models\Billing\PaymentMethod;
use App\Models\User\User;
use App\Repositories\Admin\PaymentMethodRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Support\ImageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentMethodService
{
    public function __construct(
        protected PaymentMethodRepository $methodRepo,
        protected ImageService $imageService,
        protected CriticalActionService $criticalActions,
    ) {}

    public function getPaginated(string $type, ?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->methodRepo->getPaginated($type, $search, $perPage);
    }

    /** The methods of $type; of the online gateways, only those built (Doc 13 Phase D2). */
    public function getByType(string $type): Collection
    {
        $methods = $this->methodRepo->getByType($type);

        return $type === 'online' ? $methods->whereIn('slug', PaymentMethod::BUILT)->values() : $methods;
    }

    public function find(int $id): ?PaymentMethod
    {
        return $this->methodRepo->find($id);
    }

    /** A method the admin may open: an offline one, or an online gateway that is built. */
    public function findEditable(int $id): ?PaymentMethod
    {
        $method = $this->find($id);

        return $method && ($method->type !== 'online' || in_array($method->slug, PaymentMethod::BUILT, true)) ? $method : null;
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

    /**
     * A new offline method. An uploaded $data['image'] becomes its logo.
     * Switched on, it takes real payments at once: a critical action.
     */
    public function createOffline(array $data, User $admin): PaymentMethod
    {
        $this->confirmIf((bool) ($data['is_active'] ?? false), $admin, $data, "add the payment method \"{$data['name']}\", switched on");

        $data['type']       = 'offline';
        $data['slug']       = Str::slug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $method = $this->create($this->withLogo($data, null));

        ActivityLogService::record('created', "Created offline payment method \"{$method->name}\"", $method);

        return $method;
    }

    /**
     * An offline method's details. An uploaded $data['image'] replaces its
     * logo. Switched on before or after, it is a critical action: its
     * instructions tell businesses where to send their money.
     */
    public function updateOffline(PaymentMethod $method, array $data, User $admin): PaymentMethod
    {
        $this->confirmIf($method->takesRealPayments() || (bool) ($data['is_active'] ?? false), $admin, $data, "change the payment method \"{$method->name}\", which takes real payments");

        $data['slug']       = Str::slug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $updated = $this->update($method, $this->withLogo($data, $method->logo_url));

        ActivityLogService::record('updated', "Updated offline payment method \"{$method->name}\"", $method);

        return $updated;
    }

    /**
     * An online gateway's switches and keys (Doc 15 section 15): test and live
     * apart, a key left blank keeping the one kept. Switched on, the mode it
     * is in needs its secret key and webhook signing secret.
     */
    public function updateOnline(PaymentMethod $method, array $data, User $admin): PaymentMethod
    {
        $kept        = $method->credentials ?? [];
        $credentials = [];

        foreach (PaymentMethod::MODES as $mode) {
            foreach (array_keys(PaymentMethod::GATEWAY_FIELDS[$method->slug] ?? []) as $key) {
                $typed                    = trim((string) ($data['credentials'][$mode][$key] ?? ''));
                $credentials[$mode][$key] = $typed !== '' ? $typed : ($kept[$mode][$key] ?? null);
            }
        }

        $mode        = $data['is_sandbox'] ? 'test' : 'live';

        if ($data['is_active'] && (empty($credentials[$mode]['secret_key']) || empty($credentials[$mode]['webhook_secret']))) {
            throw ValidationException::withMessages([
                'credentials' => "Add the {$mode} secret key and webhook signing secret before switching {$method->name} on in {$mode} mode.",
            ]);
        }

        // Live before or after - switched on, in live mode - it is a critical action. Checked once the keys are.
        $liveAfter = (bool) $data['is_active'] && ! $data['is_sandbox'];
        $this->confirmIf($method->takesRealPayments() || $liveAfter, $admin, $data, "change the live payment gateway {$method->name}");

        $updated     = $this->update($method, [
            'is_active'   => $data['is_active'],
            'is_sandbox'  => $data['is_sandbox'],
            'credentials' => $credentials,
        ]);

        ActivityLogService::record('updated', "Updated online gateway \"{$method->name}\"", $method);

        return $updated;
    }

    /** Why $method cannot be deleted, if it cannot: a gateway can only be switched off. */
    public function deletionProblem(PaymentMethod $method): ?string
    {
        return $method->type === 'online' ? 'Online gateways cannot be deleted — only deactivated.' : null;
    }

    /** Deletes an offline method, and its logo. One switched on is a critical action. */
    public function deleteOffline(PaymentMethod $method, User $admin, array $confirmation): void
    {
        $this->confirmIf($method->takesRealPayments(), $admin, $confirmation, "delete the payment method \"{$method->name}\", which takes real payments");

        $name = $method->name;
        $this->imageService->deletePublic($method->logo_url);
        $this->delete($method);

        ActivityLogService::record('deleted', "Deleted offline payment method \"{$name}\"");
    }

    /**
     * A critical action (Doc 15 section 15.25) when $critical: $admin's
     * password again and a reason, from $data's confirm_password and reason,
     * which are then taken out of it.
     */
    private function confirmIf(bool $critical, User $admin, array &$data, string $what): void
    {
        if ($critical) {
            $this->criticalActions->confirm($admin, $data['confirm_password'] ?? null, $data['reason'] ?? null, $what);
        }

        unset($data['confirm_password'], $data['reason']);
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
