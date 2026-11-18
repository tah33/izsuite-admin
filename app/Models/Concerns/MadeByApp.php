<?php

namespace App\Models\Concerns;

use App\Models\Frontend\Application;
use App\Services\User\Apps\WorkspaceApps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A transaction that keeps the app that made it (Doc 14 section 14.5): the
 * edition of its family the business had on then - Sales Lite, say, or
 * Sales once it moves up - kept by its catalog id. The model names its
 * family in APP_FAMILY.
 */
trait MadeByApp
{
    public static function bootMadeByApp(): void
    {
        static::creating(function (Model $model) {
            if ($model->source_app_id === null && $model->workspace) {
                $model->source_app_id = WorkspaceApps::for($model->workspace)->row(static::APP_FAMILY)?->app_id;
            }
        });
    }

    public function sourceApp(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'source_app_id');
    }
}
