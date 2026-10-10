<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A study's supporting PDF can be sent through the category check: the submission keeps a link to its study. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_submissions', fn (Blueprint $t) => $t->foreignId('study_id')->nullable()->constrained('v2_studies')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('v2_submissions', fn (Blueprint $t) => $t->dropConstrainedForeignId('study_id'));
    }
};
