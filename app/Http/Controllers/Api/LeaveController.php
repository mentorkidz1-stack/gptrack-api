<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $query = Leave::with('employee')->orderByDesc('start_date');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        return response()->json([
            'success' => true,
            'leaves' => $query->get()->map(fn (Leave $leave) => [
                'id' => $leave->id,
                'employee_id' => $leave->employee_id,
                'employee' => $leave->employee?->full_name,
                'start_date' => $leave->start_date->format('Y-m-d'),
                'end_date' => $leave->end_date->format('Y-m-d'),
                'type' => $leave->type,
                'reason' => $leave->reason,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'nullable|string|in:conge,maladie,autre',
            'reason' => 'nullable|string|max:500',
        ]);

        $employee = Employee::find($request->employee_id);
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employé invalide',
            ], 422);
        }

        $leave = Leave::create([
            'employee_id' => $employee->id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'type' => $request->type ?? 'conge',
            'reason' => $request->reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Congé enregistré',
            'leave' => $leave->load('employee'),
        ], 201);
    }

    public function destroy($id)
    {
        $leave = Leave::findOrFail($id);
        $leave->delete();

        return response()->json([
            'success' => true,
            'message' => 'Congé supprimé',
        ]);
    }
}
