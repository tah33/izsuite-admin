<?php

namespace App\Models\Frontend;

use App\Enums\Edition;
use App\Models\Concerns\BumpsCatalogVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing an app needs turned on first: a family, at least at min_edition.
 */
class AppDependency extends Model
{
    use BumpsCatalogVersion;

    protected $fillable = [
        'app_id',
        'requires_family',
        'min_edition',
    ];

    protected $casts = [
        'min_edition' => Edition::class,
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'app_id');
    }
}
