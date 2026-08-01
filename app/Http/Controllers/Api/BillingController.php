<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Site;
use App\Services\BillingService;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    /**
     * Récupère l'entreprise de l'utilisateur authentifié, qu'il s'agisse
     * d'un User (DG/RH) ou d'un Employee.
     */
    private function currentCompany(Request $request): ?Company
    {
        $companyId = $request->user()->company_id ?? null;
        return $companyId ? Company::find($companyId) : null;
    }

    public function show(Request $request)
    {
        $company = $this->currentCompany($request);
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Entreprise introuvable'], 404);
        }

        return response()->json([
            'success' => true,
            'plan' => $company->subscription_plan,
            'status' => $company->subscription_status,
            'trial_ends_at' => $company->trial_ends_at,
            'is_trial_expired' => $company->isTrialExpired(),
            'employee_count' => Employee::count(),
            'employee_limit' => $company->employeeLimit(),
            'site_count' => Site::count(),
            'site_limit' => $company->siteLimit(),
            'plans' => config('plans'),
        ]);
    }

    public function subscribe(Request $request)
    {
        $request->validate([
            'plan' => 'required|string|in:starter,business,enterprise',
        ]);

        $company = $this->currentCompany($request);
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Entreprise introuvable'], 404);
        }

        $planKey = $request->plan;
        $planConfig = config("plans.{$planKey}");

        $billing = new BillingService();

        // Pas de clé configurée : mode démo, on bascule le plan directement
        // sans paiement réel (pour ne pas bloquer le développement local).
        if (!$billing->isConfigured() || empty($planConfig['price_xof'])) {
            $company->update([
                'subscription_plan' => $planKey,
                'subscription_status' => 'active',
            ]);

            return response()->json([
                'success' => true,
                'message' => $billing->isConfigured()
                    ? 'Plan mis à jour (contactez-nous pour le plan Enterprise).'
                    : 'Plan mis à jour (mode démo — paiement non configuré).',
                'checkout_url' => null,
            ]);
        }

        try {
            $checkoutUrl = $billing->createCheckout(
                $company,
                $planKey,
                $planConfig['price_xof'],
                config('app.url') . '/api/billing/callback',
            );
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Impossible de créer le paiement pour le moment.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'checkout_url' => $checkoutUrl,
        ]);
    }

    /**
     * Webhook FedaPay : active le plan une fois le paiement confirmé.
     * Route publique — l'authenticité est garantie par la signature, pas
     * par un token Sanctum.
     */
    public function webhook(Request $request)
    {
        $billing = new BillingService();

        $valid = $billing->verifyWebhookSignature(
            $request->getContent(),
            $request->header('X-FEDAPAY-SIGNATURE'),
        );

        if (!$valid) {
            return response()->json(['success' => false, 'message' => 'Signature invalide'], 400);
        }

        $event = $request->all();

        if (($event['name'] ?? null) === 'transaction.approved') {
            $metadata = $event['entity']['custom_metadata'] ?? [];
            $companyId = $metadata['company_id'] ?? null;
            $plan = $metadata['plan'] ?? null;

            if ($companyId && $plan) {
                Company::whereKey($companyId)->update([
                    'subscription_plan' => $plan,
                    'subscription_status' => 'active',
                ]);
            }
        }

        return response()->json(['success' => true]);
    }
}
