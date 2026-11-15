<?php

declare(strict_types=1);

namespace Modules\Web\Quadlets\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Web\Secrets\Requests\StoreSecretRequest;

class InstallQuadletsRequest extends QuadletFilesRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'application' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9_.-]{0,127}$/'],
            'secrets' => ['array', 'max:20'],
            'secrets.*.name' => ['required', 'string', 'distinct', 'regex:'.StoreSecretRequest::NamePattern],
            'secrets.*.driver' => ['required', 'string', 'in:file,pass,shell'],
            'secrets.*.value' => ['required', 'string', 'max:524288'],
            'start' => ['boolean'],
            'replace' => ['boolean'],
        ];
    }

    /**
     * @return list<array{name: string, driver: string, value: string}>
     */
    public function secrets(): array
    {
        /** @var list<array{name: string, driver: string, value: string}> */
        return array_values($this->validated('secrets', []));
    }
}
