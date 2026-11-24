<?php

declare(strict_types=1);

namespace Modules\Web\Users\Requests;

use Domain\Environments\Models\Environment;
use Domain\Users\Enums\PinType;
use Domain\Users\Models\Pin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StorePinRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Environment $environment */
        $environment = $this->route('environment');

        return Gate::allows('view', $environment) && Gate::allows('create', Pin::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $type = PinType::tryFrom($this->string('type')->toString());

        return [
            'type' => ['required', Rule::enum(PinType::class)],
            'name' => ['required', 'string', ...($type !== null ? ['regex:'.$type->hostResource()->regex()] : [])],
        ];
    }

    /**
     * The validated type, which `rules()` requires.
     */
    public function pinType(): PinType
    {
        return PinType::from($this->validated('type'));
    }
}
