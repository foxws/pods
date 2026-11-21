<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A drop-in of one of an application's Quadlets, named as lpod's APP files names it. lpod's own
 * drop-ins (lpod-*.conf) belong to the commands that write them, so they can't be named here.
 */
class DropInRequest extends FormRequest
{
    public const string NamePattern = '/^[A-Za-z0-9][A-Za-z0-9_.@-]{0,127}\.(container|pod|volume|network|kube|image|build)\.d\/(?!lpod-)[A-Za-z0-9_][A-Za-z0-9_.-]{0,127}\.conf$/';

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
            'restart' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => __('Use letters, digits, dots, dashes and underscores, without lpod- at the start.'),
        ];
    }
}
