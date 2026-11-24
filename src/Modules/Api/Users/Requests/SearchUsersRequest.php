<?php

declare(strict_types=1);

namespace Modules\Api\Users\Requests;

use Domain\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Validates a user search before ScoutBuilder reads it, so an unknown sort, a filter or an include
 * is a 422 rather than the builder's exception.
 */
class SearchUsersRequest extends FormRequest
{
    /**
     * The sorts UserController allows, each also descending with a leading "-".
     *
     * @var list<string>
     */
    public const array Sorts = ['newest', 'oldest', 'name'];

    public function authorize(): bool
    {
        return Gate::allows('viewAny', User::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $sort = '-?(?:'.implode('|', self::Sorts).')';

        return [
            'query' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', "regex:/^{$sort}(?:,{$sort})*$/"],
            'filter' => ['prohibited'],
            'include' => ['prohibited'],
            'page' => ['array:number,size'],
            'page.number' => ['integer', 'min:1'],
            'page.size' => ['integer', 'between:1,30'],
        ];
    }
}
