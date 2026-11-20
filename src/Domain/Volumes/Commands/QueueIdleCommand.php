<?php

declare(strict_types=1);

namespace Domain\Volumes\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class QueueIdleCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pods:queue-idle';

    /**
     * @var string
     */
    protected $description = 'Succeed when the queue has no jobs waiting or running, so an on-demand Pods can stop its worker';

    public function handle(): int
    {
        return Queue::size() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
