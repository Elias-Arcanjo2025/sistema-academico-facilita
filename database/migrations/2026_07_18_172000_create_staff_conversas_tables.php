<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_conversas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_low_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_high_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['user_low_id', 'user_high_id'], 'staff_conversa_par_unique');
            $table->index('last_message_at');
        });

        Schema::create('staff_mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversa_id')->constrained('staff_conversas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('corpo');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversa_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_mensagens');
        Schema::dropIfExists('staff_conversas');
    }
};
