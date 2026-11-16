<?php

declare(strict_types=1);

namespace Modules\Web\Secrets\Requests;

use Domain\Secrets\DataObjects\SecretData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSecretRequest extends FormRequest
{
    public const string NamePattern = SecretData::NamePattern;

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
            'name' => ['required', 'string', 'regex:'.self::NamePattern],
            'driver' => ['required', 'string', 'in:file,pass,shell'],
            'value' => ['required', 'string', 'max:524288'],
            'attachments' => ['array', 'max:50'],
            'attachments.*.application' => ['required', 'string', 'distinct', 'regex:'.self::NamePattern],
            'attachments.*.type' => ['required', 'string', 'in:env,mount'],
            'attachments.*.target' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.\/-]+$/'],
            'restart' => ['boolean'],
        ];
    }

    /**
     * @return list<array{application: string, type: string, target: string}>
     */
    public function attachments(): array
    {
        /** @var list<array{application: string, type: string, target: string}> */
        return array_values($this->validated('attachments', []));
    }
}
