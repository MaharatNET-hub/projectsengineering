<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // client accounts (separate from the office's users)
        Schema::create('v2_clients', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('company')->nullable();
            $t->string('email')->unique();
            $t->string('phone', 40)->nullable();
            $t->string('password');
            $t->string('locale', 2)->default('ar');
            $t->rememberToken();
            $t->timestamps();
        });

        // office users: admin (everything) or engineer (reviews only)
        Schema::table('users', function (Blueprint $t) {
            $t->string('role', 20)->default('admin');
            $t->boolean('active')->default(true);
        });

        Schema::table('v2_studies', function (Blueprint $t) {
            $t->unsignedInteger('revision')->default(0);
            $t->foreignId('parent_id')->nullable()->constrained('v2_studies')->nullOnDelete(); // the revision this one answers
            $t->foreignId('client_id')->nullable()->constrained('v2_clients')->nullOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->index('client_id');
        });

        // the activity log also covers studies
        Schema::table('v2_activities', function (Blueprint $t) {
            $t->foreignId('study_id')->nullable()->constrained('v2_studies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('v2_activities', fn (Blueprint $t) => $t->dropConstrainedForeignId('study_id'));
        Schema::table('v2_studies', function (Blueprint $t) {
            $t->dropConstrainedForeignId('assigned_to');
            $t->dropConstrainedForeignId('client_id');
            $t->dropConstrainedForeignId('parent_id');
            $t->dropColumn('revision');
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'active']));
        Schema::dropIfExists('v2_clients');
    }
};
