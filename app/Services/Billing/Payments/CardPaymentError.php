<?php

namespace App\Services\Billing\Payments;

use RuntimeException;

/** The card payment provider could not be reached, or refused what was asked: its own message, for the log. */
class CardPaymentError extends RuntimeException {}
