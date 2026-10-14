<?php

namespace App\Http\Requests\References;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

abstract class ReferenceRequest extends FormRequest
{
    use HasReferenceMessages;

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    abstract protected function parameterName(): string;

    public function authorize(): bool
    {
        $model = $this->route($this->parameterName());

        return $model instanceof Model ? Gate::allows('update', $model) : Gate::allows('create', $this->modelClass());
    }

    protected function prepareForValidation(): void
    {
        $input = [];
        foreach (['code', 'name', 'type', 'address', 'phone', 'email'] as $key) {
            if ($this->has($key) && is_string($this->input($key))) {
                $value = trim($this->input($key));
                $input[$key] = $key === 'code' ? strtoupper($value) : $value;
                if ($input[$key] === '') {
                    $input[$key] = null;
                }
            }
        }
        $this->merge($input);
    }
}
