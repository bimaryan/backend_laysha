<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_room_id')->constrained()->cascadeOnDelete();
            $table->enum('sender_type', ['user', 'ai', 'admin']);
            $table->text('message');
            $table->unsignedBigInteger('reply_to_id')->nullable(); // FK untuk fitur Balas
            $table->string('instruction')->nullable(); // Instruksi darurat dari AI
            $table->timestamps();

            $table->foreign('reply_to_id')->references('id')->on('chat_messages')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
