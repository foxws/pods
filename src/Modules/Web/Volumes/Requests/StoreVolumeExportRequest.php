<?php

declare(strict_types=1);

namespace Modules\Web\Volumes\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVolumeExportRequest extends FormRequest
{
    /**
     * A plain Podman object name, as lpod takes it.
     */
    public const string NamePattern = '/^[A-Za-z0-9][A-Za-z0-9_.@-]{0,252}$/';

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
            'volumes' => ['required_without:application', 'prohibits:application', 'array', 'min:1', 'max:50'],
            'volumes.*' => ['required', 'string', 'regex:'.self::NamePattern],
            'application' => ['required_without:volumes', 'string', 'regex:'.self::NamePattern],
            'stop_applications' => ['boolean'],
        ];
    }
}
