<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a study entered through the per-type form (values + supporting file)
        Schema::create('v2_studies', function (Blueprint $t) {
            $t->id();
            $t->string('code', 16)->unique(); // tracking code given to the client
            $t->string('type', 60); // study type key (resources/studies/{type}.json)
            $t->string('status', 20)->default('submitted'); // submitted, review, issued, archived
            $t->string('client_name');
            $t->string('client_company')->nullable();
            $t->string('client_email');
            $t->string('client_phone', 40)->nullable();
            $t->string('project_name');
            $t->string('reference')->nullable();
            $t->text('notes')->nullable();
            $t->json('values')->nullable(); // what the client entered
            $t->json('analysis')->nullable(); // computed values, findings (with the engineer's edits)
            $t->string('decision', 20)->nullable(); // approved, noted, revise, rejected
            $t->text('remarks')->nullable(); // engineer's general remarks
            $t->string('engineer')->nullable();
            $t->string('file_name')->nullable();
            $t->unsignedBigInteger('file_size')->default(0);
            $t->unsignedInteger('page_count')->nullable();
            $t->unsignedInteger('finding_count')->nullable();
            $t->string('locale', 2)->default('en');
            $t->timestamp('issued_at')->nullable();
            $t->timestamp('emailed_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_studies');
    }
};
