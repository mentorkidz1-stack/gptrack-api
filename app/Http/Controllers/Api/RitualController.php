<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\DailyPriority;
use App\Models\Employee;
use App\Services\AssistantService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RitualController extends Controller
{
    /**
     * Enregistre (ou met à jour) la priorité du matin du jour.
     */
    public function saveMorning(Request $request)
    {
        $employee = $this->requireEmployee($request);
        if ($employee instanceof JsonResponse) {
            return $employee;
        }

        $request->validate([
            'main_priority'  => 'nullable|string',
            'secondary_1'    => 'nullable|string',
            'secondary_2'    => 'nullable|string',
            'obstacle_self'  => 'nullable|string',
            'obstacle_other' => 'nullable|string',
            'parade'         => 'nullable|string',
            'skipped'        => 'boolean',
        ]);

        $priority = DailyPriority::updateOrCreate(
            [
                'employee_id'   => $employee->id,
                'priority_date' => Carbon::today()->toDateString(),
            ],
            [
                'main_priority'  => $request->main_priority,
                'secondary_1'    => $request->secondary_1,
                'secondary_2'    => $request->secondary_2,
                'obstacle_self'  => $request->obstacle_self,
                'obstacle_other' => $request->obstacle_other,
                'parade'         => $request->parade,
                'skipped'        => $request->boolean('skipped'),
            ]
        );

        return response()->json(array_merge([
            'success'       => true,
            'priority'      => $priority,
            'rappel_veille' => $this->rappelVeille($employee),
        ], $this->computeStats($employee)));
    }

    /**
     * Récupère le rituel du matin du jour (pour savoir s'il est déjà fait).
     */
    public function todayMorning(Request $request)
    {
        $employee = $this->requireEmployee($request);
        if ($employee instanceof JsonResponse) {
            return $employee;
        }

        $priority = DailyPriority::where('employee_id', $employee->id)
            ->where('priority_date', Carbon::today()->toDateString())
            ->first();

        return response()->json(array_merge([
            'success'       => true,
            'done'          => $priority !== null,
            'main_priority' => $priority?->main_priority,
            'secondary_1'   => $priority?->secondary_1,
            'secondary_2'   => $priority?->secondary_2,
            'skipped'       => $priority?->skipped ?? false,
            'rappel_veille' => $this->rappelVeille($employee),
        ], $this->computeStats($employee)));
    }

    /**
     * Bilan du soir — « Je fais le point sur ma journée » (régime Priorités,
     * un seul bloc). Toujours écrit à 100% par l'employé : cet endpoint ne
     * fait jamais appel à l'IA pour rédiger quoi que ce soit, seulement pour
     * générer, ensuite, une question de clôture.
     */
    public function saveEvening(Request $request)
    {
        $employee = $this->requireEmployee($request);
        if ($employee instanceof JsonResponse) {
            return $employee;
        }

        $request->validate([
            'evening_status'         => 'nullable|in:faite,partielle,pas_faite',
            'evening_note'           => 'nullable|string',
            'secondary_1_done'       => 'boolean',
            'secondary_2_done'       => 'boolean',
            'evening_obstacle_self'  => 'nullable|string',
            'evening_obstacle_other' => 'nullable|string',
            'evening_smooth_day'     => 'boolean',
            'private_reflection'     => 'nullable|string',
        ]);

        $priority = DailyPriority::firstOrCreate(
            [
                'employee_id'   => $employee->id,
                'priority_date' => Carbon::today()->toDateString(),
            ],
            ['skipped' => true]
        );

        $priority->update([
            'evening_status'         => $request->evening_status,
            'evening_note'           => $request->evening_note,
            'secondary_1_done'       => $request->boolean('secondary_1_done'),
            'secondary_2_done'       => $request->boolean('secondary_2_done'),
            'evening_obstacle_self'  => $request->evening_obstacle_self,
            'evening_obstacle_other' => $request->evening_obstacle_other,
            'evening_smooth_day'     => $request->boolean('evening_smooth_day'),
            'private_reflection'     => $request->private_reflection,
            'evening_completed_at'   => Carbon::now(),
        ]);

        // Coda IA : une question, jamais une consigne. En mode dégradé (pas
        // de clé configurée), le bilan reste 100% fonctionnel sans elle.
        $question = null;
        $assistant = new AssistantService();
        if ($assistant->isConfigured()) {
            try {
                $question = $assistant->generateEveningQuestion([
                    'main_priority'    => $priority->main_priority ?: 'non renseignée',
                    'evening_status'   => $priority->evening_status ?: 'non renseigné',
                    'obstacle_summary' => $priority->evening_smooth_day
                        ? 'journée fluide'
                        : trim(($priority->evening_obstacle_self ?? '') . ' ' . ($priority->evening_obstacle_other ?? '')),
                ]);
                $priority->update(['ai_evening_question' => $question]);
            } catch (\Throwable $e) {
                report($e);
                $question = null;
            }
        }

        return response()->json(array_merge([
            'success'     => true,
            'priority'    => $priority,
            'ai_question' => $question,
        ], $this->computeStats($employee)));
    }

    /**
     * Enregistre la réponse de l'employé à la question IA du soir
     * (for intérieur — jamais lue par le responsable).
     */
    public function answerEveningQuestion(Request $request)
    {
        $employee = $this->requireEmployee($request);
        if ($employee instanceof JsonResponse) {
            return $employee;
        }

        $request->validate([
            'answer' => 'required|string',
        ]);

        $priority = DailyPriority::where('employee_id', $employee->id)
            ->where('priority_date', Carbon::today()->toDateString())
            ->first();

        if (!$priority) {
            return response()->json([
                'success' => false,
                'message' => 'Bilan du soir introuvable',
            ], 404);
        }

        $priority->update(['ai_evening_answer' => $request->answer]);

        return response()->json(['success' => true]);
    }

    /**
     * État du bilan du soir du jour (fait ou non).
     */
    public function todayEvening(Request $request)
    {
        $employee = $this->requireEmployee($request);
        if ($employee instanceof JsonResponse) {
            return $employee;
        }

        $priority = DailyPriority::where('employee_id', $employee->id)
            ->where('priority_date', Carbon::today()->toDateString())
            ->first();

        return response()->json([
            'success' => true,
            'done'    => $priority?->evening_completed_at !== null,
        ]);
    }

    /**
     * @return Employee|JsonResponse Employee si autorisé, sinon une
     *  réponse 403 déjà prête à être retournée par l'appelant.
     */
    private function requireEmployee(Request $request)
    {
        $employee = $request->user();
        if (!$employee instanceof Employee) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé',
            ], 403);
        }

        return $employee;
    }

    /**
     * « Ce que je ferais différemment » écrit hier soir (for intérieur),
     * rappelé une seule fois le matin suivant — l'employé doit le
     * reformuler, jamais reporté automatiquement dans la priorité.
     */
    private function rappelVeille(Employee $employee): ?string
    {
        $veille = DailyPriority::where('employee_id', $employee->id)
            ->where('priority_date', Carbon::yesterday()->toDateString())
            ->first();

        $reflection = $veille?->private_reflection;

        return $reflection ? trim($reflection) : null;
    }

    /**
     * Capital de progrès et série, calculés de façon déterministe à partir
     * de l'historique du rituel — jamais une valeur générée par l'IA ou
     * codée en dur côté client.
     */
    private function computeStats(Employee $employee): array
    {
        $quarterStart = Carbon::now()->firstOfQuarter()->toDateString();
        $today = Carbon::today()->toDateString();

        $entriesByDate = DailyPriority::where('employee_id', $employee->id)
            ->where('priority_date', '>=', $quarterStart)
            ->get()
            ->keyBy(fn (DailyPriority $e) => $e->priority_date->toDateString());

        $capital = 0;
        foreach ($entriesByDate as $date => $entry) {
            if ($this->journeePriseEnMain($entry, $date, $today)) {
                $capital++;
            }
            if ($entry->obstacle_self || $entry->obstacle_other) {
                $capital++;
            }
        }

        $joker = (int) config('ritual.joker_days_per_week', 1);

        return [
            'capital' => $capital,
            'serie'   => $this->computeStreak($entriesByDate, $today, $joker),
        ];
    }

    /**
     * « Journée prise en main » : rituel du matin fait, et — pour les
     * jours déjà passés — bilan du soir bouclé aussi. Le jour courant ne
     * requiert que le matin (le soir n'est pas encore exigible).
     */
    private function journeePriseEnMain(?DailyPriority $entry, string $date, string $today): bool
    {
        if (!$entry || $entry->skipped || !$entry->main_priority) {
            return false;
        }

        if ($date === $today) {
            return true;
        }

        return $entry->evening_completed_at !== null;
    }

    /**
     * Jours consécutifs de fidélité au rituel, tolérance jours-joker
     * (jusqu'à N jours manqués par semaine glissante sans casser la série).
     *
     * @param Collection<string, DailyPriority> $entriesByDate
     */
    private function computeStreak(Collection $entriesByDate, string $today, int $jokerPerWeek): int
    {
        $streak = 0;
        $jokersUsedThisWeek = 0;
        $cursor = Carbon::today();

        for ($i = 0; $i < 366; $i++) {
            $date = $cursor->toDateString();
            $entry = $entriesByDate->get($date);

            if ($this->journeePriseEnMain($entry, $date, $today)) {
                $streak++;
            } elseif ($jokersUsedThisWeek < $jokerPerWeek) {
                $jokersUsedThisWeek++;
            } else {
                break;
            }

            if ($cursor->dayOfWeekIso === 1) {
                $jokersUsedThisWeek = 0;
            }

            $cursor->subDay();
        }

        return $streak;
    }
}
