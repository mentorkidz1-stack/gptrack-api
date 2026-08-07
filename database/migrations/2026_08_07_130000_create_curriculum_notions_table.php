<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_notions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_week_id')->constrained()->cascadeOnDelete();
            // "Activité n°1", "Sous-activité n°4.2" — tel qu'extrait du PDF.
            $table->string('label');
            $table->text('text');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        // Une attestation peut couvrir plusieurs notions (l'enseignant
        // coche celles réellement traitées pendant le cours).
        Schema::create('attendance_notion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_notion_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // Horodatage du début du cours (distinct de check_time, qui reste
        // l'heure de soumission du cahier de texte) — pour se rapprocher
        // d'un vrai cahier de texte papier.
        Schema::table('attendances', function (Blueprint $table) {
            $table->timestamp('course_started_at')->nullable()->after('check_time');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('course_started_at');
        });
        Schema::dropIfExists('attendance_notion');
        Schema::dropIfExists('curriculum_notions');
    }
};
