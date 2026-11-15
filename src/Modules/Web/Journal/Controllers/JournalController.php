<?php

declare(strict_types=1);

namespace Modules\Web\Journal\Controllers;

use Domain\Environments\DataObjects\EnvironmentData;
use Domain\Environments\Models\Environment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Integrations\Lpod\LpodClient;
use Modules\Web\Environments\Concerns\ReadsFromHost;

class JournalController implements HasMiddleware
{
    use ReadsFromHost;

    /**
     * The time windows the page offers, as journalctl --since values.
     *
     * @var array<string, string>
     */
    public const array Windows = [
        '15m' => '-15min',
        '1h' => '-1h',
        'today' => 'today',
        'boot' => 'boot',
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('verified'),
        ];
    }

    public function __invoke(Request $request, Environment $environment, LpodClient $lpod): Response
    {
        Gate::authorize('view', $environment);

        $window = (string) $request->query('since', '1h');

        if (! array_key_exists($window, self::Windows)) {
            $window = '1h';
        }

        return Inertia::render('Journal/JournalIndex', [
            'environment' => EnvironmentData::fromModel($environment),
            'since' => $window,
            'entries' => Inertia::defer(fn (): array => $this->fromHost(fn (): array => $lpod->journal($environment, since: self::Windows[$window]))),
        ]);
    }
}
