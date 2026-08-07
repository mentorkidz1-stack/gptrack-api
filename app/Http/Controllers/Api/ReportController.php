<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Leave;
use Illuminate\Http\Request;
use Carbon\Carbon;


class ReportController extends Controller
{

    /**
     * Rapport de présence filtrable : période (par défaut aujourd'hui),
     * site, employé, statut. Une ligne par employé et par jour dans la
     * période — plus la seule vue "aujourd'hui" d'avant, qui empêchait
     * tout archivage ou calcul de paie sur plusieurs jours.
     */
    public function attendance(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'site_id' => 'nullable|integer',
            'employee_id' => 'nullable|integer',
            'status' => 'nullable|in:present,absent,late,leave',
        ]);

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : Carbon::today();

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->startOfDay()
            : Carbon::today();

        // Jamais plus de 92 jours d'un coup (~3 mois) : au-delà, le rapport
        // devient trop lourd à générer et à lire pour rester utile.
        if ($dateFrom->diffInDays($dateTo) > 92) {
            $dateTo = $dateFrom->copy()->addDays(92);
        }

        $employeesQuery = Employee::with('site');
        if ($request->filled('site_id')) {
            $employeesQuery->where('site_id', $request->site_id);
        }
        if ($request->filled('employee_id')) {
            $employeesQuery->where('id', $request->employee_id);
        }
        $employees = $employeesQuery->get();

        // Une seule requête pour toute la période, regroupée ensuite en
        // mémoire par employé+jour — plutôt qu'une requête par employé et
        // par jour, qui deviendrait très coûteuse sur une longue période.
        $attendances = Attendance::whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('check_time', '>=', $dateFrom)
            ->whereDate('check_time', '<=', $dateTo)
            ->orderBy('check_time')
            ->get()
            ->groupBy(fn ($a) => $a->employee_id . '_' . $a->check_time->format('Y-m-d'));

        // Congés couvrant la période, regroupés par employé pour un test
        // rapide "en congé ce jour-là ?" sans requête par jour.
        $leaves = Leave::whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('start_date', '<=', $dateTo)
            ->whereDate('end_date', '>=', $dateFrom)
            ->get()
            ->groupBy('employee_id');

        $rows = collect();
        for ($date = $dateFrom->copy(); $date->lte($dateTo); $date->addDay()) {
            $dateKey = $date->format('Y-m-d');

            foreach ($employees as $employee) {
                $dayAttendances = $attendances->get($employee->id . '_' . $dateKey, collect());
                $arrival = $dayAttendances->where('attendance_type', 'arrival')->last();
                $departure = $dayAttendances->where('attendance_type', 'departure')->last();

                $onLeave = $leaves->get($employee->id, collect())
                    ->contains(fn (Leave $leave) => $date->between($leave->start_date, $leave->end_date));

                if ($arrival && $arrival->status === 'success') {
                    $status = 'present';
                } elseif ($onLeave) {
                    $status = 'leave';
                } else {
                    $status = 'absent';
                }
                $late = (bool) ($arrival->is_late ?? false);

                $rows->push([
                    'date' => $dateKey,
                    'employee' => $employee->full_name,
                    'site' => $employee->site->name ?? null,
                    'status' => $status,
                    'late' => $late,
                    'arrival_time' => $arrival?->check_time->format('H:i'),
                    'departure_time' => $departure?->check_time->format('H:i'),
                    'worked_minutes' => $departure->work_minutes ?? 0,
                ]);
            }
        }

        if ($request->filled('status')) {
            $rows = $request->status === 'late'
                ? $rows->where('late', true)
                : $rows->where('status', $request->status);
        }

        $rows = $rows->values();

        return response()->json([
            'success' => true,
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d'),
            'summary' => [
                'total_rows' => $rows->count(),
                'present' => $rows->where('status', 'present')->count(),
                'absent' => $rows->where('status', 'absent')->count(),
                'leave' => $rows->where('status', 'leave')->count(),
                'late' => $rows->where('late', true)->count(),
            ],
            'employees' => $rows,
        ]);
    }

}
