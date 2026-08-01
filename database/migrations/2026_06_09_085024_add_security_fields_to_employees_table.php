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
    Schema::table('employees', function (Blueprint $table) {

        $table->string('otp')->nullable()->after('phone');

        $table->timestamp('otp_expires_at')
              ->nullable()
              ->after('otp');

        $table->boolean('phone_verified')
              ->default(false)
              ->after('otp_expires_at');

        $table->timestamp('enrolled_at')
              ->nullable()
              ->after('phone_verified');

    });
}

    /**
     * Reverse the migrations.
     */
   public function down(): void
{
    Schema::table('employees', function (Blueprint $table) {

        $table->dropColumn([
            'otp',
            'otp_expires_at',
            'phone_verified',
            'enrolled_at'
        ]);

    });
}
};
