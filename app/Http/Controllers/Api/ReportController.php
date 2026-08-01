<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Attendance;
use Carbon\Carbon;


class ReportController extends Controller
{


    public function attendance()
    {


        $today = Carbon::today();



        $employees = Employee::with('site')
            ->get();



        $report = $employees->map(function($employee) use ($today){



            $arrival = Attendance::where(
                'employee_id',
                $employee->id
            )
            ->where('attendance_type','arrival')
            ->whereDate(
                'check_time',
                $today
            )
            ->latest()
            ->first();




            $departure = Attendance::where(
                'employee_id',
                $employee->id
            )
            ->where('attendance_type','departure')
            ->whereDate(
                'check_time',
                $today
            )
            ->latest()
            ->first();





            return [


                'employee'=>$employee->full_name,


                'site'=>$employee->site->name ?? null,



                'status'=>

                    $arrival &&
                    $arrival->status === 'success'

                    ?

                    'present'

                    :

                    'absent',




                'arrival_time'=>

                    $arrival

                    ?

                    $arrival->check_time->format('H:i')

                    :

                    null,




                'departure_time'=>

                    $departure

                    ?

                    $departure->check_time->format('H:i')

                    :

                    null,




                'worked_minutes'=>

                    $departure->work_minutes ?? 0,




                'late'=>

                    $arrival->is_late ?? false



            ];

        });





        return response()->json([


            'success'=>true,


            'date'=>$today->format('Y-m-d'),



            'summary'=>[


                'total_employees'=>$employees->count(),


                'present'=>$report
                    ->where('status','present')
                    ->count(),



                'absent'=>$report
                    ->where('status','absent')
                    ->count(),



                'late'=>$report
                    ->where('late',true)
                    ->count()


            ],




            'employees'=>$report


        ]);



    }



}