<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;



class UserController extends Controller
{


    public function createRh(Request $request)
    {


        $request->validate([


            'name'=>'required',

            'email'=>'required|email|unique:users',

            'password'=>'required|min:6'


        ]);




        $user = User::create([


            'name'=>$request->name,


            'email'=>$request->email,


            'password'=>Hash::make($request->password),


            'role'=>'rh',


            // Le compte RH appartient toujours à l'entreprise du DG qui le
            // crée — jamais une valeur envoyée par le client.
            'company_id'=>$request->user()->company_id,


        ]);





        return response()->json([


            'success'=>true,


            'message'=>'Compte RH créé',


            'user'=>[

                'id'=>$user->id,

                'name'=>$user->name,

                'email'=>$user->email,

                'role'=>$user->role

            ]


        ]);


    }



}