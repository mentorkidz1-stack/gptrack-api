<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {

            $table->time('work_start_time')
                  ->nullable()
                  ->after('radius');

            $table->time('work_end_time')
                  ->nullable()
                  ->after('work_start_time');

            $table->integer('late_tolerance_minutes')
                  ->default(0)
                  ->after('work_end_time');

            $table->boolean('require_selfie')
                  ->default(true)
                  ->after('late_tolerance_minutes');

            $table->boolean('require_face_verification')
                  ->default(true)
                  ->after('require_selfie');

        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {

            $table->dropColumn([
                'work_start_time',
                'work_end_time',
                'late_tolerance_minutes',
                'require_selfie',
                'require_face_verification'
            ]);

        });
    }
};