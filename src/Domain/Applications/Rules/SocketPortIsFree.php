<?php

declare(strict_types=1);

namespace Domain\Applications\Rules;

use Closure;
use Domain\Applications\Actions\SuggestSocketPort;
use Domain\Environments\Models\Environment;
use Illuminate\Contracts\Validation\ValidationRule;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;

/**
 * An on-demand socket can't take a port another application on the host publishes, and lpod can't give
 * the application the port + 10000 on 127.0.0.1 when another one has that. When the host's applications
 * can't be read, lpod finds out itself.
 */
class SocketPortIsFree implements ValidationRule
{
    public function __construct(
        protected Environment $environment,
        protected string $application,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $port = (int) $value;

        try {
            $applications = app(LpodClient::class)->applications($this->environment);
        } catch (LpodException) {
            return;
        }

        $conflict = app(SuggestSocketPort::class)->conflict($this->application, $port, $applications);

        if ($conflict === null) {
            return;
        }

        $fail($conflict['port'] === $port
            ? __(':application publishes port :port already.', $conflict)
            : __(':application publishes port :port already, which the app gets on 127.0.0.1.', $conflict));
    }
}
