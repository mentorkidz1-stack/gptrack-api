<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use Illuminate\Http\Request;

class ClassLevelController extends Controller
{
    /**
     * Classes de l'entreprise courante (cloisonné automatiquement par le
     * trait BelongsToCompany).
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'class_levels' => ClassLevel::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $classLevel = ClassLevel::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Classe créée',
            'class_level' => $classLevel,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $classLevel = ClassLevel::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $classLevel->update($request->only(['name']));

        return response()->json([
            'success' => true,
            'message' => 'Classe modifiée',
            'class_level' => $classLevel->fresh(),
        ]);
    }

    public function destroy($id)
    {
        $classLevel = ClassLevel::findOrFail($id);
        $classLevel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Classe supprimée',
        ]);
    }
}
