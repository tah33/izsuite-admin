<?php

namespace App\Http\Requests\Api\Auth\Concerns;

/**
 * Keeps secrets out of the URL on the unauthenticated auth endpoints.
 *
 * Laravel's input() reads the query string as well as the body, so without this
 * a caller could sign in - or sign up - via POST /auth/login?email=..&password=..
 *
 * A URL is not a private channel: it is written verbatim into web-server access
 * logs, browser history, bookmarks and outgoing Referer headers, so a password
 * sent that way is a password on disk in half a dozen places.
 *
 * Refusing the request is deliberate. Quietly ignoring the parameters would
 * leave a client "working" while still putting secrets in URLs, and the leak
 * would only surface later in a log file.
 */
trait RejectsCredentialsInQueryString
{
    /**
     * @param  array<int, string>  $fields  Names that may only arrive in the body.
     */
    protected function rejectCredentialsInQueryString(array $fields): void
    {
        // Matches on the parameter name, not its value: `?password` with nothing
        // after it is still a client that will one day send a real one.
        $offending = array_intersect($fields, array_keys($this->query->all()));

        if ($offending === []) {
            return;
        }

        abort(400, sprintf(
            'Send %s in the request body, not the query string.',
            implode(' and ', $offending),
        ));
    }
}
