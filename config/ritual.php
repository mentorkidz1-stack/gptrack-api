<?php

/*
|--------------------------------------------------------------------------
| Rituel du matin/soir — paramètres de calcul
|--------------------------------------------------------------------------
*/

return [

    // Nombre de jours-joker tolérés sur une fenêtre glissante de 7 jours
    // avant que la série (fidélité au rituel) ne soit rompue.
    'joker_days_per_week' => (int) env('RITUAL_JOKER_DAYS_PER_WEEK', 1),

];
