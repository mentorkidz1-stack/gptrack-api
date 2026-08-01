<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Attendance;
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