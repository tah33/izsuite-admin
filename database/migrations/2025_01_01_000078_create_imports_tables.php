<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Importing contacts or items from a CSV file (Doc 03 section 15, Doc 04
 * section 17), one step at a time: the file is read and its columns matched
 * to fields (mapping), every row is checked - errors and possible duplicates
 * - and shown (checking, then ready), and the rows the user keeps are added
 * (importing, then done).
 *
 * The file itself waits on the private disk until the import is finished or
 * dropped; it does not count toward the business's storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kind', 20);
            $t->string('status', 20);
            $t->string('file_path', 500);
            $t->string('file_name');
            $t->json('headers');
            $t->json('mapping')->nullable();
            $t->unsignedInteger('row_count');
            $t->unsignedInteger('processed')->default(0);
            $t->json('summary')->nullable();
            $t->json('result')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('import_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('line');
            $t->json('data');
            $t->json('errors')->nullable();
            $t->json('duplicates')->nullable();
            $t->boolean('include')->default(false);
            $t->string('outcome', 10)->nullable();
            $t->unsignedBigInteger('record_id')->nullable();
            $t->string('message')->nullable();

            $t->index(['import_id', 'line']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('imports');
    }
};
