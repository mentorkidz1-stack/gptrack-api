<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\RitualController;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\ClassLevelController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\CurriculumController;
use App\Http\Controllers\Api\LeaveController;



/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------
*/


Route::prefix('auth')->middleware('throttle:6,1')->group(function(){



    /*
    |--------------------------------------------------------------------------
    | Connexion DG / RH
    |--------------------------------------------------------------------------
    */


    Route::post(
        '/login',
        [AuthController::class,'login']
    );



    /*
    |--------------------------------------------------------------------------
    | Connexion employé
    |--------------------------------------------------------------------------
    */


    Route::post(
        '/check-phone',
        [AuthController::class,'checkPhone']
    );


    Route::post(
        '/request-otp',
        [AuthController::class,'requestOtp']
    );


    Route::post(
        '/verify-otp',
        [AuthController::class,'verifyOtp']
    );


});



/*
|--------------------------------------------------------------------------
| INSCRIPTION SELF-SERVE D'UNE ENTREPRISE
|--------------------------------------------------------------------------
*/


Route::middleware('throttle:6,1')->post(
    '/companies/register',
    [CompanyController::class,'register']
);



/*
|--------------------------------------------------------------------------
| FORMULAIRE DE CONTACT (SITE VITRINE)
|--------------------------------------------------------------------------
*/


Route::middleware('throttle:6,1')->post(
    '/contact-requests',
    [\App\Http\Controllers\Api\ContactRequestController::class,'store']
);



/*
|--------------------------------------------------------------------------
| WEBHOOK FACTURATION (FedaPay)
| Route publique : authenticité garantie par la signature, pas par Sanctum.
|--------------------------------------------------------------------------
*/


Route::post(
    '/billing/webhook',
    [BillingController::class,'webhook']
);







/*
|--------------------------------------------------------------------------
| ROUTES PROTEGEES SANCTUM
|--------------------------------------------------------------------------
*/


Route::middleware('auth:sanctum')->group(function(){



    /*
    |--------------------------------------------------------------------------
    | Utilisateur connecté
    |--------------------------------------------------------------------------
    */
    Route::post('/ritual/morning', [RitualController::class, 'saveMorning']);
Route::post('/ritual/morning/today', [RitualController::class, 'todayMorning']);
Route::post('/ritual/evening', [RitualController::class, 'saveEvening']);
Route::post('/ritual/evening/answer', [RitualController::class, 'answerEveningQuestion']);
Route::post('/ritual/evening/today', [RitualController::class, 'todayEvening']);
Route::middleware('throttle:20,1')->post('/assistant/chat', [AssistantController::class, 'chat']);
Route::post('/attendance/today-status', [AttendanceController::class, 'todayStatus']);
    Route::post('/attendance/history', [AttendanceController::class, 'history']);
    Route::post('/schedule/today', [ScheduleController::class, 'today']);
    Route::get('/user',function(Request $request){

        $user = $request->user();

        return response()->json([

            'success'=>true,

            'user'=>$user,

            'company_type'=>$user->company?->type ?? 'entreprise'

        ]);


    });







    /*
    |--------------------------------------------------------------------------
    | CREATION COMPTE RH
    | DG uniquement
    |--------------------------------------------------------------------------
    */


    Route::middleware('role:dg')
    ->group(function(){


        Route::post(
            '/users/create-rh',
            [UserController::class,'createRh']
        );


    });








    /*
    |--------------------------------------------------------------------------
    | DASHBOARD DG / RH
    |--------------------------------------------------------------------------
    */


    Route::middleware('role:dg,rh')
    ->group(function(){



        Route::get(
            '/dashboard/stats',
            [DashboardController::class,'stats']
        );



        Route::get(
            '/dashboard/today-attendances',
            [DashboardController::class,'todayAttendances']
        );



        Route::get(
            '/dashboard/late-employees',
            [DashboardController::class,'lateEmployees']
        );



        Route::get(
            '/dashboard/absent-employees',
            [DashboardController::class,'absentEmployees']
        );



        Route::get(
            '/dashboard/live-status',
            [DashboardController::class,'liveStatus']
        );


    });









    /*
    |--------------------------------------------------------------------------
    | POINTAGE
    |--------------------------------------------------------------------------
    */


    Route::post(
        '/attendance/check-in',
        [AttendanceController::class,'checkIn']
    );

    Route::post(
        '/attendance/check-zone',
        [AttendanceController::class,'checkZone']
    );

    Route::post(
        '/auth/enroll-face',
        [AuthController::class,'enrollFace']
    );

    Route::post(
        '/auth/device-token',
        [AuthController::class,'saveDeviceToken']
    );

    Route::post(
        '/save-token',
        [AuthController::class,'saveFcmToken']
    );










    /*
    |--------------------------------------------------------------------------
    | SITES
    | Lecture DG/RH
    | Modification DG seulement
    |--------------------------------------------------------------------------
    */


    Route::get(
        '/sites',
        [SiteController::class,'index']
    );



    Route::get(
        '/sites/{id}',
        [SiteController::class,'show']
    );



    Route::middleware('role:dg')
    ->group(function(){


        Route::post(
            '/sites',
            [SiteController::class,'store']
        );


        Route::put(
            '/sites/{id}',
            [SiteController::class,'update']
        );


    });










    /*
    |--------------------------------------------------------------------------
    | EMPLOYES DG / RH
    |--------------------------------------------------------------------------
    */


    Route::middleware('role:dg,rh')
    ->prefix('employees')
    ->group(function(){



        Route::get(
            '/',
            [EmployeeController::class,'index']
        );



        Route::post(
            '/',
            [EmployeeController::class,'store']
        );



        Route::get(
            '/{id}',
            [EmployeeController::class,'show']
        );



        Route::put(
            '/{id}',
            [EmployeeController::class,'update']
        );



        Route::delete(
            '/{id}',
            [EmployeeController::class,'destroy']
        );



        Route::get(
            '/{id}/stats',
            [EmployeeController::class,'stats']
        );


    });









    /*
    |--------------------------------------------------------------------------
    | RAPPORTS DG / RH
    |--------------------------------------------------------------------------
    */


    Route::middleware('role:dg,rh')
    ->group(function(){



        Route::get(
            '/reports/attendance',
            [ReportController::class,'attendance']
        );



        Route::get('/leaves', [LeaveController::class, 'index']);
        Route::post('/leaves', [LeaveController::class, 'store']);
        Route::delete('/leaves/{id}', [LeaveController::class, 'destroy']);


    });



    /*
    |--------------------------------------------------------------------------
    | ECOLE : MATIERES / CLASSES / CRENEAUX / PROGRESSION
    | DG/RH — extension pour les établissements scolaires (n'affecte pas
    | les entreprises existantes : ces routes ne sont utilisées que par
    | qui en a besoin).
    |--------------------------------------------------------------------------
    */


    Route::middleware('role:dg,rh')
    ->group(function(){


        Route::get('/subjects', [SubjectController::class, 'index']);
        Route::post('/subjects', [SubjectController::class, 'store']);
        Route::put('/subjects/{id}', [SubjectController::class, 'update']);
        Route::delete('/subjects/{id}', [SubjectController::class, 'destroy']);

        Route::get('/class-levels', [ClassLevelController::class, 'index']);
        Route::post('/class-levels', [ClassLevelController::class, 'store']);
        Route::put('/class-levels/{id}', [ClassLevelController::class, 'update']);
        Route::delete('/class-levels/{id}', [ClassLevelController::class, 'destroy']);

        Route::get('/schedules', [ScheduleController::class, 'index']);
        Route::post('/schedules', [ScheduleController::class, 'store']);
        Route::put('/schedules/{id}', [ScheduleController::class, 'update']);
        Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy']);

        Route::post('/curriculum/import', [CurriculumController::class, 'importPreview']);
        Route::post('/curriculum/import/confirm', [CurriculumController::class, 'importConfirm']);
        Route::get('/curriculum/progression', [CurriculumController::class, 'progression']);
        Route::put('/curriculum-weeks/{id}', [CurriculumController::class, 'update']);
        Route::delete('/curriculum-weeks/{id}', [CurriculumController::class, 'destroy']);


    });



    /*
    |--------------------------------------------------------------------------
    | FACTURATION
    | Lecture DG/RH, changement de plan DG seulement
    |--------------------------------------------------------------------------
    */


    Route::middleware('role:dg,rh')
    ->group(function(){


        Route::get(
            '/billing',
            [BillingController::class,'show']
        );


    });


    Route::middleware('role:dg')
    ->group(function(){


        Route::post(
            '/billing/subscribe',
            [BillingController::class,'subscribe']
        );


    });




});


Route::middleware('auth:sanctum')->post(
    '/auth/logout',
    [AuthController::class,'logout']
);