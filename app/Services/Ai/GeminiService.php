<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Client pour l'API Gemini (Google), appelée en HTTP brut via le facade Http de Laravel — aucun SDK
 * PHP officiel n'existe pour Gemini, contrairement à Claude. L'interface publique (chat/generateText/
 * extractStructured) et le format des blocs de contenu (text/image/document, façon Claude) sont
 * volontairement conservés identiques à l'ancien ClaudeService pour ne pas avoir à toucher les
 * appelants (ChatbotTools, contrôleurs, commandes) — seule cette classe connaît le format Gemini.
 *
 * Phase 7 : chaque appel est précédé d'une vérification de quota (GeminiUsageLimiter) et toute
 * erreur (clé invalide, quota Google, timeout réseau) est catégorisée — lastErrorMessage() donne
 * un message français actionnable au lieu d'un échec silencieux.
 */
class GeminiService
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    private string $apiKey;

    private string $model;

    private ?string $lastErrorReason = null;

    private ?string $lastErrorMessage = null;

    public function __construct(private readonly GeminiUsageLimiter $limiter = new GeminiUsageLimiter())
    {
        $this->apiKey = (string) config('services.gemini.key');
        $this->model = (string) config('services.gemini.model', 'gemini-2.5-flash');
    }

    /** Raison de code du dernier échec ('invalid_key' | 'quota_exceeded' | 'timeout' | 'unknown'), null si le dernier appel a réussi. */
    public function lastErrorReason(): ?string
    {
        return $this->lastErrorReason;
    }

    /** Message français actionnable pour le dernier échec, null si le dernier appel a réussi. */
    public function lastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }

    /**
     * Fait un appel minimal réel à l'API pour vérifier que la clé est valide — à exécuter
     * AVANT toute autre fonctionnalité IA (voir la commande app:validate-gemini-key).
     *
     * @return array{valid: bool, reason: ?string, message: string}
     */
    public function validateKey(): array
    {
        // maxTokens généreux : les modèles Gemini récents consomment une partie du budget en
        // "réflexion" interne avant de produire le texte visible — un budget trop court (ex. 10)
        // peut être entièrement absorbé par la réflexion et renvoyer un texte vide sans erreur HTTP.
        $text = $this->generateText('Réponds uniquement par le mot "ok", rien d\'autre.', 'Dis "ok".', 100);

        if ($this->lastErrorReason === null && trim($text) !== '') {
            return ['valid' => true, 'reason' => null, 'message' => 'Clé Gemini valide — réponse reçue avec succès.'];
        }

        return [
            'valid' => false,
            'reason' => $this->lastErrorReason ?? 'unknown',
            'message' => $this->lastErrorMessage ?? "Aucune réponse reçue de l'API, raison inconnue.",
        ];
    }

    /**
     * Conversation avec appel d'outils optionnel, jusqu'à convergence (réponse texte) ou plafond d'itérations.
     *
     * @param  array<int, array{role: string, content: mixed}>  $history
     * @param  array<int, array{name: string, description: string, inputSchema: array<string, mixed>, handler: callable}>  $tools
     */
    public function chat(string $system, array $history, array $tools = [], int $maxToolIterations = 5): string
    {
        $handlers = [];
        $functionDeclarations = [];
        foreach ($tools as $tool) {
            $handlers[$tool['name']] = $tool['handler'];
            $functionDeclarations[] = [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => $tool['inputSchema'],
            ];
        }

        $contents = collect($history)->map(fn ($msg) => [
            'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => (string) $msg['content']]],
        ])->values()->all();

        $geminiTools = $functionDeclarations !== [] ? [['functionDeclarations' => $functionDeclarations]] : null;

        for ($i = 0; $i <= $maxToolIterations; $i++) {
            $response = $this->send($contents, $system, $geminiTools);

            if ($response === null) {
                return $this->lastErrorMessage ?? 'Le service IA est momentanément indisponible, réessaie plus tard.';
            }

            $parts = $response['candidates'][0]['content']['parts'] ?? [];
            $functionCalls = array_values(array_filter($parts, fn ($p) => isset($p['functionCall'])));

            if ($functionCalls === [] || $i === $maxToolIterations) {
                return $this->extractText($parts);
            }

            $contents[] = ['role' => 'model', 'parts' => $parts];

            $responseParts = [];
            foreach ($functionCalls as $part) {
                $name = $part['functionCall']['name'] ?? '';
                $args = $part['functionCall']['args'] ?? [];
                $handler = $handlers[$name] ?? null;
                $result = $handler !== null ? (string) $handler($args) : "Outil inconnu : {$name}";

                $responseParts[] = [
                    'functionResponse' => [
                        'name' => $name,
                        'response' => ['result' => $result],
                    ],
                ];
            }

            $contents[] = ['role' => 'user', 'parts' => $responseParts];
        }

        return "Je n'ai pas pu terminer cette demande, essaie de la reformuler.";
    }

    public function generateText(string $system, string $prompt, int $maxTokens = 1024): string
    {
        $contents = [['role' => 'user', 'parts' => [['text' => $prompt]]]];
        $response = $this->send($contents, $system, null, $maxTokens);

        return $response !== null ? $this->extractText($response['candidates'][0]['content']['parts'] ?? []) : '';
    }

    /**
     * Retourne un tableau associatif décodé conforme au schéma JSON fourni (extraction structurée, vision incluse).
     *
     * @param  array<int, array<string, mixed>>  $userContent  Blocs de contenu façon Claude (text/image/document), traduits en interne au format Gemini
     * @param  array<string, mixed>  $jsonSchema
     * @return array<string, mixed>
     */
    public function extractStructured(string $system, array $userContent, array $jsonSchema, int $maxTokens = 2048): array
    {
        $contents = [['role' => 'user', 'parts' => $this->blocksToParts($userContent)]];

        $response = $this->send($contents, $system, null, $maxTokens, [
            'responseMimeType' => 'application/json',
            'responseSchema' => $this->sanitizeSchemaForGemini($jsonSchema),
        ]);

        if ($response === null) {
            return [];
        }

        $decoded = json_decode($this->extractText($response['candidates'][0]['content']['parts'] ?? []), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Le schéma JSON passé aux appelants suit la convention "façon Claude" (JSON Schema standard),
     * mais l'API REST Gemini n'accepte qu'un sous-ensemble OpenAPI 3.0 : `additionalProperties`
     * n'existe pas côté Gemini et fait échouer l'appel avec une erreur 400 ("Unknown name
     * additionalProperties... Cannot find field") si on le transmet tel quel — retiré ici
     * récursivement (objets et tableaux imbriqués), pour que les appelants puissent continuer à
     * écrire un schéma JSON Schema standard sans connaître cette limitation de Gemini.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function sanitizeSchemaForGemini(array $schema): array
    {
        unset($schema['additionalProperties']);

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $property) {
                $schema['properties'][$key] = is_array($property) ? $this->sanitizeSchemaForGemini($property) : $property;
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->sanitizeSchemaForGemini($schema['items']);
        }

        return $schema;
    }

    /**
     * Streaming SSE réel (pas d'outils — une conversation à appel d'outils entrelacé avec du
     * streaming token-par-token demanderait de détecter un functionCall au milieu du flux, ce
     * qui est nettement plus complexe et risqué ; les chatbots outillés de l'app affichent donc
     * la réponse finale de chat() de façon progressive côté Livewire plutôt que d'utiliser cette
     * méthode). Utile pour toute conversation simple ne nécessitant pas d'outils.
     *
     * @param  array<int, array{role: string, content: mixed}>  $history
     * @return \Generator<string>
     */
    public function streamChat(string $system, array $history): \Generator
    {
        $this->resetLastError();

        if ($this->apiKey === '') {
            $this->fail('invalid_key', "Aucune clé API Gemini n'est configurée (GEMINI_API_KEY dans .env).");

            return;
        }

        if (! $this->limiter->hasQuotaRemaining()) {
            $this->fail('quota_exceeded', "Le quota quotidien d'appels IA de l'application est atteint — réessaie demain.");

            return;
        }

        $contents = collect($history)->map(fn ($msg) => [
            'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => (string) $msg['content']]],
        ])->values()->all();

        $body = [
            'contents' => $contents,
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'generationConfig' => ['maxOutputTokens' => 2048],
        ];

        try {
            $response = Http::timeout(60)
                ->withOptions(['stream' => true])
                ->post(self::BASE_URL."/{$this->model}:streamGenerateContent?alt=sse&key={$this->apiKey}", $body);

            if ($response->failed()) {
                $this->handleFailedResponse($response);

                return;
            }

            $this->limiter->recordCall();

            $stream = $response->toPsrResponse()->getBody();
            $buffer = '';

            while (! $stream->eof()) {
                $buffer .= $stream->read(1024);

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);

                    if (! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $json = trim(substr($line, 5));
                    if ($json === '' || $json === '[DONE]') {
                        continue;
                    }

                    $chunk = json_decode($json, true);
                    $text = $chunk['candidates'][0]['content']['parts'][0]['text'] ?? null;

                    if ($text !== null && $text !== '') {
                        yield $text;
                    }
                }
            }
        } catch (Throwable $e) {
            $this->fail('timeout', 'Le service IA a été interrompu pendant la réponse — réessaie.');
            Log::error('Erreur streaming Gemini', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Traduit les blocs de contenu façon Claude (type text/image/document) en Parts Gemini (inlineData).
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function blocksToParts(array $blocks): array
    {
        $parts = [];
        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;

            if ($type === 'text') {
                $parts[] = ['text' => $block['text']];
            } elseif ($type === 'image' || $type === 'document') {
                $parts[] = [
                    'inlineData' => [
                        'mimeType' => $block['source']['mediaType'] ?? 'application/octet-stream',
                        'data' => $block['source']['data'] ?? '',
                    ],
                ];
            }
        }

        return $parts;
    }

    /**
     * @param  array<int, array<string, mixed>>  $contents
     * @param  array<int, array<string, mixed>>|null  $tools
     * @param  array<string, mixed>|null  $generationConfigExtra
     * @return array<string, mixed>|null
     */
    private function send(array $contents, string $system, ?array $tools, int $maxTokens = 2048, ?array $generationConfigExtra = null): ?array
    {
        $this->resetLastError();

        if ($this->apiKey === '') {
            $this->fail('invalid_key', "Aucune clé API Gemini n'est configurée (GEMINI_API_KEY dans .env).");
            Log::error("GEMINI_API_KEY manquante — impossible d'appeler l'API Gemini.");

            return null;
        }

        if (! $this->limiter->hasQuotaRemaining()) {
            $this->fail('quota_exceeded', "Le quota quotidien d'appels IA de l'application est atteint — réessaie demain.");

            return null;
        }

        $body = [
            'contents' => $contents,
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'generationConfig' => array_merge(['maxOutputTokens' => $maxTokens], $generationConfigExtra ?? []),
        ];

        if ($tools !== null) {
            $body['tools'] = $tools;
        }

        try {
            $response = Http::timeout(60)
                ->post(self::BASE_URL."/{$this->model}:generateContent?key={$this->apiKey}", $body);

            if ($response->failed()) {
                $this->handleFailedResponse($response);

                return null;
            }

            $this->limiter->recordCall();

            return $response->json();
        } catch (ConnectionException $e) {
            $this->fail('timeout', "Le service IA met trop de temps à répondre ou est injoignable — réessaie dans un instant.");
            Log::error('Erreur API Gemini (connexion)', ['error' => $e->getMessage()]);

            return null;
        } catch (Throwable $e) {
            $this->fail('unknown', 'Le service IA est momentanément indisponible.');
            Log::error('Erreur API Gemini', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function handleFailedResponse(Response $response): void
    {
        $status = $response->status();
        $bodyText = $response->body();

        Log::error('Erreur API Gemini', ['status' => $status, 'body' => $bodyText]);

        if ($status === 401 || $status === 403 || str_contains($bodyText, 'API_KEY_INVALID') || str_contains($bodyText, 'API key not valid')) {
            $this->fail('invalid_key', "La clé API Gemini est invalide ou expirée — vérifie GEMINI_API_KEY dans .env (aistudio.google.com/apikey).");
        } elseif ($status === 429) {
            $this->fail('quota_exceeded', 'Le quota Gemini (Google) est atteint pour le moment — réessaie plus tard.');
        } elseif ($status >= 500) {
            $this->fail('unknown', 'Le service Gemini est temporairement indisponible côté Google — réessaie plus tard.');
        } else {
            $this->fail('unknown', 'Le service IA est momentanément indisponible.');
        }
    }

    private function resetLastError(): void
    {
        $this->lastErrorReason = null;
        $this->lastErrorMessage = null;
    }

    private function fail(string $reason, string $message): void
    {
        $this->lastErrorReason = $reason;
        $this->lastErrorMessage = $message;
    }

    /** @param array<int, array<string, mixed>> $parts */
    private function extractText(array $parts): string
    {
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                return $part['text'];
            }
        }

        return '';
    }
}
