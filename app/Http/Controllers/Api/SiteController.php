<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Site;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    /**
     * Sites de l'entreprise courante (cloisonné automatiquement par le
     * trait BelongsToCompany).
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'sites' => Site::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius'    => 'nullable|integer|min:10',

            'work_start_time'        => 'nullable',
            'work_end_time'          => 'nullable',
            'late_tolerance_minutes' => 'nullable|integer',
        ]);

        $company = Company::find($request->user()->company_id);
        $limit = $company?->siteLimit();
        if ($limit !== null && Site::count() >= $limit) {
            return response()->json([
                'success' => false,
                'message' => "Limite du plan atteinte ($limit sites max). Passez à un plan supérieur.",
            ], 422);
        }

        // company_id est fixé automatiquement à la création par le trait
        // BelongsToCompany, jamais depuis une valeur du client.
        $site = Site::create([
            'name'                   => $request->name,
            'latitude'               => $request->latitude,
            'longitude'              => $request->longitude,
            'radius'                 => $request->radius ?? 100,
            'work_start_time'        => $request->work_start_time,
            'work_end_time'          => $request->work_end_time,
            'late_tolerance_minutes' => $request->late_tolerance_minutes ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Site créé',
            'site' => $site,
        ], 201);
    }

    public function show($id)
    {
        return response()->json(
            Site::findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $site = Site::findOrFail($id);

        $request->validate([
            'name' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'work_start_time' => 'nullable',
            'work_end_time' => 'nullable',
            'late_tolerance_minutes' => 'nullable|integer',

            'require_selfie' => 'nullable|boolean',
            'require_face_verification' => 'nullable|boolean',

            'radius' => 'nullable|integer'
        ]);

        // Liste blanche explicite : jamais `$request->all()`, pour ne pas
        // permettre au client de réassigner `company_id` ou d'autres
        // champs protégés via une requête bien formée.
        // (latitude/longitude étaient absents ici : le dashboard permettait
        // de "modifier la position", affichait un succès, mais la position
        // stockée ne changeait jamais réellement.)
        $site->update($request->only([
            'name',
            'latitude',
            'longitude',
            'work_start_time',
            'work_end_time',
            'late_tolerance_minutes',
            'require_selfie',
            'require_face_verification',
            'radius',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Configuration mise à jour',
            'site' => $site->fresh()
        ]);
    }
}