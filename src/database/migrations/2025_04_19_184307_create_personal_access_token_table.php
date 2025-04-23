<?php

use Phaseolies\Support\Facades\Schema;
use Phaseolies\Database\Migration\Migration;
use Phaseolies\Database\Migration\Blueprint;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('personal_access_token', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class, true, true);
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->json('abilities')->nullable();
            $table->datetime('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_token');
    }
};
