<?php

declare(strict_types=1);

namespace Modules\Web\Users\Requests;

use Domain\Environments\Models\Environment;
use Domain\Users\Enums\PinType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StorePinRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Environment $environment */
        $environment = $this->route('environment');

        return Gate::allows('view', $environment);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $type = PinType::tryFrom($this->string('type')->toString());

        return [
            'type' => ['required', Rule::enum(PinType::class)],
            'name' => ['required', 'string', ...($type !== null ? ['regex:/^'.$type->namePattern().'$/'] : [])],
        ];
    }

    public function pinType(): PinType
    {
        return $this->enum('type', PinType::class);
    }
}
