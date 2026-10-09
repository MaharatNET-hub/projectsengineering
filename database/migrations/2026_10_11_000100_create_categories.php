<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a review category / position: who reviews it and against which specification and criteria
        Schema::create('v2_categories', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name_en');
            $t->string('name_ar');
            $t->string('discipline', 40)->default('Electrical');
            $t->text('description_en')->nullable();
            $t->text('description_ar')->nullable();
            $t->string('spec_title')->nullable(); // e.g. "Volume 3 – PART F Electrical (IFC Dec 2024)"
            $t->string('spec_file')->nullable(); // original name of the uploaded specification PDF
            $t->unsignedBigInteger('spec_size')->default(0);
            $t->json('rules')->nullable(); // the check criteria (v1 engine rule list)
            $t->json('study_types')->nullable(); // form-based study types routed to this category
            $t->boolean('active')->default(true);
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });

        // the engineers responsible for a category
        Schema::create('v2_category_user', function (Blueprint $t) {
            $t->foreignId('category_id')->constrained('v2_categories')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->primary(['category_id', 'user_id']);
        });

        Schema::table('v2_submissions', function (Blueprint $t) {
            $t->foreignId('category_id')->nullable()->constrained('v2_categories')->nullOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('title')->nullable(); // job title, signed under the official letter
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('title'));
        Schema::table('v2_submissions', function (Blueprint $t) {
            $t->dropConstrainedForeignId('assigned_to');
            $t->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('v2_category_user');
        Schema::dropIfExists('v2_categories');
    }
};
