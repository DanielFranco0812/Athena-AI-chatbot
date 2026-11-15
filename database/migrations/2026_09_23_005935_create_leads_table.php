<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('interest')->nullable()->comment('Plan, class or service the lead asked about');
            $table->string('source')->default('website')->comment('website, chat_widget, messenger');
            $table->text('message')->nullable();
            $table->text('summary')->nullable()->comment('Short inquiry summary written by Athena AI');
            $table->string('status')->default('new')->comment('new, contacted, converted, lost');
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
