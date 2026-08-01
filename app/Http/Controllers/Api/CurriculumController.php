<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\CurriculumWeek;
use App\Models\Subject;
use App\Services\CurriculumImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurriculumController extends Controller
{
    /**
     * Analyse un PDF "Fiche de suivi de la progression" et renvoie la
     * structure détectée (discipline, promotions, semaines) SANS rien
     * écrire en base — le DG associe ensuite chaque promotion à une
     * classe existante et confirme via importConfirm(). Le parsing est
     * best-effort sur un format irrégulier : certaines semaines peuvent
     * être absentes ou mal découpées, à repérer/corriger à cet écran ou
     * après import via update()/destroy().
     */
    public function importPreview(Request $request)
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:20480',
        ]);

        $service = new CurriculumImportService();
        $result = $service->parse($request->file('pdf')->getRealPath());

        return response()->json([
            'success' => true,
            'discipline' => $result['discipline'],
            'annee_debut' => $result['annee_debut'],
            'promotions' => $result['promotions'],
        ]);
    }

    /**
     * Écrit en base les semaines pour le mapping confirmé par le DG.
     * Toutes les classes référencées sont vérifiées avant toute écriture
     * (transaction) pour ne jamais laisser un import partiel si une
     * classe du mapping est invalide.
     */
    public function importConfirm(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|integer',
            'mappings' => 'required|array|min:1',
            'mappings.*.class_level_id' => 'required|integer',
            'mappings.*.weeks' => 'required|array',
            'mappings.*.weeks.*.trimester' => 'required|integer|min:1|max:3',
            'mappings.*.weeks.*.period_start' => 'required|date',
            'mappings.*.weeks.*.period_end' => 'required|date',
            'mappings.*.weeks.*.situation_apprentissage' => 'nullable|string',
            'mappings.*.weeks.*.activities_text' => 'nullable|string',
            'mappings.*.weeks.*.taux_prevu' => 'required|numeric|min:0|max:100',
            'mappings.*.weeks.*.is_teaching_week' => 'required|boolean',
        ]);

        $subject = Subject::find($request->subject_id);
        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => 'Matière invalide',
            ], 422);
        }

        foreach ($request->mappings as $mapping) {
            if (!ClassLevel::find($mapping['class_level_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Classe invalide dans le mapping',
                ], 422);
            }
        }

        $created = 0;

        DB::transaction(function () use ($request, $subject, &$created) {
            foreach ($request->mappings as $mapping) {
                foreach ($mapping['weeks'] as $week) {
                    CurriculumWeek::create([
                        'subject_id' => $subject->id,
                        'class_level_id' => $mapping['class_level_id'],
                        'trimester' => $week['trimester'],
                        'period_start' => $week['period_start'],
                        'period_end' => $week['period_end'],
                        'situation_apprentissage' => $week['situation_apprentissage'] ?? null,
                        'activities_text' => $week['activities_text'] ?? null,
                        'taux_prevu' => $week['taux_prevu'],
                        'is_teaching_week' => $week['is_teaching_week'],
                    ]);
                    $created++;
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => "$created semaines importées",
            'weeks_created' => $created,
        ]);
    }

    /**
     * Progression prévu vs réalisé, semaine par semaine, cumulée
     * chronologiquement à la volée (jamais stockée) — la vue de pilotage
     * du DG. Le "réalisé" d'une semaine vient de la dernière attestation
     * liée (normalement une par enseignant/classe/semaine).
     */
    public function progression(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|integer',
            'class_level_id' => 'required|integer',
        ]);

        $weeks = CurriculumWeek::where('subject_id', $request->subject_id)
            ->where('class_level_id', $request->class_level_id)
            ->orderBy('period_start')
            ->get();

        $cumulPrevu = 0;
        $cumulRealise = 0;

        $rows = $weeks->map(function (CurriculumWeek $week) use (&$cumulPrevu, &$cumulRealise) {
            $attestation = $week->attendances()
                ->whereNotNull('taux_realise')
                ->orderByDesc('check_time')
                ->first();

            if ($week->is_teaching_week) {
                $cumulPrevu += $week->taux_prevu;
                if ($attestation) {
                    $cumulRealise += $attestation->taux_realise;
                }
            }

            return [
                'id' => $week->id,
                'trimester' => $week->trimester,
                'period_start' => $week->period_start->format('Y-m-d'),
                'period_end' => $week->period_end->format('Y-m-d'),
                'situation_apprentissage' => $week->situation_apprentissage,
                'activities_text' => $week->activities_text,
                'is_teaching_week' => $week->is_teaching_week,
                'taux_prevu' => $week->taux_prevu,
                'taux_realise' => $attestation?->taux_realise,
                'notes' => $attestation?->notes,
                'cumul_prevu' => round($cumulPrevu, 2),
                'cumul_realise' => round($cumulRealise, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'weeks' => $rows->values(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $week = CurriculumWeek::findOrFail($id);

        $request->validate([
            'trimester' => 'sometimes|integer|min:1|max:3',
            'period_start' => 'sometimes|date',
            'period_end' => 'sometimes|date',
            'situation_apprentissage' => 'sometimes|nullable|string',
            'activities_text' => 'sometimes|nullable|string',
            'taux_prevu' => 'sometimes|numeric|min:0|max:100',
            'is_teaching_week' => 'sometimes|boolean',
        ]);

        $week->update($request->only([
            'trimester',
            'period_start',
            'period_end',
            'situation_apprentissage',
            'activities_text',
            'taux_prevu',
            'is_teaching_week',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Semaine modifiée',
            'week' => $week->fresh(),
        ]);
    }

    public function destroy($id)
    {
        $week = CurriculumWeek::findOrFail($id);
        $week->delete();

        return response()->json([
            'success' => true,
            'message' => 'Semaine supprimée',
        ]);
    }
}
