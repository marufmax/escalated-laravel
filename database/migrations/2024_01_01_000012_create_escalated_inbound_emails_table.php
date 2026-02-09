<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('escalated.table_prefix', 'escalated_').'inbound_emails';

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->nullable()->unique();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('to_email');
            $table->string('subject');
            $table->text('body_text')->nullable();
            $table->text('body_html')->nullable();
            $table->text('raw_headers')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->unsignedBigInteger('reply_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('adapter');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $ticketsTable = config('escalated.table_prefix', 'escalated_').'tickets';
            $repliesTable = config('escalated.table_prefix', 'escalated_').'replies';

            $table->foreign('ticket_id')
                ->references('id')
                ->on($ticketsTable)
                ->nullOnDelete();

            $table->foreign('reply_id')
                ->references('id')
                ->on($repliesTable)
                ->nullOnDelete();

            $table->index('from_email');
            $table->index('status');
            $table->index('adapter');
        });
    }

    public function down(): void
    {
        $table = config('escalated.table_prefix', 'escalated_').'inbound_emails';

        Schema::dropIfExists($table);
    }
};
