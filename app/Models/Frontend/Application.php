<?php

namespace App\Models\Frontend;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $table = 'apps';

    /** Status options shown in the admin dropdown, keyed by stored value. */
    public const STATUSES = [
        'included'    => 'Included',
        'active'      => 'Active',
        'available'   => 'Available',
        'locked'      => 'Locked',
        'coming_soon' => 'Coming Soon',
    ];

    protected $fillable = [
        'name',
        'description',
        'price',
        'logo_url',
        'category',
        'status',
        'is_active',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
