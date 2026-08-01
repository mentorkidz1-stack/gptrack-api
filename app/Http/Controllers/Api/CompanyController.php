<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CompanyController extends Controller
{
    /**
     * Inscription self-serve : crée l'entreprise et son premier compte DG,
     * puis connecte directement ce DG (retourne un token Sanctum), comme
     * le fait AuthController::login.
     */
    public function register(Request $request)
    {
        $request->validate([
            'company_name'  => 'required|string|max:255',
            'company_phone' => 'required|string|max:30',
            'company_email' => 'nullable|email',
            'company_type'  => 'nullable|in:entreprise,ecole',

            'dg_name'     => 'required|string|max:255',
            'dg_email'    => 'required|email|unique:users,email',
            'dg_password' => 'required|min:6',
        ]);

        [$user, $company] = DB::transaction(function () use ($request) {
            $company = Company::create([
                'name'  => $request->company_name,
                'type'  => $request->company_type ?? 'entreprise',
                'phone' => $request->company_phone,
                'email' => $request->company_email,
                'subscription_plan' => 'starter',
                'subscription_status' => 'trialing',
                'trial_ends_at' => now()->addDays(14),
            ]);

            $user = User::create([
                'name'       => $request->dg_name,
                'email'      => $request->dg_email,
                'password'   => Hash::make($request->dg_password),
                'role'       => 'dg',
                'company_id' => $company->id,
            ]);

            return [$user, $company];
        });

        $token = $user->createToken('gptrack-mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Entreprise créée',
            'company' => [
                'id'   => $company->id,
                'name' => $company->name,
                'type' => $company->type,
            ],
            'user' => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'role'         => $user->role,
                'company_id'   => $user->company_id,
                'company_type' => $company->type,
            ],
            'token' => $token,
        ], 201);
    }
}
