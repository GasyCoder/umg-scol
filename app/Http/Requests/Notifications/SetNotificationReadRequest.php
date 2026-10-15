<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;

class SetNotificationReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', DatabaseNotification::class);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['read' => ['required', 'boolean']];
    }
}
