<?php

namespace App\Http\Requests\References;

use App\Models\Mention;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveMentionRequest extends ReferenceRequest
{
    protected function modelClass(): string
    {
        return Mention::class;
    }

    protected function parameterName(): string
    {
        return 'mention';
    }

    public function authorize(): bool
    {
        return parent::authorize() && (! $this->user()->hasRole('establishment') || (string) $this->input('establishment_id') === (string) $this->user()->establishment_id);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $record = $this->route('mention');
        $id = $record instanceof Mention ? $record->id : null;

        return ['establishment_id' => ['required', 'integer', Rule::exists('establishments', 'id')->whereNull('deleted_at')], 'code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique('mentions')->where('establishment_id', $this->input('establishment_id'))->ignore($id)], 'name' => ['required', 'string', 'max:255'], 'is_active' => ['sometimes', 'boolean']];
    }

    /** @return array<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $mention = $this->route('mention');
            if ($mention instanceof Mention && $mention->establishment_id !== $this->integer('establishment_id') && $mention->programs()->withTrashed()->exists()) {
                $validator->errors()->add('establishment_id', 'Une mention qui regroupe des parcours ne peut pas changer d’établissement.');
            }
        }];
    }
}
