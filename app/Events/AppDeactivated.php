<?php

namespace App\Events;

use App\Models\Frontend\Application;
use App\Models\User\Workspace;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A business has turned an app off, or moved it from Full back to Lite. Its
 * records stay; only access to them changes.
 */
class AppDeactivated
{
    use Dispatchable;

    public function __construct(
        public Workspace $workspace,
        public Application $app,
    ) {}
}
