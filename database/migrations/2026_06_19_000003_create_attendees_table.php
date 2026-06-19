<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendees', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamp('reminded_3day_at')->nullable();
            $table->timestamp('reminded_24h_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'email']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendees');
    }
};
