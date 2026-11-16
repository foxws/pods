<?php

declare(strict_types=1);

namespace Modules\Web\Environments\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHostSettingsRequest extends FormRequest
{
    /**
     * A systemd OnCalendar expression, e.g. "daily" or "Mon..Fri 04:00". It can't start with a dash, so lpod never reads it as an option.
     */
    public const string SchedulePattern = '/^[A-Za-z0-9*][A-Za-z0-9*:,.\/~ -]{0,63}$/';

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
            'auto_update' => ['required', 'boolean'],
            'schedule' => ['required', 'string', 'regex:'.self::SchedulePattern],
            'rollback' => ['required', 'boolean'],
            'linger' => ['required', 'boolean'],
        ];
    }
}
