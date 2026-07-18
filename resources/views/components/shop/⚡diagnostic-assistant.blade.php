<?php

use App\Services\Ai\ChatbotTools;
use App\Services\Ai\GeminiService;
use Livewire\Component;

/**
 * Assistant diagnostic technique côté boutique (Phase 7) — le client décrit un besoin
 * ("fuite sous l'évier") et l'IA propose des produits réels du catalogue + une méthode.
 * Accessible sans compte. Conversation gardée en mémoire du composant seulement (pas de
 * persistance en base, contrairement à l'assistant staff) — un rafraîchissement de page
 * repart de zéro, comme le panier avant ajout.
 */
new class extends Component
{
    /** @var array<int, array{role: string, content: string}> */
    public array $messages = [];

    public string $input = '';

    public function send(GeminiService $gemini): void
    {
        $text = trim($this->input);

        if ($text === '') {
            return;
        }

        $this->messages[] = ['role' => 'user', 'content' => $text];
        $this->input = '';

        $system = "Tu es l'assistant technique en ligne d'une quincaillerie camerounaise, sur son site de vente en ligne. "
            ."Un visiteur décrit un problème ou un besoin (ex. « fuite sous l'évier », « repeindre un mur extérieur »). "
            .'Pose au maximum UNE question de clarification si c\'est vraiment nécessaire, puis recommande des produits '
            .'RÉELS du catalogue (utilise TOUJOURS l\'outil de recherche pour vérifier qu\'ils existent et sont disponibles '
            ."— n'invente jamais un produit ni un prix) et explique brièvement la méthode ou les étapes. "
            .'Réponds en français, simplement, sans jargon inutile.';

        $reply = $gemini->chat($system, $this->messages, ChatbotTools::publicDefinitions());

        foreach ($this->chunkForStreaming($reply) as $i => $chunk) {
            $this->stream(to: 'shop-assistant-reply', content: $chunk, replace: $i === 0);
            usleep(15000);
        }

        $this->messages[] = ['role' => 'assistant', 'content' => $reply];
    }

    public function restart(): void
    {
        $this->messages = [];
        $this->input = '';
    }

    /** Découpe mot par mot (sans casser un mot) pour un effet de frappe naturel. */
    private function chunkForStreaming(string $text): array
    {
        preg_match_all('/\S+\s*/u', $text, $matches);

        return $matches[0] ?: [$text];
    }
};
?>

<div class="chat-thread" style="height:70vh">
    @if (count($messages) > 0)
        <div style="padding:10px 16px 0; text-align:right">
            <button type="button" class="btn btn-sm btn-ghost" wire:click="restart"><i class="bi bi-arrow-counterclockwise"></i> Recommencer</button>
        </div>
    @endif
    <div class="chat-messages" wire:loading.class="muted" wire:target="send">
        @forelse ($messages as $message)
            <div class="chat-bubble chat-bubble-{{ $message['role'] }}">
                <i class="bi {{ $message['role'] === 'assistant' ? 'bi-robot' : 'bi-person-circle' }} me-1"></i>{{ $message['content'] }}
            </div>
        @empty
            <p class="chat-empty"><i class="bi bi-tools" style="font-size:28px; display:block; margin-bottom:8px;"></i>Décrivez votre besoin ou votre problème (ex. « fuite sous l'évier », « repeindre un mur extérieur ») — l'assistant vous propose des produits et une méthode.</p>
        @endforelse
        <div wire:loading wire:target="send" class="chat-bubble chat-bubble-assistant">
            <i class="bi bi-robot me-1"></i><span wire:stream="shop-assistant-reply"><i class="bi bi-three-dots"></i> L'assistant réfléchit…</span>
        </div>
    </div>

    <form wire:submit.prevent="send" class="chat-input-row">
        <input
            type="text"
            wire:model="input"
            placeholder="Décrivez votre besoin…"
            autocomplete="off"
            wire:loading.attr="disabled"
            wire:target="send"
        >
        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="send"><i class="bi bi-send-fill"></i> Envoyer</button>
    </form>
</div>
