<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceEnrolmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_app_install_can_enrol_and_gets_a_token(): void
    {
        $response = $this->postJson('/api/v1/devices', [
            'device_id' => (string) Str::uuid(),
            'platform' => 'android',
            'app_version' => '1.0.0',
        ]);

        $response->assertCreated()->assertJsonStructure(['token']);
        $this->assertSame(1, Device::count());
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_the_token_actually_authenticates_a_submission(): void
    {
        $token = $this->postJson('/api/v1/devices', ['device_id' => (string) Str::uuid()])->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/books/suggestions', ['title' => 'Yangi kitob'])
            ->assertStatus(202);
    }

    public function test_re_enrolling_replaces_the_old_token_rather_than_adding_one(): void
    {
        $uuid = (string) Str::uuid();

        $first = $this->postJson('/api/v1/devices', ['device_id' => $uuid])->json('token');
        $second = $this->postJson('/api/v1/devices', ['device_id' => $uuid])->json('token');

        $this->assertNotSame($first, $second);
        $this->assertSame(1, Device::count(), 'the same install must not create a second device');
        $this->assertSame(1, Device::sole()->tokens()->count());

        // A reinstall invalidates whatever the old copy of the app held.
        $this->withHeader('Authorization', 'Bearer '.$first)
            ->postJson('/api/v1/books/suggestions', ['title' => 'Kitob'])
            ->assertUnauthorized();
    }

    public function test_a_blocked_device_cannot_enrol(): void
    {
        $device = Device::factory()->blocked()->create();

        $this->postJson('/api/v1/devices', ['device_id' => $device->uuid])
            ->assertForbidden();
    }

    public function test_a_blocked_device_cannot_submit_with_a_token_it_already_had(): void
    {
        $device = Device::factory()->create();
        $token = $device->createToken('mobile-app')->plainTextToken;
        $device->update(['blocked_at' => now()]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/books/suggestions', ['title' => 'Kitob'])
            ->assertForbidden();
    }

    public function test_enrolment_needs_a_real_device_id(): void
    {
        $this->postJson('/api/v1/devices', [])->assertJsonValidationErrors('device_id');
        $this->postJson('/api/v1/devices', ['device_id' => 'not-a-uuid'])->assertJsonValidationErrors('device_id');
    }

    public function test_enrolment_is_rate_limited_harder_than_the_rest(): void
    {
        config(['scraping.enrol_rate_limit' => 2]);

        $this->postJson('/api/v1/devices', ['device_id' => (string) Str::uuid()])->assertCreated();
        $this->postJson('/api/v1/devices', ['device_id' => (string) Str::uuid()])->assertCreated();
        $this->postJson('/api/v1/devices', ['device_id' => (string) Str::uuid()])->assertStatus(429);
    }

    public function test_submitting_without_a_token_is_refused(): void
    {
        $this->postJson('/api/v1/books/suggestions', ['title' => 'Kitob'])->assertUnauthorized();
    }

    public function test_reading_still_needs_no_token(): void
    {
        $this->getJson('/api/v1/books')->assertOk();
    }
}
