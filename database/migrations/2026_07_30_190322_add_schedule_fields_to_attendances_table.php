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
        Schema::table('attendances', function (Blueprint $table) {
            // NULL = pointage classique inchangé (comportement actuel de
            // tous les employés existants, y compris l'arrivée/départ
            // général des enseignants). Non-NULL = attestation de cours.
            $table->foreignId('schedule_id')->nullable()->after('site_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('curriculum_week_id')->nullable()->after('schedule_id')
                ->constrained()->nullOnDelete();
            // % réellement couvert cette semaine (cahier de texte) —
            // pré-rempli au taux prévu de la semaine côté app, ajustable.
            $table->decimal('taux_realise', 5, 2)->nullable()->after('curriculum_week_id');
            // Observation libre de l'enseignant (cahier de texte).
            $table->text('notes')->nullable()->after('taux_realise');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_id');
            $table->dropConstrainedForeignId('curriculum_week_id');
            $table->dropColumn(['taux_realise', 'notes']);
        });
    }
};
