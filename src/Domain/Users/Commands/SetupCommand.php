<?php

declare(strict_types=1);

namespace Domain\Users\Commands;

use Domain\Users\Actions\OwnerSetup;
use Illuminate\Console\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class SetupCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pods:setup {--code : Only print the setup code, for scripts}';

    /**
     * @var string
     */
    protected $description = 'Print the one-time code that the setup page asks for, while Pods has no owner';

    public function handle(OwnerSetup $setup): int
    {
        if (! $setup->isNeeded()) {
            if (! $this->option('code')) {
                info('Pods has an owner, so there is nothing to set up.');
            }

            return self::SUCCESS;
        }

        if ($this->option('code')) {
            $this->line($setup->code());

            return self::SUCCESS;
        }

        note('Pods has no owner yet. Open '.route('setup.show')." and enter this setup code:\n\n{$setup->code()}");

        return self::SUCCESS;
    }
}
