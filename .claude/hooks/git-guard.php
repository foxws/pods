<?php

declare(strict_types=1);

/**
 * A Claude Code PreToolUse hook for Bash. Before git commit it runs Pint, vp check and Larastan.
 * Before git push it runs the Unit and Feature tests, and asks when the push goes to main or to
 * the branch of a merged or closed PR.
 *
 * It reads the command without running it, so it's a guard against mistakes and not a security
 * boundary: it doesn't look inside scripts, aliases, eval or bash -c. CI stays the real gate.
 */
class GitGuard
{
    private const array CommitChecks = [
        ['vendor/bin/pint', '--test'],
        ['pnpm', 'exec', 'vp', 'check'],
        ['vendor/bin/phpstan', 'analyse', '--memory-limit=1G', '--no-progress'],
    ];

    private const array PushChecks = [
        ['php', 'artisan', 'test', '--compact', '--testsuite=Unit,Feature'],
    ];

    private const array Wrappers = ['!', '{', 'if', 'then', 'elif', 'else', 'do', 'while', 'until', 'time', 'command', 'exec', 'env', 'nohup', 'timeout'];

    private string $directory;

    /** @var array<string, string> */
    private array $variables;

    /** @var array<string, string> */
    private array $branches = [];

    /** @var array<string, true> */
    private array $passed = [];

    /** @var array<string, true> */
    private array $questions = [];

    public function __construct(private string $project, string $directory)
    {
        $this->directory = $directory;
        $this->variables = ['HOME' => (string) getenv('HOME'), 'CLAUDE_PROJECT_DIR' => $project, 'PWD' => $directory];
    }

    public function inspect(string $script): void
    {
        $words = [];
        $subshells = [];

        foreach ([...$this->tokens($script), "\n"] as $token) {
            if (! preg_match('/^([\n;&|()]|&&|\|\|)$/', $token)) {
                $words[] = $token;

                continue;
            }

            $this->inspectCommand($words);
            $words = [];

            if ($token === '(') {
                $subshells[] = [$this->directory, $this->variables];
            }

            if ($token === ')' && $subshells !== []) {
                [$this->directory, $this->variables] = array_pop($subshells);
            }
        }

        if ($this->questions === []) {
            return;
        }

        echo json_encode(['hookSpecificOutput' => [
            'hookEventName' => 'PreToolUse',
            'permissionDecision' => 'ask',
            'permissionDecisionReason' => implode(' ', array_keys($this->questions)),
        ]], JSON_THROW_ON_ERROR);
    }

    /** @return list<string> */
    private function tokens(string $script): array
    {
        $script = (string) preg_replace('/\\\\\n/', '', $this->withoutHeredocs($script));
        $word = '(?:\\\\.|\'[^\']*+\'|"(?:[^"\\\\]++|\\\\.)*+"|[^\s;&|()\'"\\\\]++)++';

        if (preg_match_all("/&&|\\|\\||[\\n;&|()]|\\#[^\\n]*+|{$word}/s", $script, $matches) === false) {
            $this->block('The git guard could not read this command. Run git in a simpler command of its own.');
        }

        return array_values(array_filter($matches[0], fn (string $token): bool => ! str_starts_with($token, '#')));
    }

    private function withoutHeredocs(string $script): string
    {
        $lines = explode("\n", $script);
        $kept = [];

        for ($index = 0; $index < count($lines); $index++) {
            $kept[] = $lines[$index];

            preg_match_all('/(?<!<)<<-?[ \t]*([\'"]?)([A-Za-z_]\w*)\1/', $lines[$index], $matches);

            foreach ($matches[2] as $delimiter) {
                for ($end = $index + 1; $end < count($lines); $end++) {
                    if (trim($lines[$end], "\t") === $delimiter) {
                        $index = $end;

                        break;
                    }
                }
            }
        }

        return implode("\n", $kept);
    }

    /** @param list<string> $words */
    private function inspectCommand(array $words): void
    {
        $assignments = [];

        while ($words !== []) {
            if (preg_match('/^([A-Za-z_]\w*)=(.*)$/s', $words[0], $match)) {
                $assignments[$match[1]] = $this->expand($match[2]);
                array_shift($words);

                continue;
            }

            if (! in_array($words[0], self::Wrappers, true)) {
                break;
            }

            $wrapper = array_shift($words);

            while ($wrapper === 'timeout' && $words !== [] && preg_match('/^(-|\d)/', $words[0])) {
                array_shift($words);
            }
        }

        if ($words === []) {
            $this->variables = [...$this->variables, ...$assignments];

            return;
        }

        $arguments = [];

        foreach ($words as $word) {
            if (preg_match('/^\d*[<>]/', $word)) {
                break;
            }

            $arguments[] = $this->expand($word);
        }

        $program = array_shift($arguments);

        if ($program === 'cd' || $program === 'pushd') {
            $targets = array_values(array_filter($arguments, fn (string $argument): bool => ! str_starts_with($argument, '-')));

            $this->directory = $this->resolve($targets[0] ?? $this->variables['HOME'], $this->directory);
            $this->variables['PWD'] = $this->directory;

            return;
        }

        if ($program !== null && basename($program) === 'git') {
            $this->inspectGit($arguments);
        }
    }

    /** @param list<string> $arguments */
    private function inspectGit(array $arguments): void
    {
        $directory = $this->directory;

        while ($arguments !== [] && str_starts_with($arguments[0], '-')) {
            $option = array_shift($arguments);

            if (! in_array($option, ['-C', '-c', '--git-dir', '--work-tree', '--namespace'], true)) {
                continue;
            }

            $value = (string) array_shift($arguments);

            if ($option === '-C') {
                $directory = $this->resolve($value, $directory);
            }
        }

        $subcommand = array_shift($arguments);

        if (! in_array($subcommand, ['commit', 'push', 'switch', 'checkout'], true)) {
            return;
        }

        $root = $this->projectCheckout($directory);

        if ($root === null) {
            return;
        }

        match ($subcommand) {
            'commit' => $this->check($root, self::CommitChecks, 'Commit'),
            'push' => $this->inspectPush($root, $arguments),
            default => $this->trackBranch($root, $arguments),
        };
    }

    /** @param list<string> $arguments */
    private function inspectPush(string $root, array $arguments): void
    {
        if (array_intersect($arguments, ['--dry-run', '-n']) !== []) {
            return;
        }

        $positional = [];
        $skipNext = false;

        foreach ($arguments as $argument) {
            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            $skipNext = in_array($argument, ['-o', '--push-option', '--receive-pack', '--exec', '--repo'], true);

            if (! str_starts_with($argument, '-')) {
                $positional[] = $argument;
            }
        }

        $refspecs = array_slice($positional, 1);
        $deletes = array_intersect($arguments, ['--delete', '-d']) !== []
            || ($refspecs !== [] && array_filter($refspecs, fn (string $refspec): bool => ! str_starts_with($refspec, ':')) === []);

        if (! $deletes) {
            $this->check($root, self::PushChecks, 'Push');
        }

        if (array_intersect($arguments, ['--all', '--mirror']) !== []) {
            $refspecs[] = 'main';
        }

        if ($refspecs === [] && ! in_array('--tags', $arguments, true)) {
            $refspecs[] = 'HEAD';
        }

        foreach ($refspecs as $refspec) {
            $this->questionDestination($root, $refspec, $deletes);
        }
    }

    private function questionDestination(string $root, string $refspec, bool $deletes): void
    {
        $parts = explode(':', ltrim($refspec, '+'));
        $branch = (string) preg_replace('#^refs/heads/#', '', end($parts));

        if ($branch === 'HEAD' || str_contains($branch, '$')) {
            $branch = $this->branches[$root] ?? $this->run($root, ['git', 'branch', '--show-current'])[1];
        }

        if ($branch === 'main') {
            $this->questions['This pushes to main. Is that intended?'] = true;

            return;
        }

        if ($deletes || $branch === '' || preg_match('#^(refs/tags/|v\d+\.\d+)#', $branch)) {
            return;
        }

        // No PR, no network and no gh all mean there's nothing to ask about.
        [$status, $state] = $this->run($root, ['timeout', '10', 'gh', 'pr', 'view', $branch, '--json', 'state', '--jq', '.state']);

        if ($status === 0 && in_array($state, ['MERGED', 'CLOSED'], true)) {
            $this->questions["The PR of {$branch} is {$state}, so these commits may never reach main."] = true;
        }
    }

    /** @param list<string> $arguments */
    private function trackBranch(string $root, array $arguments): void
    {
        foreach ($arguments as $index => $argument) {
            if (in_array($argument, ['-b', '-B', '-c', '-C'], true) && isset($arguments[$index + 1])) {
                $this->branches[$root] = $arguments[$index + 1];

                return;
            }
        }

        $targets = array_values(array_filter($arguments, fn (string $argument): bool => ! str_starts_with($argument, '-')));

        if (count($targets) !== 1 || in_array('--', $arguments, true)) {
            return;
        }

        if ($targets[0] === 'main' || $this->run($root, ['git', 'show-ref', '--verify', '--quiet', "refs/heads/{$targets[0]}"])[0] === 0) {
            $this->branches[$root] = $targets[0];
        }
    }

    /** @param list<list<string>> $checks */
    private function check(string $root, array $checks, string $action): void
    {
        foreach ($checks as $check) {
            $name = implode(' ', $check);

            if (isset($this->passed["{$root} {$name}"])) {
                continue;
            }

            [$status, $output] = $this->run($root, $check);

            if ($status !== 0) {
                $tail = substr($output, -4000);

                $this->block("{$action} blocked, `{$name}` failed in {$root}:\n{$tail}");
            }

            $this->passed["{$root} {$name}"] = true;
        }
    }

    /**
     * The root of the checkout when the directory is in this repository, worktrees included.
     */
    private function projectCheckout(string $directory): ?string
    {
        // A directory that only the shell knows is taken to be the project, so it's still checked.
        if (str_contains($directory, '$') || str_contains($directory, '`')) {
            $directory = $this->project;
        }

        if (! is_dir($directory)) {
            return null;
        }

        $commonDirectory = ['git', 'rev-parse', '--path-format=absolute', '--git-common-dir'];
        [$status, $repository] = $this->run($directory, $commonDirectory);

        if ($status !== 0 || $repository !== $this->run($this->project, $commonDirectory)[1]) {
            return null;
        }

        return $this->run($directory, ['git', 'rev-parse', '--show-toplevel'])[1];
    }

    private function resolve(string $path, string $from): string
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '$')) {
            return $path;
        }

        if ($path === '~' || str_starts_with($path, '~/')) {
            return $this->variables['HOME'].substr($path, 1);
        }

        return "{$from}/{$path}";
    }

    private function expand(string $word): string
    {
        return (string) preg_replace_callback('/\'[^\']*\'|"(?:[^"\\\\]++|\\\\.)*+"|\\\\.|[^\'"\\\\]++/s', function (array $match): string {
            if (str_starts_with($match[0], "'")) {
                return substr($match[0], 1, -1);
            }

            if (str_starts_with($match[0], '\\')) {
                return substr($match[0], 1);
            }

            $text = str_starts_with($match[0], '"') ? substr($match[0], 1, -1) : $match[0];

            return (string) preg_replace_callback(
                '/\$(?:\{(\w+)\}|(\w+))/',
                fn (array $variable): string => $this->variables[$variable[2] ?? $variable[1]] ?? $variable[0],
                $text,
            );
        }, $word);
    }

    /**
     * @param  list<string>  $command
     * @return array{int, string}
     */
    private function run(string $directory, array $command): array
    {
        $script = 'cd '.escapeshellarg($directory).' && '.implode(' ', array_map(escapeshellarg(...), $command));

        exec("({$script}) 2>&1 </dev/null", $output, $status);

        return [$status, trim(implode("\n", $output))];
    }

    private function block(string $reason): never
    {
        fwrite(STDERR, "{$reason}\n");

        exit(2);
    }
}

$payload = json_decode((string) stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$project = getenv('CLAUDE_PROJECT_DIR') ?: dirname(__DIR__, 2);

(new GitGuard($project, $payload['cwd'] ?? $project))->inspect($payload['tool_input']['command'] ?? '');
