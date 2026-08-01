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
    Schema::create('attendances', function (Blueprint $table) {

        $table->id();

        $table->foreignId('employee_id')
              ->constrained()
              ->cascadeOnDelete();

        $table->foreignId('site_id')
              ->constrained()
              ->cascadeOnDelete();

        $table->decimal('latitude', 10, 7);

        $table->decimal('longitude', 10, 7);

        $table->text('selfie_photo');

        $table->decimal('face_match_score', 5, 2)
              ->nullable();

        $table->boolean('is_inside_zone')
              ->default(false);

        $table->enum('status', [
            'success',
            'face_failed',
            'outside_zone'
        ]);

        $table->timestamp('check_time');

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
   public function down(): void
{
    Schema::dropIfExists('attendances');
}
};
