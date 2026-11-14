<?php

namespace App\Jobs\Concerns;

/**
 * A queued job Admin > Service Health may run again once it has failed (Doc
 * 15 section 15.23 - Phase D3a): it checks before it acts, so a second run
 * never sends, adds or charges twice. A job without it - a queued mail,
 * which may have gone out, or an import - is never retried there.
 */
interface SafeToRetry {}
