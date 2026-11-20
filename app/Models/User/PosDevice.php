<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A device registered at a POS register (Doc 18 section 18.30 - Phase I3): a
 * receipt printer, a barcode scanner, a cash drawer or a customer display -
 * its name, how it is connected and where, whether it is in use, and when it
 * was last used and last tested. Switched off, it is kept but not used.
 */
class PosDevice extends Model
{
    public const RECEIPT_PRINTER = 'receipt_printer';

    public const BARCODE_SCANNER = 'barcode_scanner';

    public const CASH_DRAWER = 'cash_drawer';

    public const CUSTOMER_DISPLAY = 'customer_display';

    public const TYPES = [
        self::RECEIPT_PRINTER  => 'receipt printer',
        self::BARCODE_SCANNER  => 'barcode scanner',
        self::CASH_DRAWER      => 'cash drawer',
        self::CUSTOMER_DISPLAY => 'customer display',
    ];

    /** How a device reaches the register: through the browser's own printing or typing, or USB, the network or Bluetooth. */
    public const CONNECTIONS = ['browser', 'usb', 'network', 'bluetooth'];

    protected $fillable = [
        'type',
        'name',
        'connection',
        'address',
        'is_active',
    ];

    protected $casts = [
        'pos_register_id' => 'integer',
        'is_active'       => 'boolean',
        'last_used_at'    => 'datetime',
        'last_tested_at'  => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function register(): BelongsTo
    {
        return $this->belongsTo(PosRegister::class, 'pos_register_id');
    }

    /** "receipt printer". */
    public function kind(): string
    {
        return self::TYPES[$this->type] ?? 'device';
    }
}
