<?php

namespace App\Events;

use App\Models\Frontend\Application;
use App\Models\User\Workspace;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A business has turned an app on, or replaced its Lite edition with Full.
 *
 * Where an app's own first-use setup belongs - numbering series, default
 * settings, dashboard widgets - once the app modules exist. Such setup must be
 * safe to run twice: an app can be turned off and on again.
 */
class AppActivated
{
    use Dispatchable;

    public function __construct(
        public Workspace $workspace,
        public Application $app,
    ) {}
}
