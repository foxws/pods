<?php

declare(strict_types=1);

namespace Modules\Web\Settings\Requests;

use Domain\Applications\DataObjects\ProxySettingsData;
use Domain\Applications\DataObjects\RouteData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Web\Environments\Requests\ProxySettingsRequest;

class ProxyDefaultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-application-settings');
    }

    /**
     * Like a host's proxy settings, but every field is kept whatever the mode, and there's no DNS
     * token: that's only ever stored on a host.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(ProxySettingsData::Modes)],
            'email' => ['nullable', 'string', 'email', 'max:254'],
            'domain' => ['nullable', 'string', 'regex:'.RouteData::HostPattern, 'regex:/\./'],
            'dns_provider' => ['required', 'string', Rule::in(ProxySettingsData::DnsProviders)],
            'resolvers' => ['nullable', 'string', 'max:255', 'regex:'.ProxySettingsRequest::ResolversPattern],
            'http_port' => ['required', 'integer', 'between:1,65535'],
            'https_port' => ['required', 'integer', 'between:1,65535', 'different:http_port'],
            'image' => ['nullable', 'string', 'regex:'.ProxySettingsRequest::ImagePattern],
        ];
    }

    /**
     * @return array{mode: string, email: ?string, domain: ?string, dns_provider: string, resolvers: ?string, http_port: int, https_port: int, image: ?string}
     */
    public function defaults(): array
    {
        $domain = $this->validated('domain');

        return [
            'mode' => $this->string('mode')->toString(),
            'email' => $this->validated('email'),
            'domain' => is_string($domain) ? strtolower($domain) : null,
            'dns_provider' => $this->string('dns_provider')->toString(),
            'resolvers' => $this->validated('resolvers'),
            'http_port' => $this->integer('http_port'),
            'https_port' => $this->integer('https_port'),
            'image' => $this->validated('image'),
        ];
    }
}
