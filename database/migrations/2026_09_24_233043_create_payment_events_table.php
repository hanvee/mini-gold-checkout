<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            // Uniqueness enforced at the DB level — this is the real idempotency guard,
            // not just an application-level check. See AI_AGENT.md §2.4.
            $table->string('event_id')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('order_number');
            $table->unsignedBigInteger('amount');
            $table->string('status');
            // sha256("{event_id}|{order_number}|{amount}|{status}") — detects a reused
            // event_id sent with a different payload.
            $table->string('payload_hash');
            $table->string('result');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
