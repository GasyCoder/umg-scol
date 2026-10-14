<?php

namespace App\Http\Requests\References;

use App\Models\Program;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProgramRequest extends ReferenceRequest
{
    protected function modelClass(): string
    {
        return Program::class;
    }

    protected function parameterName(): string
    {
        return 'program';
    }

    public function authorize(): bool
    {
        return parent::authorize() && (! $this->user()->hasRole('establishment') || (string) $this->input('establishment_id') === (string) $this->user()->establishment_id);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $record = $this->route('program');
        $id = $record instanceof Program ? $record->id : null;

        return ['establishment_id' => ['required', 'integer', Rule::exists('establishments', 'id')->whereNull('deleted_at')], 'code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique('programs')->where('establishment_id', $this->input('establishment_id'))->ignore($id)], 'mention_id' => ['nullable', 'integer', Rule::exists('mentions', 'id')->whereNull('deleted_at')->where('establishment_id', $this->input('establishment_id'))], 'name' => ['required', 'string', 'max:255'], 'is_active' => ['sometimes', 'boolean']];
    }

    /** @return array<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $program = $this->route('program');
            if ($program instanceof Program && $program->establishment_id !== $this->integer('establishment_id') && $program->studentRecords()->withTrashed()->exists()) {
                $validator->errors()->add('establishment_id', 'Un parcours utilisé par des déclarations annuelles ne peut pas changer d’établissement.');
            }
        }];
    }
}
