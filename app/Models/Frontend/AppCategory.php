<?php

namespace App\Models\Frontend;

use App\Models\Concerns\BumpsCatalogVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group in the Apps Center - Sales & Commerce, Finance & Accounting, ... An
 * app belongs to one. One that still has apps cannot be deleted; set it
 * inactive instead.
 */
class AppCategory extends Model
{
    use BumpsCatalogVersion, HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function apps(): HasMany
    {
        return $this->hasMany(Application::class, 'category_id');
    }
}
