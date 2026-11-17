<?php

namespace Tests\Feature;

use App\Domain\Realtime\Realtime;
use App\Models\Encyclopedia;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    public function test_encyclopedia_channel_is_private_to_members(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb',
                'key' => 'test-key',
                'secret' => 'test-secret',
                'app_id' => '123456',
                'options' => ['host' => '127.0.0.1', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
            ],
        ]);

        $manager = app(BroadcastManager::class);
        $manager->forgetDrivers();
        $manager->driver('reverb')->channel('encyclopedia.{encyclopediaId}', function ($user, int $id) {
            $encyclopedia = Encyclopedia::find($id);

            return $encyclopedia && ($user->is_platform_admin || $encyclopedia->isMember($user));
        });

        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $encyclopedia = $this->makeEncyclopedia($owner, ['visibility' => 'public', 'status' => 'published']);

        $this->actingAs($owner)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-encyclopedia.'.$encyclopedia->id,
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->actingAs($outsider)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-encyclopedia.'.$encyclopedia->id,
            ])
            ->assertForbidden();
    }

    public function test_realtime_service_does_not_break_writes_when_broadcasting_is_disabled(): void
    {
        config(['broadcasting.default' => 'null']);
        $encyclopedia = $this->makeEncyclopedia(User::factory()->create());

        app(Realtime::class)->encyclopedia($encyclopedia, 'probe', ['ok' => true]);

        $this->assertTrue(true);
    }
}
