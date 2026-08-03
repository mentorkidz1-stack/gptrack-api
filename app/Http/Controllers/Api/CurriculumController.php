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
        // Validation volontairement peu profonde sur le contenu de chaque
        // semaine : le parseur PDF (best-effort sur un document irrégulier)
        // produit parfois une date invalide sur une poignée de semaines.
        // Avec une règle Laravel imbriquée classique, UNE SEULE semaine
        // invalide aurait rejeté tout l'import — y compris les autres
        // promotions parfaitement valides. Chaque semaine est donc
        // vérifiée individuellement plus bas et simplement ignorée (pas
        // tout l'import bloqué) si elle est corrompue.
        $request->validate([
            'subject_id' => 'nullable|integer',
            'subject_name' => 'nullable|string|max:255',
            'mappings' => 'required|array|min:1',
            'mappings.*.class_level_id' => 'nullable|integer',
            'mappings.*.promotion' => 'required_without:mappings.*.class_level_id|nullable|string|max:255',
            'mappings.*.weeks' => 'required|array',
        ]);

        // La matière : celle choisie explicitement, ou créée à la volée à
        // partir de la discipline détectée dans le PDF si elle n'existe
        // pas encore — évite d'obliger le DG à tout pré-créer à la main.
        if ($request->filled('subject_id')) {
            $subject = Subject::find($request->subject_id);
            if (!$subject) {
                return response()->json([
                    'success' => false,
                    'message' => 'Matière invalide',
                ], 422);
            }
        } elseif ($request->filled('subject_name')) {
            $subject = Subject::firstOrCreate([
                'name' => trim($request->subject_name),
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Matière requise (subject_id ou subject_name)',
            ], 422);
        }

        // Chaque classe : celle choisie explicitement dans le mapping, ou
        // trouvée/créée automatiquement à partir du nom de la promotion
        // détectée dans le PDF (comparaison insensible à la casse).
        $classLevelIds = [];
        foreach ($request->mappings as $i => $mapping) {
            if (!empty($mapping['class_level_id'])) {
                $classLevel = ClassLevel::find($mapping['class_level_id']);
                if (!$classLevel) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Classe invalide dans le mapping',
                    ], 422);
                }
            } else {
                $name = trim($mapping['promotion']);
                $classLevel = ClassLevel::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
                    ?? ClassLevel::create(['name' => $name]);
            }
            $classLevelIds[$i] = $classLevel->id;
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($request, $subject, $classLevelIds, &$created, &$skipped) {
            foreach ($request->mappings as $i => $mapping) {
                foreach ($mapping['weeks'] as $week) {
                    if (!$this->isValidWeek($week)) {
                        $skipped++;
                        continue;
                    }

                    CurriculumWeek::create([
                        'subject_id' => $subject->id,
                        'class_level_id' => $classLevelIds[$i],
                        'trimester' => (int) $week['trimester'],
                        'period_start' => $week['period_start'],
                        'period_end' => $week['period_end'],
                        'situation_apprentissage' => $week['situation_apprentissage'] ?? null,
                        'activities_text' => $week['activities_text'] ?? null,
                        'taux_prevu' => $week['taux_prevu'],
                        'is_teaching_week' => (bool) $week['is_teaching_week'],
                    ]);
                    $created++;
                }
            }
        });

        $message = "$created semaines importées";
        if ($skipped > 0) {
            $message .= " ($skipped semaine(s) ignorée(s) car mal extraite(s) du PDF — à ajouter manuellement si besoin)";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'weeks_created' => $created,
            'weeks_skipped' => $skipped,
        ]);
    }

    /**
     * Une semaine du mapping envoyé par le dashboard est valide si ses
     * champs essentiels sont exploitables — sinon elle est ignorée plutôt
     * que de faire échouer tout l'import (voir importConfirm()).
     */
    private function isValidWeek(array $week): bool
    {
        if (!isset($week['trimester']) || !in_array((int) $week['trimester'], [1, 2, 3], true)) {
            return false;
        }

        if (!isset($week['period_start'], $week['period_end'])) {
            return false;
        }

        try {
            $start = \Carbon\Carbon::parse($week['period_start']);
            $end = \Carbon\Carbon::parse($week['period_end']);
        } catch (\Throwable $e) {
            return false;
        }

        // Une date "invalide" comme "2026-05-00" ne lève pas toujours une
        // exception chez Carbon (elle est parfois recalée sur le mois
        // précédent) — on rejette aussi une plage clairement incohérente.
        if ($end->lt($start) || $start->diffInDays($end) > 31) {
            return false;
        }

        if (!isset($week['taux_prevu']) || !is_numeric($week['taux_prevu'])) {
            return false;
        }

        if (!array_key_exists('is_teaching_week', $week)) {
            return false;
        }

        return true;
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
