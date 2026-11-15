<?php

declare(strict_types=1);

namespace Modules\Web\Quadlets\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Quadlet files written in Pods. Names are plain file names, so lpod never writes outside the Quadlet directory.
 */
class QuadletFilesRequest extends FormRequest
{
    public const string FileNamePattern = '/^[A-Za-z0-9][A-Za-z0-9_.@-]{0,127}\.(container|pod|volume|network|kube|image|build|env|yaml|yml)$/';

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
            'files' => ['required', 'array', 'min:1', 'max:50'],
            'files.*.name' => ['required', 'string', 'distinct', 'regex:'.self::FileNamePattern],
            'files.*.content' => ['required', 'string', 'max:262144'],
        ];
    }

    /**
     * @return list<array{name: string, content: string}>
     */
    public function files(): array
    {
        return array_values(array_map(fn (array $file): array => [
            'name' => (string) $file['name'],
            'content' => (string) $file['content'],
        ], $this->validated('files')));
    }
}
