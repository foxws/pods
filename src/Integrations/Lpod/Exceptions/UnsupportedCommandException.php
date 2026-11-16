<?php

declare(strict_types=1);

namespace Integrations\Lpod\Exceptions;

/**
 * The host's lpod is older than the command Pods asked for.
 */
class UnsupportedCommandException extends LpodException {}
