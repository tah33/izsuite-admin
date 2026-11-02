<?php

/*
|--------------------------------------------------------------------------
| Invitations to a business
|--------------------------------------------------------------------------
|
| Someone invited to a business (Doc 12 section 6) gets a secure link by
| e-mail. It works once, and only for this long - after that, the business
| sends it again.
|
*/

return [

    'days' => (int) env('INVITATION_DAYS', 7),

];
