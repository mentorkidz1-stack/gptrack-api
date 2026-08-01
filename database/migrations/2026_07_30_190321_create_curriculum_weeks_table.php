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
        Schema::create('curriculum_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_level_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('trimester');
            $table->date('period_start');
            $table->date('period_end');
            // Ex: "SA 4" — Situation d'Apprentissage, tel qu'extrait de la
            // fiche officielle.
            $table->string('situation_apprentissage')->nullable();
            // Bloc d'activités/sous-activités prévues cette semaine, tel
            // qu'extrait du PDF officiel.
            $table->text('activities_text')->nullable();
            // % du programme annuel prévu pour cette semaine (Taux
            // d'exécution). Le cumulé n'est jamais stocké : recalculé à la
            // volée par somme chronologique pour ne jamais dériver de la
            // réalité.
            $table->decimal('taux_prevu', 5, 2)->default(0);
            // false pour congés / périodes d'intégration / productions
            // scolaires — exclues du calcul de progression.
            $table->boolean('is_teaching_week')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_weeks');
    }
};
