<?php

declare(strict_types=1);

namespace Modules\Web\Secrets\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSecretRequest extends FormRequest
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
            'driver' => ['required', 'string', 'in:file,pass,shell'],
            'value' => ['required', 'string', 'max:524288'],
            'restart' => ['array', 'max:50'],
            'restart.*' => ['string', 'distinct', 'regex:'.StoreSecretRequest::NamePattern],
        ];
    }
}
