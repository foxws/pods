<?php

declare(strict_types=1);

namespace Modules\Web\Settings\Requests;

use Foundation\Settings\CatalogSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Web\Quadlets\Requests\FetchQuadletsRequest;

class CatalogSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-application-settings');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'repositories' => ['present', 'array', 'list', 'max:'.CatalogSettings::MaxRepositories],
            'repositories.*' => ['array'],
            'repositories.*.repository' => [...FetchQuadletsRequest::repositoryRules(), 'distinct:strict'],
            'repositories.*.reference' => FetchQuadletsRequest::referenceRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'repositories.*.repository' => __('repository'),
            'repositories.*.reference' => __('branch or tag'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...FetchQuadletsRequest::repositoryMessages('repositories.*.repository'),
            'repositories.*.repository.distinct' => __('This repository is listed more than once.'),
        ];
    }

    /**
     * @return list<array{repository: string, reference: string}>
     */
    public function repositories(): array
    {
        /** @var list<array{repository: string, reference: string}> $repositories */
        $repositories = array_values($this->validated('repositories'));

        return $repositories;
    }
}
