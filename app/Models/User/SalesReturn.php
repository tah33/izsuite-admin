<?php

namespace App\Models\User;

use App\Models\Concerns\HasFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer return of a sales order's delivered goods (Doc 16 section
 * 16.17), from the order or one of its deliveries: why it came back, in what
 * condition, and what is done about it. Draft; Submitted (numbered RET-);
 * Authorised or Rejected; Received, when the goods came back; Completed - or
 * Cancelled before it was received. Received and completed ones count as the
 * order lines' returned quantities. Its photos and papers are its files.
 */
class SalesReturn extends Model
{
    use HasFiles;

    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const AUTHORISED = 'authorised';

    public const REJECTED = 'rejected';

    public const RECEIVED = 'received';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    /** Those whose quantities are held against what was delivered: all but rejected and cancelled. */
    public const HOLDING = [self::DRAFT, self::SUBMITTED, self::AUTHORISED, self::RECEIVED, self::COMPLETED];

    /** Those whose goods came back. */
    public const BACK = [self::RECEIVED, self::COMPLETED];

    public const CONDITIONS = ['unopened', 'opened', 'damaged', 'defective'];

    public const RESOLUTIONS = ['refund', 'credit_note', 'replacement', 'repair', 'none'];

    protected $fillable = [
        'number',
        'status',
        'sales_document_id',
        'sales_delivery_id',
        'location_id',
        'return_date',
        'reason',
        'condition',
        'resolution',
        'notes',
        'status_reason',
        'received_on',
        'submitted_at',
        'decided_at',
        'received_at',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'status' => self::DRAFT,
    ];

    protected $casts = [
        'sales_document_id' => 'integer',
        'sales_delivery_id' => 'integer',
        'location_id'       => 'integer',
        'submitted_at'      => 'datetime',
        'decided_at'        => 'datetime',
        'received_at'       => 'datetime',
        'completed_at'      => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The sales order its goods were delivered on. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(SalesDelivery::class, 'sales_delivery_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesReturnLine::class)->orderBy('id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
