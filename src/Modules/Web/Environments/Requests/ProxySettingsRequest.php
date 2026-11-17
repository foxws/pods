<?php

declare(strict_types=1);

namespace Modules\Web\Environments\Requests;

use Domain\Applications\DataObjects\ProxySettingsData;
use Domain\Applications\DataObjects\RouteData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProxySettingsRequest extends FormRequest
{
    /**
     * One or more IP addresses or hostnames, separated by spaces.
     */
    public const string ResolversPattern = '/^[A-Za-z0-9.:-]+( [A-Za-z0-9.:-]+)*$/';

    /**
     * An image reference, such as ghcr.io/foxws/pods-proxy:main.
     */
    public const string ImagePattern = '/^[A-Za-z0-9][A-Za-z0-9._\/:@-]{0,254}$/';

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
            'mode' => ['required', 'string', Rule::in(ProxySettingsData::Modes)],
            'email' => ['nullable', 'string', 'email', 'max:254'],
            'domain' => ['exclude_unless:mode,wildcard', 'required', 'string', 'regex:'.RouteData::HostPattern, 'regex:/\./'],
            'dns_provider' => ['exclude_unless:mode,wildcard', 'required', 'string', Rule::in(ProxySettingsData::DnsProviders)],
            'dns_token' => ['exclude_unless:mode,wildcard', 'nullable', 'string', 'max:1024', 'regex:/^\S+$/'],
            'resolvers' => ['exclude_unless:mode,wildcard', 'nullable', 'string', 'max:255', 'regex:'.self::ResolversPattern],
            'http_port' => ['required', 'integer', 'between:1,65535'],
            'https_port' => ['exclude_if:mode,http', 'required', 'integer', 'between:1,65535', 'different:http_port'],
            'image' => ['nullable', 'string', 'regex:'.self::ImagePattern],
        ];
    }

    /**
     * The settings for lpod, without the DNS token.
     *
     * @return array{mode: string, email: ?string, domain: ?string, dns_provider: ?string, resolvers: ?string, http_port: int, https_port: ?int, image: ?string}
     */
    public function settings(): array
    {
        $domain = $this->validated('domain');
        $httpsPort = $this->validated('https_port');

        return [
            'mode' => $this->string('mode')->toString(),
            'email' => $this->validated('email'),
            'domain' => is_string($domain) ? strtolower($domain) : null,
            'dns_provider' => $this->validated('dns_provider'),
            'resolvers' => $this->validated('resolvers'),
            'http_port' => $this->integer('http_port'),
            'https_port' => $httpsPort !== null ? (int) $httpsPort : null,
            'image' => $this->validated('image'),
        ];
    }

    /**
     * A new token for the DNS challenge, or null to keep the one on the host.
     */
    public function dnsToken(): ?string
    {
        $token = $this->validated('dns_token');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
