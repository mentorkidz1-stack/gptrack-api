<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_priorities', function (Blueprint $table) {
            $table->text('secondary_1')->nullable()->after('main_priority');
            $table->text('secondary_2')->nullable()->after('secondary_1');
            $table->text('obstacle_self')->nullable()->after('secondary_2');
            $table->text('obstacle_other')->nullable()->after('obstacle_self');
            $table->text('parade')->nullable()->after('obstacle_other');
        });
    }

    public function down(): void
    {
        Schema::table('daily_priorities', function (Blueprint $table) {
            $table->dropColumn([
                'secondary_1',
                'secondary_2',
                'obstacle_self',
                'obstacle_other',
                'parade',
            ]);
        });
    }
};