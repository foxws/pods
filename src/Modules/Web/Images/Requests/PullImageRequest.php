<?php

declare(strict_types=1);

namespace Modules\Web\Images\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PullImageRequest extends FormRequest
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
            'reference' => ['required', 'string', 'max:512', 'regex:#^[A-Za-z0-9][A-Za-z0-9._/:@-]*$#'],
        ];
    }
}
