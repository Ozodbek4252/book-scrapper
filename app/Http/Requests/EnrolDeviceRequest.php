<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnrolDeviceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'uuid'],
            'platform' => ['nullable', 'string', 'in:ios,android'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ];
    }
}
