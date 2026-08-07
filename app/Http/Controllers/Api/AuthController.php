<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;


class AuthController extends Controller
{


    /*
    |--------------------------------------------------------------------------
    | LOGIN DG / RH
    |--------------------------------------------------------------------------
    */


    public function login(Request $request)
    {


        $request->validate([

            'email'=>'required|email',

            'password'=>'required'

        ]);



        $user = User::where(
            'email',
            $request->email
        )->first();



        if(!$user){


            return response()->json([

                'success'=>false,

                'message'=>'Utilisateur introuvable'

            ],404);

        }




        if(!Hash::check(
            $request->password,
            $user->password
        )){


            return response()->json([

                'success'=>false,

                'message'=>'Mot de passe incorrect'

            ],401);

        }




        // Supprime anciens tokens

        $user->tokens()->delete();




        // Nouveau token

        $token = $user
            ->createToken('gptrack-mobile')
            ->plainTextToken;




        return response()->json([


            'success'=>true,


            'user'=>[


                'id'=>$user->id,


                'name'=>$user->name,


                'email'=>$user->email,


                'role'=>$user->role,


                'company_id'=>$user->company_id ?? null,


                'company_type'=>$user->company?->type ?? 'entreprise',


                'is_operator'=>config('services.operator_company_id')
                    && (string) $user->company_id === (string) config('services.operator_company_id'),


            ],


            'token'=>$token



        ]);



    }






    /*
    |--------------------------------------------------------------------------
    | LOGOUT DG/RH
    |--------------------------------------------------------------------------
    */


    public function logout(Request $request)
    {


        $request
            ->user()
            ->currentAccessToken()
            ->delete();



        return response()->json([


            'success'=>true,


            'message'=>'Déconnexion réussie'


        ]);

    }








    /*
    |--------------------------------------------------------------------------
    | Vérification téléphone employé
    |--------------------------------------------------------------------------
    */


    public function checkPhone(Request $request)
    {


        $request->validate([


            'phone'=>'required'


        ]);




        $employee = Employee::where(
            'phone',
            SmsService::toE164($request->phone)
        )->first();




        if(!$employee){


            return response()->json([


                'success'=>false,


                'message'=>'Employé introuvable'


            ],404);


        }




        return response()->json([


            'success'=>true,


            'employee'=>[


                'id'=>$employee->id,


                'name'=>$employee->full_name,


                'phone'=>$employee->phone,


                'is_enrolled'=>(bool)$employee->is_enrolled,


                'company_type'=>$employee->company?->type ?? 'entreprise',


                'company_name'=>$employee->company?->name,


                'job_title'=>$employee->job_title,


                'enrolled_at'=>$employee->enrolled_at,


            ]



        ]);



    }









    /*
    |--------------------------------------------------------------------------
    | Génération OTP
    |--------------------------------------------------------------------------
    */


    public function requestOtp(Request $request)
    {


        $request->validate([


            'phone'=>'required'


        ]);




        $employee = Employee::where(
            'phone',
            SmsService::toE164($request->phone)
        )->first();




        if(!$employee){


            return response()->json([


                'success'=>false,


                'message'=>'Employé introuvable'


            ],404);


        }





        $otp = rand(100000,999999);




        $employee->update([


            'otp'=>$otp,


            'otp_expires_at'=>Carbon::now()
                ->addMinutes(5)


        ]);




        $sent = (new SmsService())->send(
            $employee->phone,
            "Votre code GPTrack : $otp (valable 5 minutes)"
        );


        return response()->json([


            'success'=>true,


            'message'=>$sent ? 'OTP envoyé par SMS' : 'OTP généré',


            // Tant qu'aucun SMS n'a réellement pu être envoyé (Twilio non
            // configuré, ou échec d'envoi), le code est renvoyé dans la
            // réponse pour que l'app puisse l'afficher à l'écran — c'est
            // le seul moyen de se connecter tant que Twilio n'est pas
            // activé. Volontairement indépendant de APP_DEBUG : dès que
            // Twilio enverra vraiment le SMS ($sent = true), le code ne
            // sera plus jamais exposé, quel que soit le mode de l'app.
            'otp'=>$sent ? null : $otp



        ]);



    }









    /*
    |--------------------------------------------------------------------------
    | Validation OTP
    |--------------------------------------------------------------------------
    */


    public function verifyOtp(Request $request)
    {


        $request->validate([


            'phone'=>'required',


            'otp'=>'required'


        ]);





        $employee = Employee::where(
            'phone',
            SmsService::toE164($request->phone)
        )->first();




        if(!$employee){


            return response()->json([


                'success'=>false,


                'message'=>'Employé introuvable'


            ],404);



        }






        if($employee->otp != $request->otp){


            return response()->json([


                'success'=>false,


                'message'=>'OTP incorrect'


            ],400);


        }






        if(
            Carbon::now()
            ->greaterThan(
                $employee->otp_expires_at
            )
        ){


            return response()->json([


                'success'=>false,


                'message'=>'OTP expiré'


            ],400);


        }





        $employee->update([


            'phone_verified'=>true,


            'otp'=>null,


            'otp_expires_at'=>null


        ]);





        // Revoke anciens tokens puis emission d'un nouveau token Sanctum
        $employee->tokens()->delete();

        $token = $employee
            ->createToken('gptrack-employee')
            ->plainTextToken;


        return response()->json([


            'success'=>true,


            'message'=>'Téléphone validé',


            'employee_id'=>$employee->id,

            'is_enrolled'=>(bool)$employee->is_enrolled,

            'company_type'=>$employee->company?->type ?? 'entreprise',

            'token'=>$token



        ]);



    }











    /*
    |--------------------------------------------------------------------------
    | Enregistrement visage
    |--------------------------------------------------------------------------
    */


    public function enrollFace(Request $request)
    {


        $request->validate([


            'photo'=>'required'


        ]);


        $employee = $request->user();

        if(!$employee instanceof Employee){

            return response()->json([

                'success'=>false,

                'message'=>'Accès refusé'

            ],403);

        }





        $employee->update([


            'reference_photo'=>$request->photo,


            'is_enrolled'=>true,


            'enrolled_at'=>Carbon::now()


        ]);







        return response()->json([


            'success'=>true,


            'message'=>'Visage enregistré',



            'employee'=>[


                'id'=>$employee->id,


                'name'=>$employee->full_name,


                'reference_photo'=>$employee->reference_photo,


                'is_enrolled'=>$employee->is_enrolled



            ]



        ]);



    }


 
    public function saveDeviceToken(Request $request)
{


    $request->validate([

        'device_token'=>'required'

    ]);


    $employee = $request->user();

    if(!$employee instanceof Employee){

        return response()->json([

            'success'=>false,

            'message'=>'Accès refusé'

        ],403);

    }



    $employee->update([

        'device_token'=>$request->device_token

    ]);



    return response()->json([


        'success'=>true,


        'message'=>'Téléphone enregistré'


    ]);

}
public function saveFcmToken(Request $request)
{

    $request->validate([

        'token'=>'required'

    ]);


    $employee = $request->user();

    if(!$employee instanceof Employee){

        return response()->json([

            'success'=>false,

            'message'=>'Accès refusé'

        ],403);

    }


    $employee->update([

        'fcm_token'=>$request->token

    ]);


    return response()->json([

        'success'=>true,

        'message'=>'Token enregistré'

    ]);

}
}