<?php

use App\Models\User;
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
        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('token');
            $table->string('code');
            $table->string('login_id')->comment('email / mobile No.');
            $table->enum('type', ['mobile', 'email']);
            $table->enum('auth_type', ['login', 'register'])->nullable();
            $table->string('ip')->nullable();
            $table->string('agent')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->boolean('expired')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
