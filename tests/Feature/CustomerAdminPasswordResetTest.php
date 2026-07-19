<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Notifications\CustomerResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Phase 1 — un admin doit pouvoir déclencher la réinitialisation du mot de passe d'un client qui n'a plus accès à son e-mail (ou activer son compte boutique). */
class CustomerAdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_admin_can_trigger_a_password_reset_link_for_a_customer_with_an_email(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $customer = Customer::create(['name' => 'Jean', 'email' => 'jean@example.com', 'password' => 'motdepasse123']);

        $response = $this->actingAs($admin)->post(route('customers.send-password-reset', $customer));

        $response->assertRedirect();
        Notification::assertSentTo($customer, CustomerResetPassword::class);
    }

    public function test_admin_cannot_trigger_a_reset_for_a_customer_without_an_email(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $customer = Customer::create(['name' => 'Client de passage']);

        $response = $this->actingAs($admin)->post(route('customers.send-password-reset', $customer));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        Notification::assertNothingSent();
    }
}
