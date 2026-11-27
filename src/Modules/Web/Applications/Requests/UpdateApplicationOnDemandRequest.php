<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Requests;

use Domain\Applications\Actions\SuggestSocketPort;
use Domain\Applications\DataObjects\OnDemandIdleCheckData;
use Domain\Applications\DataObjects\OnDemandScheduleData;
use Domain\Applications\DataObjects\OnDemandSettingsData;
use Domain\Environments\Models\Environment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Integrations\Lpod\Exceptions\LpodException;
use Integrations\Lpod\LpodClient;

class UpdateApplicationOnDemandRequest extends FormRequest
{
    /**
     * Before validation, which reads the host's applications.
     */
    public function authorize(): bool
    {
        return Gate::allows('operate', $this->route('environment'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // lpod gives the app the socket's port + 10000 on 127.0.0.1.
            'port' => ['required', 'integer', 'between:1,'.SuggestSocketPort::HighestPort],
            'app_port' => ['required', 'integer', 'between:1,65535'],
            // Present, so leaving it out never means every address
            'listen' => ['present', 'nullable', 'string', 'ip'],
            'idle_timeout' => ['required', 'string', 'max:32', 'regex:'.OnDemandSettingsData::TimeSpanPattern],
            'workers' => ['array', 'max:20', 'required_with:idle_container'],
            'workers.*' => ['string', 'distinct', 'regex:'.OnDemandSettingsData::ContainerPattern],
            'idle_container' => ['nullable', 'string', 'required_with:idle_command', 'regex:'.OnDemandSettingsData::ContainerPattern],
            'idle_user' => ['nullable', 'string', 'max:64', 'regex:'.OnDemandSettingsData::UserPattern],
            'idle_command' => ['nullable', 'string', 'required_with:idle_container', 'max:500', 'not_regex:/[\r\n]/'],
            'schedule' => ['nullable', 'string', 'regex:'.OnDemandSettingsData::ContainerPattern],
            'schedule_at' => ['array', 'max:24', 'required_with:schedule'],
            'schedule_at.*' => ['string', 'distinct', 'regex:'.OnDemandSettingsData::ScheduleAtPattern],
            'schedule_env' => ['array', 'max:20'],
            'schedule_env.*' => ['string', 'max:500', 'regex:/^[A-Za-z_][A-Za-z0-9_]*=/', 'not_regex:/[\r\n$]/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'port.between' => __('The socket port must be 55535 or lower, since the app gets that port + 10000 on 127.0.0.1.'),
            'listen.ip' => __('Use an IP address, such as 127.0.0.1, or leave it empty for every address.'),
            'idle_timeout.regex' => __('Use a systemd time span, such as 10min or 1h.'),
            'workers.required_with' => __('The idle check stops the workers, so pick at least one.'),
            'schedule_at.*.regex' => __('Use *:MM (every hour) or HH:MM (every day), in UTC.'),
            'schedule_env.*.regex' => __('Use KEY=VALUE, one per line.'),
            'schedule_env.*.not_regex' => __('Values can\'t contain $.'),
        ];
    }

    /**
     * The socket can't take a port another application publishes, nor can the app get its local port then.
     * When the host's applications can't be read, lpod finds out itself.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $environment = $this->route('environment');

                if (! $environment instanceof Environment) {
                    return;
                }

                try {
                    $applications = app(LpodClient::class)->applications($environment);
                } catch (LpodException) {
                    return;
                }

                $port = $this->integer('port');
                $conflict = app(SuggestSocketPort::class)->conflict((string) $this->route('application'), $port, $applications);

                if ($conflict === null) {
                    return;
                }

                $validator->errors()->add('port', $conflict['port'] === $port
                    ? __(':application publishes port :port already.', $conflict)
                    : __(':application publishes port :port already, which the app gets on 127.0.0.1.', $conflict));
            },
        ];
    }

    public function settings(): OnDemandSettingsData
    {
        $idleContainer = $this->string('idle_container')->toString();
        $idleUser = $this->string('idle_user')->toString();
        $schedule = $this->string('schedule')->toString();
        $listen = $this->string('listen')->toString();

        /** @var list<string> $workers */
        $workers = array_values($this->array('workers'));

        /** @var list<string> $scheduleAt */
        $scheduleAt = array_values($this->array('schedule_at'));

        /** @var list<string> $scheduleEnvironment */
        $scheduleEnvironment = $this->array('schedule_env');

        return new OnDemandSettingsData(
            port: $this->integer('port'),
            app_port: $this->integer('app_port'),
            listen: $listen !== '' ? $listen : null,
            idle_timeout: $this->string('idle_timeout')->toString(),
            workers: $workers,
            idle_check: $idleContainer !== '' ? new OnDemandIdleCheckData(
                container: $idleContainer,
                user: $idleUser !== '' ? $idleUser : null,
                command: $this->string('idle_command')->toString(),
            ) : null,
            schedule: $schedule !== '' ? new OnDemandScheduleData(
                container: $schedule,
                at: $scheduleAt,
                environment: collect($scheduleEnvironment)
                    ->mapWithKeys(fn (string $line): array => [Str::before($line, '=') => Str::after($line, '=')])
                    ->all(),
            ) : null,
        );
    }
}
