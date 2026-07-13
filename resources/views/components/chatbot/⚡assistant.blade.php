<?php

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\Ai\ChatbotTools;
use App\Services\Ai\GeminiService;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $conversationId = null;

    public string $input = '';

    public function mount(): void
    {
        $this->conversationId = ChatConversation::where('user_id', auth()->id())->latest()->value('id');
    }

    #[Computed]
    public function conversations()
    {
        return ChatConversation::where('user_id', auth()->id())->latest()->get();
    }

    #[Computed]
    public function messages()
    {
        if (! $this->conversationId) {
            return collect();
        }

        return ChatMessage::where('chat_conversation_id', $this->conversationId)->orderBy('created_at')->get();
    }

    public function newConversation(): void
    {
        $this->conversationId = null;
        $this->input = '';
    }

    public function selectConversation(int $id): void
    {
        $this->conversationId = $id;
    }

    public function send(GeminiService $gemini): void
    {
        $text = trim($this->input);

        if ($text === '') {
            return;
        }

        if (! $this->conversationId) {
            $conversation = ChatConversation::create([
                'user_id' => auth()->id(),
                'title' => mb_substr($text, 0, 60),
            ]);
            $this->conversationId = $conversation->id;
        }

        ChatMessage::create([
            'chat_conversation_id' => $this->conversationId,
            'role' => ChatMessage::ROLE_USER,
            'content' => $text,
        ]);
        $this->input = '';

        $history = ChatMessage::where('chat_conversation_id', $this->conversationId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (ChatMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->all();

        $system = "Tu es l'assistant technique et commercial interne de la quincaillerie. Tu réponds en français, de façon concise et pratique. "
            ."Pour toute question de stock, de prix ou de catalogue, utilise TOUJOURS les outils disponibles plutôt que d'inventer une réponse — "
            ."n'affirme jamais un stock ou un prix sans l'avoir vérifié via un outil. Tu donnes aussi des conseils d'usage (ex. quel produit pour quel besoin) "
            .'en t\'appuyant sur le catalogue réel trouvé via l\'outil de recherche.';

        $reply = $gemini->chat($system, $history, ChatbotTools::definitions());

        ChatMessage::create([
            'chat_conversation_id' => $this->conversationId,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => $reply,
        ]);

        unset($this->conversations);
    }
};
?>

<div class="chat-grid">
    <div class="chat-list">
        <button type="button" class="btn btn-sm btn-primary" style="width:100%; margin-bottom:8px" wire:click="newConversation"><i class="bi bi-plus-lg"></i> Nouvelle conversation</button>
        @foreach ($this->conversations as $conversation)
            <div
                class="chat-list-item {{ $conversation->id === $conversationId ? 'active' : '' }}"
                wire:click="selectConversation({{ $conversation->id }})"
            >
                <i class="bi bi-chat-square-text me-1"></i> {{ $conversation->title ?? 'Conversation #'.$conversation->id }}
            </div>
        @endforeach
    </div>

    <div class="chat-thread">
        <div class="chat-messages" wire:loading.class="muted" wire:target="send">
            @forelse ($this->messages as $message)
                <div class="chat-bubble chat-bubble-{{ $message->role }}">
                    <i class="bi {{ $message->role === 'assistant' ? 'bi-robot' : 'bi-person-circle' }} me-1"></i>{{ $message->content }}
                </div>
            @empty
                <p class="chat-empty"><i class="bi bi-robot" style="font-size:28px; display:block; margin-bottom:8px;"></i>Pose une question sur le stock, les prix, ou demande un conseil d'usage — l'assistant s'appuie sur le catalogue réel.</p>
            @endforelse
            <div wire:loading wire:target="send" class="chat-bubble chat-bubble-assistant muted"><i class="bi bi-three-dots"></i> L'assistant réfléchit…</div>
        </div>

        <form wire:submit.prevent="send" class="chat-input-row">
            <input
                type="text"
                wire:model="input"
                placeholder="Écris ta question…"
                autocomplete="off"
                wire:loading.attr="disabled"
                wire:target="send"
            >
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="send"><i class="bi bi-send-fill"></i> Envoyer</button>
        </form>
    </div>
</div>
