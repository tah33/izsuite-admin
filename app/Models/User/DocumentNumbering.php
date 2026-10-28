<?php

namespace App\Models\User;

use App\Enums\DocumentType;
use App\Enums\NumberingPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How one kind of document is numbered in a business (Doc 11 section 21): its
 * prefix, the number it starts at, how many digits the number is padded to,
 * and the period it carries. There is a row only once the business changes the
 * defaults - defaultsFor() is what it uses until then.
 */
class DocumentNumbering extends Model
{
    protected $table = 'document_numbering';

    protected $fillable = [
        'document_type',
        'prefix',
        'starting_number',
        'number_length',
        'period',
    ];

    protected $casts = [
        'document_type'   => DocumentType::class,
        'period'          => NumberingPeriod::class,
        'starting_number' => 'integer',
        'number_length'   => 'integer',
    ];

    /** A type's numbering until the business changes it: INV-00001 onwards, one continuous run. */
    public static function defaultsFor(DocumentType $type): self
    {
        return new self([
            'document_type'   => $type,
            'prefix'          => $type->defaultPrefix(),
            'starting_number' => 1,
            'number_length'   => 5,
            'period'          => NumberingPeriod::None,
        ]);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
