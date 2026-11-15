<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DestroyApplicationRequest extends FormRequest
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
            'confirmation' => ['required', 'string', 'in:'.$this->route('application')],
            'volumes' => ['boolean'],
            'images' => ['boolean'],
            'secrets' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.in' => __('Type the name of the application to confirm.'),
        ];
    }
}
