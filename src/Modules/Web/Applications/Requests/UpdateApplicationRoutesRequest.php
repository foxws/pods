<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Requests;

use Domain\Applications\DataObjects\RouteData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationRoutesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return RouteData::rules('routes');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'routes.*.host.regex' => __('Use a hostname, such as hello.home.arpa.'),
            'routes.*.host.distinct' => __('Each hostname can only have one route.'),
            'routes.*.upstream.regex' => __('Use a container, host name or IPv4 address and its port, such as systemd-hello:8000.'),
        ];
    }

    /**
     * @return list<array{role: string, host: string, upstream: string, main: bool, via: ?string}>
     */
    public function routes(): array
    {
        /** @var array<array-key, array{role: string, host: string, upstream: string, main?: bool, via?: ?string}> $routes */
        $routes = $this->array('routes');

        return RouteData::forLpod($routes);
    }
}
