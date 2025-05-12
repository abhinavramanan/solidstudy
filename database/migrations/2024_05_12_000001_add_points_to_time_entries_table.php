<?php

declare(strict_types=1);

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
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->integer('points')->unsigned()->nullable();
        });

        Schema::create('breaks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('description', 500);
            $table->integer('points_cost')->unsigned();
            $table->integer('duration_minutes')->unsigned();
            $table->uuid('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->dateTime('redeemed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropColumn('points');
        });

        Schema::dropIfExists('breaks');
    }
};
