<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Défaut 'active' pour ne pas bloquer les entreprises déjà
            // existantes : seules les nouvelles inscriptions (self-serve)
            // démarrent explicitement en 'trialing'.
            $table->string('subscription_status')->default('active')->after('subscription_plan');
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_status');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['subscription_status', 'trial_ends_at']);
        });
    }
};
