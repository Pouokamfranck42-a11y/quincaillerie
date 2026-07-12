<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\Ai\ClaudeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SegmentCustomersTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_updates_customer_segments_from_ai_response(): void
    {
        $customer = Customer::create(['name' => 'Client fidèle', 'type' => 'particulier']);

        $this->mock(ClaudeService::class, function ($mock) use ($customer) {
            $mock->shouldReceive('extractStructured')->once()->andReturn([
                'segments' => [
                    ['customer_id' => $customer->id, 'segment' => 'VIP', 'rationale' => 'Achète très régulièrement.'],
                ],
            ]);
        });

        $this->artisan('app:segment-customers')->assertExitCode(0);

        $customer->refresh();
        $this->assertSame('VIP', $customer->ai_segment);
        $this->assertSame('Achète très régulièrement.', $customer->ai_segment_rationale);
        $this->assertNotNull($customer->ai_segment_updated_at);
    }
}
