<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use Illuminate\Http\Request;

class ContactRequestController extends Controller
{
    /**
     * Formulaire de contact public (site vitrine) — aucune authentification
     * requise, throttle pour éviter le spam. Ne crée aucun compte : un
     * suivi commercial recontacte ensuite manuellement.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'organization' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'organization_type' => 'nullable|string|in:entreprise,ecole,autre',
            'message' => 'nullable|string|max:2000',
        ]);

        $contact = ContactRequest::create($request->only([
            'name', 'organization', 'email', 'phone', 'organization_type', 'message',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Votre demande a bien été envoyée. Nous vous recontacterons rapidement.',
            'id' => $contact->id,
        ], 201);
    }
}
