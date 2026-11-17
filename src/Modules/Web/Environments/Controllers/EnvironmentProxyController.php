<?php

declare(strict_types=1);

namespace Modules\Web\Environments\Controllers;

use Domain\Applications\Actions\InstallProxy;
use Domain\Environments\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;
use Modules\Web\Environments\Requests\ProxySettingsRequest;

class EnvironmentProxyController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    /**
     * Installs the Pods proxy, saves its settings, then starts it, so it comes up on the right ports.
     */
    public function store(ProxySettingsRequest $request, Environment $environment, InstallProxy $installProxy, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        try {
            $installProxy($environment);
        } catch (LpodException $exception) {
            toast(title: __('Proxy not installed'), description: $exception->getMessage(), type: 'error');

            return back();
        }

        $failure = null;

        try {
            $backup = $lpod->updateProxySettings($environment, $request->settings(), $request->dnsToken());
        } catch (LpodException $exception) {
            $backup = null;
            $failure = $exception->getMessage();
        }

        try {
            $lpod->up($environment, InstallProxy::Application);
        } catch (LpodException $exception) {
            toast(title: __('Proxy installed, but not started'), description: $exception->getMessage(), type: 'error');

            return back();
        }

        if ($failure !== null) {
            toast(title: __('Proxy installed, but its settings weren\'t saved'), description: $failure, type: 'error');

            return back();
        }

        toast(title: __('Proxy installed'), description: $this->saved($backup, __('systemd is starting pods-proxy.')));

        return back();
    }

    public function update(ProxySettingsRequest $request, Environment $environment, LpodClient $lpod): RedirectResponse
    {
        Gate::authorize('operate', $environment);

        try {
            $backup = $lpod->updateProxySettings($environment, $request->settings(), $request->dnsToken());
        } catch (LpodException $exception) {
            toast(title: __('Proxy settings not saved'), description: $exception->getMessage(), type: 'error');

            return back();
        }

        toast(title: __('Proxy settings saved'), description: $this->saved($backup, __('lpod rewrote the Caddyfile and the routes, and restarted the proxy.')));

        return back();
    }

    /**
     * Downloads the root certificate of the proxy's local certificate authority, for trusting it.
     */
    public function certificate(Environment $environment, LpodClient $lpod): Response|RedirectResponse
    {
        Gate::authorize('view', $environment);

        try {
            $certificate = $lpod->proxyCertificate($environment);
        } catch (LpodException $exception) {
            toast(title: __('No certificate'), description: $exception->getMessage(), type: 'error');

            return back();
        }

        if ($certificate === null) {
            toast(title: __('No certificate yet'), description: __('Caddy makes its local certificate authority once the proxy runs in the local mode.'), type: 'error');

            return back();
        }

        return response($certificate, 200, [
            'Content-Type' => 'application/x-x509-ca-cert',
            'Content-Disposition' => 'attachment; filename="pods-proxy-'.Str::slug($environment->name).'.crt"',
        ]);
    }

    protected function saved(?string $backup, string $description): string
    {
        if ($backup === null) {
            return $description;
        }

        return $description.' '.__('Your own Caddyfile is kept as :backup.', ['backup' => $backup]);
    }
}
