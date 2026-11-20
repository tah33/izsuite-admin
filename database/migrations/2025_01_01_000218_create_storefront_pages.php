<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase C5a: a storefront's pages (section 19.6) - its contact and
 * about pages and its four policies (privacy, terms and conditions, shipping,
 * and return and refund), each at a link of its own: the business's words in
 * rich text, published or a draft, linked from the footer or not, with its
 * SEO. Pages of the business's own (Phase C5b) are kept here too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storefront_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);                                                     // about | contact | shipping | returns | privacy | terms
            $table->string('title', 120);
            $table->string('slug', 200);
            $table->mediumText('body')->nullable();                                         // rich text, with headings
            $table->string('status', 20)->default('draft');                                 // published | draft
            $table->boolean('in_footer')->default(true);
            $table->string('seo_title', 200)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['storefront_id', 'slug']);
            $table->index(['storefront_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_pages');
    }
};
