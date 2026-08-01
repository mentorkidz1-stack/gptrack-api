<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ClassLevel;
use App\Models\CurriculumWeek;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * Créneaux de l'entreprise courante (cloisonné automatiquement par le
     * trait BelongsToCompanyThroughEmployee), filtrable par employé.
     */
    public function index(Request $request)
    {
        $query = Schedule::with(['employee', 'subject', 'classLevel'])
            ->orderBy('day_of_week')
            ->orderBy('start_time');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        return response()->json([
            'success' => true,
            'schedules' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id'     => 'required|integer',
            'subject_id'      => 'required|integer',
            'class_level_id'  => 'required|integer',
            'day_of_week'     => 'required|integer|min:0|max:6',
            'start_time'      => 'required',
            'end_time'        => 'required',
        ]);

        // Chaque référence est cherchée dans les données de l'entreprise
        // courante uniquement (tous cloisonnés) : impossible de créer un
        // créneau pointant vers l'employé/la matière/la classe d'une autre
        // entreprise.
        $employee = Employee::find($request->employee_id);
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employé invalide',
            ], 422);
        }

        $subject = Subject::find($request->subject_id);
        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => 'Matière invalide',
            ], 422);
        }

        $classLevel = ClassLevel::find($request->class_level_id);
        if (!$classLevel) {
            return response()->json([
                'success' => false,
                'message' => 'Classe invalide',
            ], 422);
        }

        $schedule = Schedule::create([
            'employee_id'    => $employee->id,
            'subject_id'     => $subject->id,
            'class_level_id' => $classLevel->id,
            'day_of_week'    => $request->day_of_week,
            'start_time'     => $request->start_time,
            'end_time'       => $request->end_time,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Créneau créé',
            'schedule' => $schedule->load(['employee', 'subject', 'classLevel']),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $schedule = Schedule::findOrFail($id);

        $request->validate([
            'subject_id'     => 'sometimes|integer',
            'class_level_id' => 'sometimes|integer',
            'day_of_week'    => 'sometimes|integer|min:0|max:6',
            'start_time'     => 'sometimes',
            'end_time'       => 'sometimes',
        ]);

        if ($request->filled('subject_id') && !Subject::find($request->subject_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Matière invalide',
            ], 422);
        }

        if ($request->filled('class_level_id') && !ClassLevel::find($request->class_level_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Classe invalide',
            ], 422);
        }

        $schedule->update($request->only([
            'subject_id',
            'class_level_id',
            'day_of_week',
            'start_time',
            'end_time',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Créneau modifié',
            'schedule' => $schedule->fresh(['employee', 'subject', 'classLevel']),
        ]);
    }

    public function destroy($id)
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Créneau supprimé',
        ]);
    }

    /**
     * Créneaux du jour pour l'employé connecté (côté mobile), avec pour
     * chacun son statut d'attestation et le contenu de la semaine de
     * progression courante (si une progression a été importée pour cette
     * matière+classe).
     */
    public function today(Request $request)
    {
        $employee = $request->user();
        if (!$employee instanceof Employee) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé',
            ], 403);
        }

        // 0 = lundi ... 6 = dimanche, aligné sur la convention day_of_week.
        $todayDow = Carbon::now()->dayOfWeekIso - 1;

        $schedules = Schedule::with(['subject', 'classLevel'])
            ->where('employee_id', $employee->id)
            ->where('day_of_week', $todayDow)
            ->orderBy('start_time')
            ->get();

        $result = $schedules->map(function (Schedule $schedule) {
            $attested = Attendance::where('schedule_id', $schedule->id)
                ->whereDate('check_time', Carbon::today())
                ->first();

            $curriculumWeek = CurriculumWeek::where('subject_id', $schedule->subject_id)
                ->where('class_level_id', $schedule->class_level_id)
                ->where('is_teaching_week', true)
                ->whereDate('period_start', '<=', Carbon::today())
                ->whereDate('period_end', '>=', Carbon::today())
                ->first();

            return [
                'schedule_id'     => $schedule->id,
                'subject'         => $schedule->subject?->name,
                'class_level'     => $schedule->classLevel?->name,
                'start_time'      => $schedule->start_time,
                'end_time'        => $schedule->end_time,
                'can_attest_from' => $schedule->start_time,
                'attested'        => $attested !== null,
                'attested_at'     => $attested ? Carbon::parse($attested->check_time)->format('H:i') : null,
                'curriculum_week' => $curriculumWeek ? [
                    'id'                      => $curriculumWeek->id,
                    'situation_apprentissage' => $curriculumWeek->situation_apprentissage,
                    'activities_text'         => $curriculumWeek->activities_text,
                    'taux_prevu'              => $curriculumWeek->taux_prevu,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'schedules' => $result,
        ]);
    }
}
