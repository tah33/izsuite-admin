<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Every file a business keeps (Doc 03 section 5, Doc 04 sections 6 and 12):
 * a contact's attachments, an item's image, a digital product's files and the
 * business's logo. Each row belongs to one record - its attachable - and says
 * which disk it is on, so the disk new uploads go to can change (local or
 * DigitalOcean Spaces) without breaking a file already stored. Its size counts
 * toward the business's storage (Doc 13 section 13.14).
 *
 * The logo moves here from business_settings.logo: it becomes the
 * workspace's file in the "logo" collection, on the public disk it was
 * already on.
 */
return new class extends Migration
{
    private const WORKSPACE = 'App\\Models\\User\\Workspace';

    public function up(): void
    {
        Schema::create('workspace_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('attachable_type');
            $t->unsignedBigInteger('attachable_id');
            $t->string('collection', 30);
            $t->string('disk', 30);
            $t->string('path', 500);
            $t->string('thumbnail_path', 500)->nullable();
            $t->string('visibility', 10);
            $t->string('name');
            $t->string('mime_type', 150)->nullable();
            $t->unsignedBigInteger('size');
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['attachable_type', 'attachable_id', 'collection']);
        });

        $now = now();

        foreach (DB::table('business_settings')->whereNotNull('workspace_id')->whereNotNull('logo')->get(['workspace_id', 'user_id', 'logo']) as $row) {
            $path = $this->publicPath($row->logo);

            if ($path === null) {
                continue;
            }

            $disk = Storage::disk('public');

            DB::table('workspace_files')->insert([
                'workspace_id'    => $row->workspace_id,
                'attachable_type' => self::WORKSPACE,
                'attachable_id'   => $row->workspace_id,
                'collection'      => 'logo',
                'disk'            => 'public',
                'path'            => $path,
                'visibility'      => 'public',
                'name'            => basename($path),
                'mime_type'       => $disk->exists($path) ? $disk->mimeType($path) : null,
                'size'            => $disk->exists($path) ? $disk->size($path) : 0,
                'uploaded_by'     => $row->user_id,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }

        Schema::table('business_settings', function (Blueprint $t) {
            $t->dropColumn('logo');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $t) {
            $t->string('logo')->nullable()->after('language');
        });

        foreach (DB::table('workspace_files')->where('collection', 'logo')->where('attachable_type', self::WORKSPACE)->get(['workspace_id', 'path']) as $file) {
            DB::table('business_settings')->where('workspace_id', $file->workspace_id)->update(['logo' => $file->path]);
        }

        Schema::dropIfExists('workspace_files');
    }

    /** "business-logos/x.png" from the forms a stored logo took: a path, "/storage/…" or a full URL. */
    private function publicPath(?string $logo): ?string
    {
        $path = preg_replace('#^https?://[^/]+#', '', str_replace('\\', '/', trim((string) $logo))) ?? '';
        $path = str_starts_with($path, '/storage/') ? substr($path, strlen('/storage/')) : ltrim($path, '/');

        return $path === '' ? null : $path;
    }
};
