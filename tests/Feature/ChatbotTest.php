<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ai\ClaudeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'magasinier', 'caissier'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_sending_a_message_persists_conversation_and_reply(): void
    {
        $user = User::factory()->create();
        $user->assignRole('caissier');

        $this->mock(ClaudeService::class, function ($mock) {
            $mock->shouldReceive('chat')->once()->andReturn('Réponse simulée de test.');
        });

        Livewire::actingAs($user)
            ->test('chatbot.assistant')
            ->set('input', 'Bonjour')
            ->call('send')
            ->assertSet('input', '');

        $this->assertDatabaseHas('chat_messages', ['role' => 'user', 'content' => 'Bonjour']);
        $this->assertDatabaseHas('chat_messages', ['role' => 'assistant', 'content' => 'Réponse simulée de test.']);
    }

    public function test_assistant_page_is_accessible_to_internal_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('magasinier');

        $this->actingAs($user)->get(route('chatbot.index'))->assertOk();
    }

    public function test_assistant_page_is_forbidden_without_a_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('chatbot.index'))->assertForbidden();
    }
}
