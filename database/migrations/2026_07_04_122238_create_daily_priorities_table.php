<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_priorities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->date('priority_date');           // le jour concerné
            $table->text('main_priority')->nullable(); // "Ma seule priorité aujourd'hui"
            $table->boolean('skipped')->default(false); // l'employé a passé le rituel
            $table->timestamps();

            // Un seul rituel du matin par employé et par jour
            $table->unique(['employee_id', 'priority_date']);
        });
    }

    public function down(): void
    {
        Schema::dropTable('daily_priorities');
    }
};