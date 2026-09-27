<?php

use Phaseolies\Support\Facades\Schema;
use Phaseolies\Database\Migration\Migration;
use Phaseolies\Database\Migration\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations
     *
     * @return void
     */
    public function up(): void
    {
        $model = config('auth.actors.api.model');

        Schema::create('personal_access_token', function (Blueprint $table) use ($model) {
            $table->id();
            $table->foreignIdFor($model, true, true);
            $table->string('name');
            $table->json('abilities')->nullable();
            $table->string('lookup_hash', 64)->unique()->index();
            $table->datetime('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
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
