<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class WriteDropInRequest extends DropInRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'content' => ['required', 'string', 'max:262144'],
        ];
    }
}
