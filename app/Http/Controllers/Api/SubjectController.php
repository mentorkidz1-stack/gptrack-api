<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    /**
     * Matières de l'entreprise courante (cloisonné automatiquement par le
     * trait BelongsToCompany).
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'subjects' => Subject::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // company_id est fixé automatiquement à la création par le trait
        // BelongsToCompany, jamais depuis une valeur du client.
        $subject = Subject::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Matière créée',
            'subject' => $subject,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $subject->update($request->only(['name']));

        return response()->json([
            'success' => true,
            'message' => 'Matière modifiée',
            'subject' => $subject->fresh(),
        ]);
    }

    public function destroy($id)
    {
        $subject = Subject::findOrFail($id);
        $subject->delete();

        return response()->json([
            'success' => true,
            'message' => 'Matière supprimée',
        ]);
    }
}
