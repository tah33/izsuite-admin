<?php

namespace App\Exceptions;

use RuntimeException;

/** What an e-mail was to carry is no longer there - a file removed, a document deleted - so it is not sent without it. */
class AttachmentMissing extends RuntimeException {}
