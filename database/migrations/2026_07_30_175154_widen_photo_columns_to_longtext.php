<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TEXT (limite ~64 Ko) était trop petit pour une photo base64 issue de la
 * caméra en ResolutionPreset.medium : au-delà de la limite, MySQL tronque
 * (ou rejette en mode strict) silencieusement les octets en trop, ce qui
 * corrompt l'image stockée. LONGTEXT lève cette limite (jusqu'à 4 Go).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE attendances MODIFY selfie_photo LONGTEXT NOT NULL');
        DB::statement('ALTER TABLE employees MODIFY reference_photo LONGTEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE attendances MODIFY selfie_photo TEXT NOT NULL');
        DB::statement('ALTER TABLE employees MODIFY reference_photo TEXT NULL');
    }
};
