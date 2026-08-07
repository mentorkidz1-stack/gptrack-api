<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Demande envoyée depuis le formulaire de contact public du site vitrine —
 * pas de compte créé automatiquement, un suivi commercial la traite
 * ensuite manuellement (pas de service d'envoi d'e-mail configuré pour
 * l'instant, donc aucune notification automatique n'est envoyée).
 */
class ContactRequest extends Model
{
    protected $fillable = [
        'name',
        'organization',
        'email',
        'phone',
        'organization_type',
        'message',
        'handled',
    ];

    protected $casts = [
        'handled' => 'boolean',
    ];
}
