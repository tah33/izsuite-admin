<?php

namespace App\Repositories\User;

use App\Models\User\PosDevice;
use App\Models\User\PosRegister;
use Illuminate\Support\Collection;

/** A register's devices (Doc 18 Phase I3). */
class PosDeviceRepository
{
    /** Its devices, by kind and name. */
    public function forRegister(PosRegister $register): Collection
    {
        return PosDevice::query()->where('pos_register_id', $register->id)->orderBy('type')->orderBy('name')->orderBy('id')->get();
    }

    public function find(PosRegister $register, int $id): ?PosDevice
    {
        return PosDevice::query()->where('pos_register_id', $register->id)->find($id);
    }

    public function create(PosRegister $register, array $data): PosDevice
    {
        $device = new PosDevice($data);
        $device->forceFill(['workspace_id' => $register->workspace_id, 'pos_register_id' => $register->id])->save();

        return $device;
    }

    /** Its fields; whether any of them changed. */
    public function update(PosDevice $device, array $data): bool
    {
        $device->fill($data);
        $changed = $device->isDirty();
        $device->save();

        return $changed;
    }

    public function delete(PosDevice $device): void
    {
        $device->delete();
    }

    public function mark(PosDevice $device, array $changes): void
    {
        $device->forceFill($changes)->save();
    }

    /** The register $registerId's devices of $type in use, used now. */
    public function used(int $registerId, string $type): void
    {
        PosDevice::query()->where('pos_register_id', $registerId)->where('type', $type)->where('is_active', true)->update(['last_used_at' => now()]);
    }
}
