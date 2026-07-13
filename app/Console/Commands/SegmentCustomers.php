<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Ai\GeminiService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:segment-customers')]
#[Description('Segmente les clients (VIP, régulier, occasionnel, à risque de départ, nouveau) via IA, à partir de leur historique de ventes')]
class SegmentCustomers extends Command
{
    private const BATCH_SIZE = 20;

    private const SEGMENTS = ['VIP', 'régulier', 'occasionnel', 'à risque de départ', 'nouveau'];

    public function handle(GeminiService $gemini): void
    {
        $customers = Customer::orderBy('id')->get();
        $updated = 0;

        foreach ($customers->chunk(self::BATCH_SIZE) as $batch) {
            $payload = $batch->map(fn (Customer $c) => array_merge(['customer_id' => $c->id], $c->rfmSummary()))->values()->all();

            if ($payload === []) {
                continue;
            }

            $system = 'Tu segmentes des clients de quincaillerie à partir de métriques simples (nombre de ventes, montant total dépensé, '
                .'jours depuis le dernier achat, encours dû). Segments possibles : '.implode(', ', self::SEGMENTS).'. '
                .'Donne pour chaque client une justification en une phrase, en français.';

            $result = $gemini->extractStructured(
                $system,
                [['type' => 'text', 'text' => json_encode($payload, JSON_UNESCAPED_UNICODE)]],
                [
                    'type' => 'object',
                    'properties' => [
                        'segments' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'customer_id' => ['type' => 'integer'],
                                    'segment' => ['type' => 'string', 'enum' => self::SEGMENTS],
                                    'rationale' => ['type' => 'string'],
                                ],
                                'required' => ['customer_id', 'segment', 'rationale'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['segments'],
                    'additionalProperties' => false,
                ],
                4096,
            );

            foreach ($result['segments'] ?? [] as $entry) {
                $customer = $batch->firstWhere('id', $entry['customer_id'] ?? null);

                if (! $customer) {
                    continue;
                }

                $customer->update([
                    'ai_segment' => $entry['segment'] ?? null,
                    'ai_segment_rationale' => $entry['rationale'] ?? null,
                    'ai_segment_updated_at' => now(),
                ]);
                $updated++;
            }
        }

        $this->info($updated.' client(s) segmenté(s).');
    }
}
