<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use Illuminate\Http\Request;

class ContactRequestController extends Controller
{
    /**
     * Liste des demandes de contact — réservée au compte opérateur GPTrack
     * (DFEM SOLUTIONS), pas à n'importe quel DG client. La vérification se
     * fait sur l'ID de l'entreprise, pas seulement sur le rôle "dg" :
     * un DG d'une entreprise cliente a aussi le rôle "dg", mais ne doit
     * jamais voir les prospects des autres.
     */
    public function index(Request $request)
    {
        $operatorCompanyId = config('services.operator_company_id');
        if (!$operatorCompanyId || (string) $request->user()->company_id !== (string) $operatorCompanyId) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'requests' => ContactRequest::orderByDesc('created_at')->get(),
        ]);
    }

    public function markHandled(Request $request, $id)
    {
        $operatorCompanyId = config('services.operator_company_id');
        if (!$operatorCompanyId || (string) $request->user()->company_id !== (string) $operatorCompanyId) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé',
            ], 403);
        }

        $contact = ContactRequest::findOrFail($id);
        $contact->update(['handled' => true]);

        return response()->json(['success' => true, 'request' => $contact]);
    }

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
