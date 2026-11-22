<?php

declare(strict_types=1);

namespace Modules\Web\Quadlets\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FetchQuadletsRequest extends FormRequest
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
            'repository' => self::repositoryRules(),
            'reference' => self::referenceRules(),
            'path' => ['nullable', 'string', 'max:255', 'regex:#^[A-Za-z0-9._-][A-Za-z0-9._/-]*$#', 'not_regex:#\.\.#'],
        ];
    }

    /**
     * A URL git may clone on a host, as lpod accepts it: never a local path or another transport.
     *
     * @return list<string>
     */
    public static function repositoryRules(): array
    {
        return ['required', 'string', 'max:2048', 'regex:#^(https://|ssh://|git@)[A-Za-z0-9][A-Za-z0-9._~:/@%+-]*$#'];
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
