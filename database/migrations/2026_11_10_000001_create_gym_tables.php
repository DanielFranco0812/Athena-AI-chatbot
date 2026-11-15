<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->enum('period', ['day', 'month']);
            $table->unsignedInteger('class_limit')->nullable()->comment('NULL = unlimited classes per plan period');
            $table->string('description');
            $table->text('perks')->comment('One perk per line');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('role', 20)->default('member')->after('phone');
            $table->foreignId('plan_id')->nullable()->after('role')->constrained('plans')->nullOnDelete();
            $table->date('plan_started')->nullable()->after('plan_id');
            $table->date('plan_expires')->nullable()->after('plan_started');
        });

        Schema::create('coaches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('nickname')->nullable();
            $table->string('specialty');
            $table->unsignedInteger('years_experience');
            $table->text('bio');
            $table->string('pt_hours')->comment('Personal training availability');
            $table->string('color', 10)->default('blue');
            $table->timestamps();
        });

        Schema::create('gym_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 20)->comment('strength, cardio, combat, recovery');
            $table->unsignedTinyInteger('intensity')->comment('1 easy, 2 moderate, 3 hard');
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->comment('1 = Monday ... 7 = Sunday');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room');
            $table->unsignedInteger('capacity');
            $table->timestamps();
            $table->index(['day_of_week', 'start_time']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_schedule_id')->constrained()->cascadeOnDelete();
            $table->date('session_date');
            $table->string('status', 20)->default('booked')->comment('booked, cancelled');
            $table->timestamps();
            $table->unique(['user_id', 'class_schedule_id', 'session_date']);
            $table->index(['class_schedule_id', 'session_date', 'status']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->default('web')->comment('web, messenger');
            $table->string('external_id')->comment('Browser chat key or Messenger PSID');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index(['channel', 'external_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->comment('user, assistant');
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('class_schedules');
        Schema::dropIfExists('gym_classes');
        Schema::dropIfExists('coaches');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['phone', 'role', 'plan_started', 'plan_expires']);
        });
        Schema::dropIfExists('plans');
    }
};
