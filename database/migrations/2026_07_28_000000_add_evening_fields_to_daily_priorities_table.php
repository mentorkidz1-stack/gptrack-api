<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_priorities', function (Blueprint $table) {
            $table->enum('evening_status', ['faite', 'partielle', 'pas_faite'])->nullable()->after('skipped');
            $table->text('evening_note')->nullable()->after('evening_status');

            $table->boolean('secondary_1_done')->nullable()->after('evening_note');
            $table->boolean('secondary_2_done')->nullable()->after('secondary_1_done');

            $table->text('evening_obstacle_self')->nullable()->after('secondary_2_done');
            $table->text('evening_obstacle_other')->nullable()->after('evening_obstacle_self');
            $table->boolean('evening_smooth_day')->default(false)->after('evening_obstacle_other');

            // Zone 3 (for intérieur) : jamais exposé aux endpoints DG/RH.
            $table->text('private_reflection')->nullable()->after('evening_smooth_day');

            $table->timestamp('evening_completed_at')->nullable()->after('private_reflection');

            $table->text('ai_evening_question')->nullable()->after('evening_completed_at');
            // Zone 3 (for intérieur).
            $table->text('ai_evening_answer')->nullable()->after('ai_evening_question');
        });
    }

    public function down(): void
    {
        Schema::table('daily_priorities', function (Blueprint $table) {
            $table->dropColumn([
                'evening_status',
                'evening_note',
                'secondary_1_done',
                'secondary_2_done',
                'evening_obstacle_self',
                'evening_obstacle_other',
                'evening_smooth_day',
                'private_reflection',
                'evening_completed_at',
                'ai_evening_question',
                'ai_evening_answer',
            ]);
        });
    }
};
