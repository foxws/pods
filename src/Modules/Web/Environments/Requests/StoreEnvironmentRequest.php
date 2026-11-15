<?php

declare(strict_types=1);

namespace Modules\Web\Environments\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnvironmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'host' => ['required', 'string', 'max:253', 'regex:/^[A-Za-z0-9]([A-Za-z0-9.:-]*[A-Za-z0-9])?$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'user' => [
                'required',
                'string',
                'max:32',
                'regex:/^[a-z_][a-z0-9_-]*$/',
                Rule::unique('environments')->where('host', $this->input('host'))->where('port', $this->input('port')),
            ],
        ];
    }
}
