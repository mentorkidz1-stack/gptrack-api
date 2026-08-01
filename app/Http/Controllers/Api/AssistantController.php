<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyPriority;
use App\Models\Employee;
use App\Services\AssistantService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    /**
     * Assistant du jour : disponible uniquement une fois la priorité du
     * matin posée. Contexte limité à la Zone 1 (priorité, secondaires,
     * obstacles) — jamais la parade ni le for intérieur.
     */
    public function chat(Request $request)
    {
        $employee = $request->user();
        if (!$employee instanceof Employee) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé',
            ], 403);
        }

        $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array|max:20',
            'history.*.role' => 'required_with:history|in:user,assistant',
            'history.*.content' => 'required_with:history|string',
        ]);

        $today = DailyPriority::where('employee_id', $employee->id)
            ->where('priority_date', Carbon::today()->toDateString())
            ->first();

        if (!$today || $today->skipped || !$today->main_priority) {
            return response()->json([
                'success' => false,
                'message' => "L'assistant est disponible une fois ta priorité du jour posée.",
            ], 422);
        }

        $assistant = new AssistantService();
        if (!$assistant->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => "Assistant non configuré pour le moment.",
            ], 503);
        }

        try {
            $reply = $assistant->chat(
                [
                    'main_priority'  => $today->main_priority,
                    'secondary_1'    => $today->secondary_1,
                    'secondary_2'    => $today->secondary_2,
                    'obstacle_self'  => $today->obstacle_self,
                    'obstacle_other' => $today->obstacle_other,
                ],
                $request->input('history', []),
                $request->message,
            );
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Assistant indisponible pour le moment.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'reply'   => $reply,
        ]);
    }
}
