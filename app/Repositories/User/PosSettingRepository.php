<?php

namespace App\Repositories\User;

use App\Models\User\PosPaymentMethod;
use App\Models\User\PosSetting;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

/** Settings → Point of Sale (Doc 18 Phase A1): its defaults, and its payment methods in their order. */
class PosSettingRepository
{
    /** What a payment method's row names: its account, as the settings show it. */
    private const METHOD_WITH = ['paymentAccount:id,name,currency,type,is_active,allow_customer_payments'];

    /** The business's settings - unsaved, with the defaults, if it has never saved them - with its walk-in customer. */
    public function forWorkspace(Workspace $workspace): PosSetting
    {
        return $workspace->posSetting()->with('walkInContact:id,name')->firstOrNew();
    }

    /** Whether the business has opened Settings → Point of Sale before: its settings were made then. */
    public function started(Workspace $workspace): bool
    {
        return $workspace->posSetting()->exists();
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function save(Workspace $workspace, array $data): PosSetting
    {
        return $workspace->posSetting()->updateOrCreate([], $data)->load('walkInContact:id,name');
    }

    /** Its payment methods in their order, each with its account. */
    public function methods(Workspace $workspace): Collection
    {
        return $workspace->posPaymentMethods()->with(self::METHOD_WITH)->orderBy('position')->orderBy('id')->get();
    }

    public function findMethod(Workspace $workspace, int $id): ?PosPaymentMethod
    {
        return $workspace->posPaymentMethods()->with(self::METHOD_WITH)->find($id);
    }

    /** Whether another of its payment methods - $exceptId aside - is called $name. */
    public function methodNameTaken(Workspace $workspace, string $name, ?int $exceptId = null): bool
    {
        return $workspace->posPaymentMethods()->where('name', $name)->when($exceptId, fn ($query, $id) => $query->whereKeyNot($id))->exists();
    }

    /** The place after its last payment method. */
    public function nextMethodPosition(Workspace $workspace): int
    {
        return (int) $workspace->posPaymentMethods()->max('position') + 1;
    }

    public function createMethod(Workspace $workspace, array $data): PosPaymentMethod
    {
        return $workspace->posPaymentMethods()->create($data)->load(self::METHOD_WITH);
    }

    public function updateMethod(PosPaymentMethod $method, array $data): PosPaymentMethod
    {
        $method->update($data);

        return $method->load(self::METHOD_WITH);
    }

    public function deleteMethod(PosPaymentMethod $method): void
    {
        $method->delete();
    }
}
