<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Messages\Message;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\ToolUseBlock;
use Illuminate\Support\Facades\Log;

class ClaudeService
{
    private const MODEL = 'claude-opus-4-8';

    private Client $client;

    public function __construct()
    {
        $this->client = new Client(apiKey: config('services.anthropic.key'));
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
        $apiTools = [];
        foreach ($tools as $tool) {
            $handlers[$tool['name']] = $tool['handler'];
            $apiTools[] = [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'inputSchema' => $tool['inputSchema'],
            ];
        }

        $messages = $history;

        for ($i = 0; $i <= $maxToolIterations; $i++) {
            $response = $this->send($messages, $system, $apiTools !== [] ? $apiTools : null);

            if ($response === null) {
                return 'Le service IA est momentanément indisponible, réessaie plus tard.';
            }

            if ($response->stopReason !== 'tool_use' || $i === $maxToolIterations) {
                return $this->extractText($response);
            }

            $toolResults = [];
            foreach ($response->content as $block) {
                if ($block instanceof ToolUseBlock) {
                    $handler = $handlers[$block->name] ?? null;
                    $result = $handler !== null ? (string) $handler($block->input) : "Outil inconnu : {$block->name}";
                    $toolResults[] = [
                        'type' => 'tool_result',
                        'toolUseID' => $block->id,
                        'content' => $result,
                    ];
                }
            }

            $messages[] = ['role' => 'assistant', 'content' => $this->contentToArray($response->content)];
            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        return "Je n'ai pas pu terminer cette demande, essaie de la reformuler.";
    }

    public function generateText(string $system, string $prompt, int $maxTokens = 1024): string
    {
        $response = $this->send([['role' => 'user', 'content' => $prompt]], $system, null, $maxTokens);

        return $response !== null ? $this->extractText($response) : '';
    }

    /**
     * Retourne un tableau associatif décodé conforme au schéma JSON fourni (extraction structurée, vision incluse).
     *
     * @param  array<int, array<string, mixed>>  $userContent  Blocs de contenu du message utilisateur (texte, image, document)
     * @param  array<string, mixed>  $jsonSchema
     * @return array<string, mixed>
     */
    public function extractStructured(string $system, array $userContent, array $jsonSchema, int $maxTokens = 2048): array
    {
        $response = $this->send(
            [['role' => 'user', 'content' => $userContent]],
            $system,
            null,
            $maxTokens,
            ['format' => ['type' => 'json_schema', 'schema' => $jsonSchema]],
        );

        if ($response === null) {
            return [];
        }

        $decoded = json_decode($this->extractText($response), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @param  array<int, array<string, mixed>>|null  $tools
     * @param  array<string, mixed>|null  $outputConfig
     */
    private function send(array $messages, string $system, ?array $tools, int $maxTokens = 2048, ?array $outputConfig = null): ?Message
    {
        try {
            return $this->client->messages->create(
                maxTokens: $maxTokens,
                model: self::MODEL,
                system: $system,
                messages: $messages,
                tools: $tools,
                outputConfig: $outputConfig,
            );
        } catch (APIStatusException $e) {
            Log::error('Erreur API Anthropic', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function extractText(Message $response): string
    {
        foreach ($response->content as $block) {
            if ($block instanceof TextBlock) {
                return $block->text;
            }
        }

        return '';
    }

    /**
     * @param  array<int, mixed>  $content
     * @return array<int, array<string, mixed>>
     */
    private function contentToArray(array $content): array
    {
        $result = [];
        foreach ($content as $block) {
            if ($block instanceof TextBlock) {
                $result[] = ['type' => 'text', 'text' => $block->text];
            } elseif ($block instanceof ToolUseBlock) {
                $result[] = ['type' => 'tool_use', 'id' => $block->id, 'name' => $block->name, 'input' => $block->input];
            }
        }

        return $result;
    }
}
