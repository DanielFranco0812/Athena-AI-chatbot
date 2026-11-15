<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->string('role', 20)->default('coach')->after('name')->comment('coach or staff');
            $table->string('photo')->nullable()->after('color')->comment('Path under public/, e.g. images/coaches/mark.jpg');
            $table->text('certifications')->nullable()->after('bio')->comment('One per line');
            $table->text('specialties')->nullable()->after('certifications')->comment('One per line: "Name: short description"');
        });

        Schema::table('class_schedules', function (Blueprint $table) {
            $table->foreignId('co_coach_id')->nullable()->after('coach_id')->constrained('coaches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('co_coach_id');
        });
        Schema::table('coaches', function (Blueprint $table) {
            $table->dropColumn(['role', 'photo', 'certifications', 'specialties']);
        });
    }
};
