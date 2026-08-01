<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Assistant IA de GPTrack, appelé en HTTP direct sur l'API Messages
 * d'Anthropic (contrat vérifié : POST /v1/messages, headers x-api-key +
 * anthropic-version, corps {model, max_tokens, system, messages}) plutôt
 * qu'avec le SDK officiel — même choix que pour FedaPay dans ce projet,
 * pour éviter une nouvelle installation composer volumineuse.
 *
 * Deux usages, avec des frontières distinctes (doc "Interface employé") :
 *  - chat() = « Assistant du jour » : aide à l'exécution du travail,
 *    n'écrit jamais dans la déclaration (priorité, bilan, for intérieur).
 *  - generateEveningQuestion() = coda légère du soir : une question,
 *    jamais une consigne.
 */
class AssistantService
{
    private const MODEL = 'claude-haiku-4-5-20251001';
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.api_key'));
    }

    private function client()
    {
        return Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => self::API_VERSION,
            'content-type' => 'application/json',
        ])->timeout(30);
    }

    /**
     * Assistant du jour : répond à une question de l'employé sur son
     * travail, avec pour seul contexte la Zone 1 de sa journée (priorité,
     * secondaires, obstacles) — jamais le for intérieur.
     *
     * @param array<string, mixed> $context
     * @param array<int, array{role: string, content: string}> $history
     */
    public function chat(array $context, array $history, string $message): string
    {
        $response = $this->client()->post(self::API_URL, [
            'model' => self::MODEL,
            'max_tokens' => 1024,
            'system' => $this->executionSystemPrompt($context),
            'messages' => [...$history, ['role' => 'user', 'content' => $message]],
        ])->throw()->json();

        return $this->extractText($response);
    }

    /**
     * Coda IA du soir (régime Priorités uniquement) : une seule question
     * de réflexion, générée à partir du bilan que l'employé vient
     * d'écrire lui-même.
     *
     * @param array<string, mixed> $bilan
     */
    public function generateEveningQuestion(array $bilan): string
    {
        $system = 'Tu es un compagnon de réflexion bref pour un employé qui '
            . 'vient de terminer sa journée de travail. À partir du bilan '
            . "qu'il a écrit, pose UNE seule question courte et bienveillante "
            . "pour l'aider à réfléchir sur sa journée — jamais une consigne, "
            . "jamais un conseil, jamais un jugement sur ce qu'il aurait dû "
            . 'faire. Réponds uniquement avec la question, rien avant, rien après.';

        $userContent = "Priorité du jour : {$bilan['main_priority']}\n"
            . "État : {$bilan['evening_status']}\n"
            . "Ce qui s'est mis en travers : {$bilan['obstacle_summary']}";

        $response = $this->client()->post(self::API_URL, [
            'model' => self::MODEL,
            'max_tokens' => 200,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $userContent]],
        ])->throw()->json();

        return trim($this->extractText($response));
    }

    private function executionSystemPrompt(array $context): string
    {
        $priority = $context['main_priority'] ?? 'non renseignée';
        $secondaries = trim(($context['secondary_1'] ?? '') . ' ' . ($context['secondary_2'] ?? ''));
        $obstacles = trim(($context['obstacle_self'] ?? '') . ' ' . ($context['obstacle_other'] ?? ''));

        return "Tu es l'assistant du jour de GPTrack, un copilote d'exécution "
            . "pour un employé au travail.\n\n"
            . "Contexte du jour de l'employé :\n"
            . "- Priorité du jour : {$priority}\n"
            . "- Priorités secondaires : {$secondaries}\n"
            . "- Obstacles anticipés : {$obstacles}\n\n"
            . "RÈGLES STRICTES (ne jamais enfreindre) :\n"
            . "- Tu aides l'employé à FAIRE son travail : par où commencer, "
            . "comment découper une tâche, rédiger un brouillon, débloquer un "
            . "obstacle concret.\n"
            . "- Tu n'écris JAMAIS à sa place sa priorité, son bilan du soir, "
            . 'ni son carnet privé. Tu ne proposes jamais de formuler sa '
            . "priorité à sa place.\n"
            . "- Tu ne donnes aucun avis sur son rituel, ne le juges pas.\n"
            . '- Réponses courtes, concrètes, orientées action.';
    }

    private function extractText(array $response): string
    {
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                return $block['text'];
            }
        }

        return '';
    }
}
