<?php

namespace App\Domain\Realtime;

use App\Events\EncyclopediaUpdated;
use App\Events\UserNotified;
use App\Models\Encyclopedia;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fault-tolerant broadcaster. Reverb is the default transport; if it is not
 * running, writes still succeed and the failure is logged.
 */
class Realtime
{
    public function encyclopedia(?Encyclopedia $encyclopedia, string $type, array $payload = []): void
    {
        if (! $encyclopedia) {
            return;
        }

        $this->send(new EncyclopediaUpdated($encyclopedia->id, $type, $payload));
    }

    public function user(?User $user, string $type, array $payload = []): void
    {
        if (! $user) {
            return;
        }

        $this->send(new UserNotified($user->id, $type, $payload));
    }

    private function send(object $event): void
    {
        if (config('broadcasting.default') === 'null') {
            return;
        }

        try {
            broadcast($event);
        } catch (Throwable $exception) {
            Log::warning('Realtime broadcast failed', [
                'event' => $event::class,
                'message' => mb_substr($exception->getMessage(), 0, 300),
            ]);
        }
    }
}
