<?php

declare(strict_types=1);

namespace Modules\Web\Applications\Requests;

use Domain\Applications\Actions\SuggestSocketPort;
use Domain\Applications\DataObjects\OnDemandIdleCheckData;
use Domain\Applications\DataObjects\OnDemandScheduleData;
use Domain\Applications\DataObjects\OnDemandSettingsData;
use Domain\Applications\Rules\SocketPortIsFree;
use Domain\Environments\Models\Environment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

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
        /** @var Environment $environment */
        $environment = $this->route('environment');

        return [
            // lpod gives the app the socket's port + 10000 on 127.0.0.1.
            'port' => ['bail', 'required', 'integer', 'between:1,'.SuggestSocketPort::HighestPort, new SocketPortIsFree($environment, (string) $this->route('application'))],
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
