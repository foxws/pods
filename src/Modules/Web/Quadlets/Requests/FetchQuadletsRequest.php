<?php

declare(strict_types=1);

namespace Modules\Web\Quadlets\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FetchQuadletsRequest extends FormRequest
{
    /**
     * A password or token in a URL, which would be stored, shown and logged. An SSH user alone is neither.
     */
    protected const string CredentialsPattern = '#^(https://[^/]*@|ssh://[^/]*:[^/]*@)#';

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
            'repository' => self::repositoryRules(),
            'reference' => self::referenceRules(),
            'path' => ['nullable', 'string', 'max:255', 'regex:#^[A-Za-z0-9._-][A-Za-z0-9._/-]*$#', 'not_regex:#\.\.#'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::repositoryMessages('repository');
    }

    /**
     * A URL git may clone on a host, as lpod accepts it: never a local path or another transport, and no credentials.
     *
     * @return list<string>
     */
    public static function repositoryRules(string $presence = 'required'): array
    {
        return [$presence, 'string', 'max:2048', 'regex:#^(https://|ssh://|git@)[A-Za-z0-9][A-Za-z0-9._~:/@%+-]*$#', 'not_regex:'.self::CredentialsPattern];
    }

    /**
     * @return array<string, string>
     */
    public static function repositoryMessages(string $key): array
    {
        return [
            "{$key}.regex" => __('Use an https://, ssh:// or git@ repository URL.'),
            "{$key}.not_regex" => __('Leave credentials out of the URL. For a private repository, use SSH and a key on each host.'),
        ];
    }

    /**
     * A branch or tag, as lpod accepts it.
     *
     * @return list<string>
     */
    public static function referenceRules(): array
    {
        return ['required', 'string', 'max:255', 'regex:#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', 'not_regex:#\.\.#'];
    }
}
