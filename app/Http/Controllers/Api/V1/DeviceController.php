<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnrolDeviceRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /**
     * Enrol an app install and hand back a token.
     *
     * Called once, on first launch. The app keeps the token in the keychain
     * and sends it with anything it submits, so a book can be traced back to
     * the install that added it and an abusive one can be stopped on its own.
     *
     * Re-enrolling the same device replaces its tokens, which is what a
     * reinstall should do.
     */
    public function store(EnrolDeviceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $device = Device::firstOrNew(['uuid' => $validated['device_id']]);

        if ($device->exists && $device->isBlocked()) {
            return response()->json([
                'message' => 'This device cannot submit books.',
            ], 403);
        }

        $device->fill([
            'platform' => $validated['platform'] ?? $device->platform,
            'app_version' => $validated['app_version'] ?? $device->app_version,
            'last_seen_at' => now(),
        ])->save();

        $device->tokens()->delete();

        return response()->json([
            'token' => $device->createToken('mobile-app')->plainTextToken,
        ], 201);
    }
}
