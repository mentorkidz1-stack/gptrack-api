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
        Schema::table('companies', function (Blueprint $table) {
            // 'entreprise' = comportement actuel inchangé (aucune section
            // école visible). 'ecole' active matières/classes/progression
            // côté dashboard et le vocabulaire adapté.
            $table->enum('type', ['entreprise', 'ecole'])
                ->default('entreprise')
                ->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
