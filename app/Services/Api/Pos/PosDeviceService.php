<?php

namespace App\Services\Api\Pos;

use App\Models\User\PosDevice;
use App\Models\User\PosRegister;
use App\Models\User\Workspace;
use App\Repositories\User\PosDeviceRepository;
use App\Repositories\User\PosRegisterRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Documents\DocumentPdf;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * A register's devices (Doc 18 section 18.30 - Phase I3): its receipt
 * printers, barcode scanners, cash drawers and customer displays - each
 * named, with how and where it is connected, in use or switched off - at a
 * register of a location the user may use. A printer's test is a test page
 * its browser prints; a scanner's, a code it reads; any other's is said to
 * be done. Each keeps when it was last used - a receipt printed, an item
 * scanned at its register - and last tested. Changes are logged.
 */
class PosDeviceService
{
    use RefusesConflicts;

    public function __construct(
        protected PosRegisterRepository $registers,
        protected PosDeviceRepository $devices,
        protected DocumentPdf $documentPdf,
        protected WorkspaceAccess $access,
        protected BusinessCalendar $calendar,
    ) {}

    /** @return array{register: PosRegister, devices: Collection} */
    public function list(Workspace $workspace, int $registerId): array
    {
        $register = $this->register($workspace, $registerId);

        return ['register' => $register, 'devices' => $this->devices->forRegister($register)];
    }

    /** $data is validated (see SavePosDeviceRequest). */
    public function create(Workspace $workspace, int $registerId, array $data): PosDevice
    {
        $register = $this->register($workspace, $registerId);
        $device   = $this->devices->create($register, $this->fields($data));

        ActivityLogService::record('created', "Added the {$device->kind()} \"{$device->name}\" to the register \"{$register->name}\" ({$register->code})", $register, ['source' => 'api']);

        return $device;
    }

    /** $data is validated (see SavePosDeviceRequest). */
    public function update(Workspace $workspace, int $registerId, int $deviceId, array $data): PosDevice
    {
        $register = $this->register($workspace, $registerId);
        $device   = $this->device($register, $deviceId);

        if ($this->devices->update($device, $this->fields($data))) {
            ActivityLogService::record('updated', "Updated the {$device->kind()} \"{$device->name}\" of the register \"{$register->name}\" ({$register->code})", $register, ['source' => 'api']);
        }

        return $device;
    }

    public function delete(Workspace $workspace, int $registerId, int $deviceId): PosDevice
    {
        $register = $this->register($workspace, $registerId);
        $device   = $this->device($register, $deviceId);

        $this->devices->delete($device);
        ActivityLogService::record('deleted', "Removed the {$device->kind()} \"{$device->name}\" from the register \"{$register->name}\" ({$register->code})", $register, ['source' => 'api']);

        return $device;
    }

    /**
     * Its test, now: a scanner's with the code it read; marked tested.
     *
     * @return array{device: PosDevice, read: ?string}
     */
    public function test(Workspace $workspace, int $registerId, int $deviceId, ?string $scanned): array
    {
        $register = $this->register($workspace, $registerId);
        $device   = $this->device($register, $deviceId);
        $scanned  = trim((string) $scanned) ?: null;

        if (! $device->is_active) {
            $this->refuse("{$device->name} is switched off: switch it on to test it.");
        }

        if ($device->type === PosDevice::BARCODE_SCANNER && $scanned === null) {
            throw ValidationException::withMessages(['scanned' => 'Scan a barcode with it to test it.']);
        }

        $this->devices->mark($device, ['last_tested_at' => now()]);
        ActivityLogService::record('updated', "Tested the {$device->kind()} \"{$device->name}\" of the register \"{$register->name}\" ({$register->code})".($scanned ? ": it read {$scanned}" : ''), $register, ['source' => 'api']);

        return ['device' => $device, 'read' => $scanned];
    }

    /** @return array{0: string, 1: string} a receipt printer's test page: its file name and the PDF */
    public function testPage(Workspace $workspace, int $registerId, int $deviceId): array
    {
        $register = $this->register($workspace, $registerId);
        $device   = $this->device($register, $deviceId);

        if ($device->type !== PosDevice::RECEIPT_PRINTER) {
            $this->refuse("A test page is a receipt printer's: {$device->name} is a {$device->kind()}.");
        }

        $now = now($this->calendar->timezone($workspace));

        return $this->documentPdf->posDeviceTest($workspace, $register, $device, $now->format(($workspace->date_format ?: 'd M Y').' H:i'));
    }

    private function fields(array $data): array
    {
        return [
            'type'       => $data['type'],
            'name'       => trim($data['name']),
            'connection' => $data['connection'],
            'address'    => trim((string) ($data['address'] ?? '')) ?: null,
            'is_active'  => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function register(Workspace $workspace, int $id): PosRegister
    {
        return $this->registers->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'There is no such register.');
    }

    private function device(PosRegister $register, int $id): PosDevice
    {
        return $this->devices->find($register, $id) ?? abort(404, 'There is no such device at this register.');
    }
}
