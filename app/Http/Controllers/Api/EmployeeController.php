<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Site;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Services\SmsService;

class EmployeeController extends Controller
{


    public function index()
    {

        $employees = Employee::with('site')
            ->orderBy('created_at','desc')
            ->get()
            ->map(function($employee){


                return [

                    'id'=>$employee->id,

                    'name'=>$employee->full_name,

                    'phone'=>$employee->phone,

                    'job_title'=>$employee->job_title,


                    'site'=>$employee->site
                        ? $employee->site->name
                        : null,


                    'is_enrolled'=>(bool)$employee->is_enrolled,


                    'status'=>$employee->status == 1
                        ? 'active'
                        : 'inactive',


                    'created_at'=>$employee->created_at

                ];


            });


        return response()->json([

            'success'=>true,

            'total'=>$employees->count(),

            'employees'=>$employees

        ]);

    }






    public function store(Request $request)
    {
        if ($request->filled('phone')) {
            $request->merge(['phone' => SmsService::toE164($request->phone)]);
        }

        $request->validate([
            'full_name'      => 'required|string',
            'phone'          => 'required|string|unique:employees,phone',
            'site_id'        => 'required|integer',
            'job_title'      => 'nullable|string',
            'employee_code'  => 'nullable|string',
        ]);

        // Le site est cherché dans les sites de l'entreprise courante
        // uniquement (Site est aussi cloisonné) : impossible de rattacher
        // un employé au site d'une autre entreprise.
        $site = Site::find($request->site_id);
        if (!$site) {
            return response()->json([
                'success' => false,
                'message' => 'Site invalide',
            ], 422);
        }

        $company = Company::find($request->user()->company_id);
        $limit = $company?->employeeLimit();
        if ($limit !== null && Employee::count() >= $limit) {
            return response()->json([
                'success' => false,
                'message' => "Limite du plan atteinte ($limit employés max). Passez à un plan supérieur.",
            ], 422);
        }

        // company_id est fixé automatiquement à la création (cf. trait
        // BelongsToCompany), jamais depuis une valeur du client.
        $employee = Employee::create([
            'site_id'       => $site->id,
            'full_name'     => $request->full_name,
            'phone'         => $request->phone,
            'job_title'     => $request->job_title,
            'employee_code' => $request->employee_code,
            'status'        => true,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Employé créé',
            'employee' => $employee,
        ], 201);
    }




    public function update(Request $request, $id)
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employé introuvable',
            ], 404);
        }

        if ($request->filled('phone')) {
            $request->merge(['phone' => SmsService::toE164($request->phone)]);
        }

        $request->validate([
            'full_name'      => 'sometimes|string',
            'phone'          => 'sometimes|string|unique:employees,phone,' . $employee->id,
            'site_id'        => 'sometimes|integer',
            'job_title'      => 'nullable|string',
            'employee_code'  => 'nullable|string',
            'status'         => 'sometimes|boolean',
        ]);

        if ($request->filled('site_id')) {
            $site = Site::find($request->site_id);
            if (!$site) {
                return response()->json([
                    'success' => false,
                    'message' => 'Site invalide',
                ], 422);
            }
        }

        $employee->update($request->only([
            'full_name', 'phone', 'site_id', 'job_title',
            'employee_code', 'status',
        ]));

        return response()->json([
            'success'  => true,
            'message'  => 'Employé mis à jour',
            'employee' => $employee->fresh(),
        ]);
    }




    public function destroy($id)
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employé introuvable',
            ], 404);
        }

        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employé supprimé',
        ]);
    }




    public function show($id)
    {


        $employee = Employee::with([

            'site',

            'attendances'

        ])->find($id);



        if(!$employee){


            return response()->json([

                'success'=>false,

                'message'=>'Employé introuvable'

            ],404);

        }



        return response()->json([


            'success'=>true,


            'employee'=>$employee


        ]);

    }
public function stats($id)
{

    $employee = Employee::find($id);


    if(!$employee){

        return response()->json([

            'success'=>false,

            'message'=>'Employé introuvable'

        ],404);

    }


    $attendances = Attendance::where(
        'employee_id',
        $id
    )->orderBy(
        'check_time',
        'desc'
    )->get();



    $presentDays = Attendance::where(
        'employee_id',
        $id
    )
    ->where('attendance_type','arrival')
    ->where('status','success')
    ->selectRaw('DATE(check_time) as day')
    ->distinct()
    ->count();



    return response()->json([


        'success'=>true,


        'employee'=>$employee->full_name,


        'statistics'=>[


            'present_days'=>$presentDays,


            'late_count'=>$attendances
                ->where('is_late',true)
                ->count(),



            'failed_face'=>$attendances
                ->where('status','face_failed')
                ->count(),



            'outside_zone'=>$attendances
                ->where('status','outside_zone')
                ->count(),



            'total_work_minutes'=>$attendances
                ->sum('work_minutes'),



            'total_work_hours'=>round(
                $attendances
                ->sum('work_minutes') / 60,
                2
            )

        ],



        'last_attendances'=>

            $attendances
            ->take(5)
            ->map(function($item){


                return [

                    'type'=>$item->attendance_type,


                    'status'=>$item->status,


                    'time'=>$item->check_time,


                    'worked_minutes'=>$item->work_minutes,


                    'late'=>$item->is_late

                ];


            })

    ]);

}
}