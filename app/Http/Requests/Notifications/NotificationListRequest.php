<?php

namespace App\Http\Requests\Notifications;

use App\Notifications\StudentActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class NotificationListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', DatabaseNotification::class);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', Rule::in(['unread', 'read'])],
            'kind' => ['nullable', Rule::in(array_keys(StudentActivity::TYPES))],
        ];
    }
}
