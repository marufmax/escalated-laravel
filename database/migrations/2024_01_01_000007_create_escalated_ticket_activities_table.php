<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('escalated.table_prefix', 'escalated_').'ticket_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained(config('escalated.table_prefix', 'escalated_').'tickets')->cascadeOnDelete();
            $table->nullableMorphs('causer');
            $table->string('type');
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('escalated.table_prefix', 'escalated_').'ticket_activities');
    }
};
