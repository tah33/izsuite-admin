<?php

/*
|--------------------------------------------------------------------------
| Business files
|--------------------------------------------------------------------------
|
| The files a business keeps - contact attachments, item images, digital
| product files and its logo - and where they are stored. Each is a row of
| workspace_files that records the disk it is on, so switching where new
| uploads go never breaks one already stored.
|
*/

return [

    /*
     | Where new uploads go: 'local' (this server's disk) or 'spaces'
     | (DigitalOcean Spaces - set the DO_SPACES_* keys first). Files already
     | stored stay where they are; `php artisan files:move --to=spaces` (or
     | --to=local) moves them across.
     */
    'storage' => env('FILES_DISK', 'local'),

    /* The Laravel disks behind each choice, for private and for public files. */
    'disks' => [
        'local'  => ['private' => 'local', 'public' => 'public'],
        'spaces' => ['private' => 'spaces', 'public' => 'spaces'],
    ],

    /* How long a download link to a private file works, in minutes. */
    'link_minutes' => (int) env('FILES_LINK_MINUTES', 5),

    /* A business is warned once it has used this share of its storage (Doc 13 section 13.14). */
    'warning_percent' => 80,

];
