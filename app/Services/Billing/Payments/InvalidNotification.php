<?php

namespace App\Services\Billing\Payments;

use RuntimeException;

/** A notification that does not carry the provider's valid signature: refused, nothing done. */
class InvalidNotification extends RuntimeException {}
