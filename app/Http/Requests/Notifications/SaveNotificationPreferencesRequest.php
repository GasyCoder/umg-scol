<?php

namespace App\Http\Requests\Notifications;

use App\Notifications\StudentActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', DatabaseNotification::class);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['enabled' => ['present', 'array'], 'enabled.*' => ['required', 'string', 'distinct', Rule::in(array_keys(StudentActivity::TYPES))]];
    }
}
