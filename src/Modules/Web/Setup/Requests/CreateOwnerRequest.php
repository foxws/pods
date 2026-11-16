<?php

declare(strict_types=1);

namespace Modules\Web\Setup\Requests;

use Domain\Users\Actions\OwnerSetup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Name, email address and password are checked by CreateUser.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string'],
            'email' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->has('code') && ! app(OwnerSetup::class)->matches($this->string('code')->toString())) {
                    $validator->errors()->add('code', __('That setup code is wrong. Run "php artisan pods:setup" on the host to see it.'));
                }
            },
        ];
    }
}
