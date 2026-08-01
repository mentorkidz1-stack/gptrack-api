<?php

/*
|--------------------------------------------------------------------------
| Plans d'abonnement GPTrack
|--------------------------------------------------------------------------
|
| max_employees / max_sites à null = illimité.
| price_xof = prix mensuel en francs CFA (XOF), null = sur devis.
|
*/

return [

    'starter' => [
        'label' => 'Starter',
        'price_xof' => 15000,
        'max_employees' => 15,
        'max_sites' => 1,
    ],

    'business' => [
        'label' => 'Business',
        'price_xof' => 45000,
        'max_employees' => 100,
        'max_sites' => 5,
    ],

    'enterprise' => [
        'label' => 'Enterprise',
        'price_xof' => null,
        'max_employees' => null,
        'max_sites' => null,
    ],

];
