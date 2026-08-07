<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Leave;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats()
    {
        $today = Carbon::today();

        $totalEmployees = Employee::count();

        $presentToday = Attendance::whereDate(
            'check_time',
            $today
        )
        ->where('attendance_type', 'arrival')
        ->where('status', 'success')
        ->distinct('employee_id')
        ->count();

        $absentToday = $totalEmployees - $presentToday;

        $failedAttendances = Attendance::whereDate(
            'check_time',
            $today
        )
        ->whereIn('status', [
            'face_failed',
            'outside_zone'
        ])
        ->count();

        return response()->json([
            'total_employees' => $totalEmployees,
            'present_today' => $presentToday,
            'absent_today' => $absentToday,
            'failed_attendances' => $failedAttendances
        ]);
    }

public function todayAttendances()
{

    $attendances = Attendance::with([
        'employee',
        'site',
        'schedule.subject',
        'schedule.classLevel',
    ])
    ->whereDate('check_time', today())
    ->orderBy('check_time','desc')
    ->get()
    ->map(function($attendance){


        return [

            'id'=>$attendance->id,


            'employee'=>$attendance->employee 
                ? $attendance->employee->full_name
                : 'Employé supprimé',


            'site'=>$attendance->site
                ? $attendance->site->name
                : 'Site supprimé',


            'attendance_type'=>$attendance->attendance_type,


            'status'=>$attendance->status,


            'selfie_photo'=>$attendance->selfie_photo,


            'face_match_score'=>$attendance->face_match_score,


            'latitude'=>$attendance->latitude,


            'longitude'=>$attendance->longitude,


            'is_inside_zone'=>$attendance->is_inside_zone,


            'is_late'=>$attendance->is_late,


            'check_time'=>$attendance->check_time,


            'work_minutes'=>$attendance->work_minutes,


            // Présents seulement pour une attestation de cours (cahier de
            // texte) — null pour un pointage classique.
            'subject'=>$attendance->schedule?->subject?->name,


            'class_level'=>$attendance->schedule?->classLevel?->name,


            'taux_realise'=>$attendance->taux_realise,


            'notes'=>$attendance->notes,

        ];

    });


    return response()->json([

        'success'=>true,

        'total'=>$attendances->count(),

        'attendances'=>$attendances

    ]);

}
public function lateEmployees()
{
    $lateEmployees = Attendance::with('employee')
        ->where('attendance_type', 'arrival')
        ->where('is_late', true)
        ->orderBy('check_time')
        ->get()
        ->map(function ($attendance) {

            return [
                'employee' => $attendance->employee 
                    ? $attendance->employee->full_name 
                    : 'Inconnu',

                'arrival_time' => Carbon::parse(
                    $attendance->check_time
                )->format('H:i'),

                'face_match_score' => $attendance->face_match_score,

                'status' => $attendance->status
            ];

        });


    return response()->json([
        'success' => true,
        'total' => $lateEmployees->count(),
        'late_employees' => $lateEmployees
    ]);
}
    /**
     * Vue "en direct" du jour pour le DG/RH : statut de chaque employé
     * (arrivé à l'heure / en retard / pas encore pointé / hors créneau),
     * un résumé chiffré, et les employés en retard répété sur les 30
     * derniers jours — trois besoins réunis en un seul appel pour
     * alimenter la nouvelle page "Aujourd'hui" du dashboard.
     */
    public function liveStatus()
    {
        $today = Carbon::today();
        $now = Carbon::now();

        $employees = Employee::with('site')->get();

        // Employés en congé aujourd'hui : une absence déclarée à l'avance
        // ne doit jamais ressortir comme "absent" ou "pas encore arrivé".
        $onLeaveToday = Leave::whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->pluck('employee_id')
            ->flip();

        $statuses = $employees->map(function (Employee $employee) use ($today, $now, $onLeaveToday) {
            $site = $employee->site;

            $arrival = Attendance::where('employee_id', $employee->id)
                ->where('attendance_type', 'arrival')
                ->where('status', 'success')
                ->whereDate('check_time', $today)
                ->first();

            $expectedStart = $site && $site->work_start_time
                ? Carbon::parse($site->work_start_time)
                : null;

            if ($arrival) {
                $status = $arrival->is_late ? 'late' : 'on_time';
            } elseif ($onLeaveToday->has($employee->id)) {
                $status = 'leave';
            } elseif ($expectedStart && $now->lt($expectedStart)) {
                $status = 'pending';
            } elseif ($expectedStart) {
                $status = 'absent';
            } else {
                $status = 'no_schedule';
            }

            return [
                'employee_id' => $employee->id,
                'employee' => $employee->full_name,
                'site' => $site?->name,
                'status' => $status,
                'arrival_time' => $arrival
                    ? Carbon::parse($arrival->check_time)->format('H:i')
                    : null,
            ];
        })->values();

        $summary = [
            'total' => $statuses->count(),
            'on_time' => $statuses->where('status', 'on_time')->count(),
            'late' => $statuses->where('status', 'late')->count(),
            'absent' => $statuses->where('status', 'absent')->count(),
            'pending' => $statuses->where('status', 'pending')->count(),
            'leave' => $statuses->where('status', 'leave')->count(),
        ];

        // Retards répétés sur les 30 derniers jours (seuil : 3 retards ou
        // plus) — signalés ici plutôt que par email/SMS, faute de service
        // d'envoi configuré pour l'instant côté entreprise.
        $repeatOffenders = Attendance::selectRaw('employee_id, count(*) as late_count')
            ->where('attendance_type', 'arrival')
            ->where('is_late', true)
            ->whereDate('check_time', '>=', $today->copy()->subDays(30))
            ->groupBy('employee_id')
            ->having('late_count', '>=', 3)
            ->with('employee')
            ->orderByDesc('late_count')
            ->get()
            ->map(fn ($row) => [
                'employee' => $row->employee?->full_name ?? 'Employé supprimé',
                'late_count' => (int) $row->late_count,
            ]);

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'employees' => $statuses,
            'repeat_offenders' => $repeatOffenders,
        ]);
    }

    public function absentEmployees()
{
    $employees = Employee::with('site')
        ->get()
        ->filter(function ($employee) {

            $present = Attendance::where('employee_id', $employee->id)
                ->whereDate('check_time', today())
                ->where('attendance_type', 'arrival')
                ->where('status', 'success')
                ->exists();


            return !$present;

        })
        ->map(function ($employee) {

            return [
                'employee' => $employee->full_name,

                'site' => $employee->site
                    ? $employee->site->name
                    : 'Aucun site',

                'status' => 'absent'
            ];

        })
        ->values();


    return response()->json([
        'success' => true,
        'total' => $employees->count(),
        'absent_employees' => $employees
    ]);
}
}