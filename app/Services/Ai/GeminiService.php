<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Client pour l'API Gemini (Google), appelée en HTTP brut via le facade Http de Laravel — aucun SDK
 * PHP officiel n'existe pour Gemini, contrairement à Claude. L'interface publique (chat/generateText/
 * extractStructured) et le format des blocs de contenu (text/image/document, façon Claude) sont
 * volontairement conservés identiques à l'ancien ClaudeService pour ne pas avoir à toucher les
 * appelants (ChatbotTools, contrôleurs, commandes) — seule cette classe connaît le format Gemini.
 */
class GeminiService
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    private string $apiKey;

    private string $model;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.key');
        $this->model = (string) config('services.gemini.model', 'gemini-2.5-flash');
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
                return 'Le service IA est momentanément indisponible, réessaie plus tard.';
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
            'responseSchema' => $jsonSchema,
        ]);

        if ($response === null) {
            return [];
        }

        $decoded = json_decode($this->extractText($response['candidates'][0]['content']['parts'] ?? []), true);

        return is_array($decoded) ? $decoded : [];
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
        if ($this->apiKey === '') {
            Log::error("GEMINI_API_KEY manquante — impossible d'appeler l'API Gemini.");

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
                Log::error('Erreur API Gemini', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            return $response->json();
        } catch (Throwable $e) {
            Log::error('Erreur API Gemini', ['error' => $e->getMessage()]);

            return null;
        }
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
