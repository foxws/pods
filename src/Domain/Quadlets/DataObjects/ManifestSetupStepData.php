<?php

declare(strict_types=1);

namespace Domain\Quadlets\DataObjects;

use SensitiveParameter;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A command to run once in a container of the application after installing it, such as
 * migrating its database or creating the first admin. The command is split on whitespace.
 */
#[TypeScript]
class ManifestSetupStepData extends Data
{
    /**
     * @param  list<ManifestSetupInputData>  $inputs
     */
    public function __construct(
        public string $name,
        public string $label,
        public ?string $description,
        public string $container,
        public ?string $user,
        public ?string $workdir,
        public string $command,
        public array $inputs,
    ) {}

    /**
     * Validation rules for the values of the step's inputs, keyed as inputs.NAME.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->inputs as $input) {
            $rules["inputs.{$input->name}"] = [
                'required', 'string', 'max:1000', 'not_regex:/[\r\n\0]/',
                ...($input->type === 'email' ? ['email'] : []),
            ];
        }

        return $rules;
    }

    /**
     * The command's words, followed by --option=value for every input with an option. The value
     * stays in the same argument, so one starting with a dash can't pass as an option of its own.
     *
     * @param  array<string, string>  $values
     * @return list<string>
     */
    public function arguments(#[SensitiveParameter] array $values): array
    {
        $arguments = preg_split('/\s+/', trim($this->command), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($this->inputs as $input) {
            if ($input->option !== null) {
                $arguments[] = $input->option.'='.($values[$input->name] ?? '');
            }
        }

        return $arguments;
    }

    /**
     * The value of the input without an option, which the command reads on stdin.
     *
     * @param  array<string, string>  $values
     */
    public function stdin(#[SensitiveParameter] array $values): ?string
    {
        foreach ($this->inputs as $input) {
            if ($input->option === null) {
                return ($values[$input->name] ?? '')."\n";
            }
        }

        return null;
    }
}
