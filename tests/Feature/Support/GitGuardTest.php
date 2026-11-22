<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Runs the Claude Code hook in a repository whose checks are stubs.
 *
 * @return array{status: int, checks: list<string>, asked: ?string}
 */
function runGitGuard(string $command, string $branch = 'feature', string $failing = '', string $pullRequest = 'OPEN', string $from = ''): array
{
    $root = sys_get_temp_dir().'/pods-git-guard-'.Str::random(16);

    foreach (['project/vendor/bin', 'project/resources', 'project/with space', 'other', 'bin'] as $directory) {
        File::makeDirectory("{$root}/{$directory}", recursive: true);
    }

    foreach (['project/vendor/bin/pint', 'project/vendor/bin/phpstan', 'bin/pnpm', 'bin/php', 'bin/gh'] as $stub) {
        $name = basename($stub);
        $body = $name === 'gh'
            ? "echo {$pullRequest}"
            : "echo {$name} >> '{$root}/checks'\n".($name === $failing ? 'exit 1' : 'exit 0');

        File::put("{$root}/{$stub}", "#!/bin/sh\n{$body}\n");
        File::chmod("{$root}/{$stub}", 0755);
    }

    (new Process(['git', 'init', '-q', '-b', $branch, "{$root}/project"]))->mustRun();
    (new Process(['git', 'init', '-q', '-b', 'main', "{$root}/other"]))->mustRun();

    $process = new Process(
        [PHP_BINARY, base_path('.claude/hooks/git-guard.php')],
        env: ['CLAUDE_PROJECT_DIR' => "{$root}/project", 'PATH' => "{$root}/bin:".getenv('PATH')],
        input: json_encode([
            'cwd' => rtrim("{$root}/project/{$from}", '/'),
            'tool_input' => ['command' => str_replace('{root}', $root, $command)],
        ], JSON_THROW_ON_ERROR),
    );

    $process->run();

    $result = [
        'status' => (int) $process->getExitCode(),
        'checks' => File::exists("{$root}/checks") ? explode("\n", trim(File::get("{$root}/checks"))) : [],
        'asked' => data_get(json_decode($process->getOutput(), true), 'hookSpecificOutput.permissionDecisionReason'),
    ];

    File::deleteDirectory($root);

    return $result;
}

it('checks a commit to the project', function (string $command) {
    expect(runGitGuard($command))->toBe(['status' => 0, 'checks' => ['pint', 'pnpm', 'phpstan'], 'asked' => null]);
})->with([
    'plain' => 'git commit -m x',
    'chained' => 'git add -A && git commit -m x',
    'on a new line' => "git add -A\ngit commit -m x",
    'after a branch check' => 'test "$(git branch --show-current)" = "feature" || exit 1; git commit -m x',
    'trailing slash' => 'cd {root}/project/ && git commit -m x',
    'quoted path' => 'cd "{root}/project/with space" && git commit -m x',
    'subdirectory' => 'cd resources && git commit -m x',
    'back from another repository' => 'cd {root}/other; ls; cd {root}/project && git commit -m x',
    'after a subshell elsewhere' => '(cd {root}/other && ls); git commit -m x',
    'before leaving' => 'git commit -m x && cd {root}/other',
    'path in a variable' => 'P={root}/project; cd /; cd "$P" && git commit -m x',
    'unknown path' => 'cd "$UNKNOWN" && git commit -m x',
    'git options' => 'git -c core.editor=true -C resources commit',
    'environment' => 'GIT_EDITOR=true git commit',
    'conditional' => 'if true; then git commit -m x; fi',
    'wrapper' => 'timeout 60 git commit -m x',
    'continued line' => "git \\\n commit -m x",
    'comment with an apostrophe' => "# don't skip this\ngit commit -m 'x'",
    'message from a heredoc' => "git commit -m \"\$(cat <<'EOF'\nSay \"hi\", don't push\nEOF\n)\"",
    'after a heredoc' => "cat > notes <<EOF\nit's\nEOF\ngit commit -m x",
]);

it('ignores a command that does not commit or push to the project', function (string $command) {
    expect(runGitGuard($command))->toBe(['status' => 0, 'checks' => [], 'asked' => null]);
})->with([
    'other git command' => 'git log --grep commit',
    'quoted text' => 'echo "git commit && git push"',
    'comment' => 'ls # then git push',
    'heredoc' => "cat > notes <<EOF\ngit commit -m x\ngit push\nEOF",
    'another repository' => 'cd {root}/other && git commit -m x && git push origin main',
    'another repository through -C' => 'git -C {root}/other commit -m x',
    'another repository in a variable' => 'P={root}/other; cd "$P" && git commit -m x',
    'dry run' => 'git push --dry-run origin main',
    'deleted branch' => 'git push origin --delete feature',
]);

it('checks from the directory that the session is in', function () {
    expect(runGitGuard('git commit -m x', from: 'resources')['checks'])->toBe(['pint', 'pnpm', 'phpstan']);
});

it('blocks when a check fails', function (string $command, string $failing) {
    expect(runGitGuard($command, failing: $failing)['status'])->toBe(2);
})->with([
    'Pint' => ['git commit -m x', 'pint'],
    'vp check' => ['git commit -m x', 'pnpm'],
    'Larastan' => ['git commit -m x', 'phpstan'],
    'tests' => ['git push', 'php'],
]);

it('runs the tests before a push to a feature branch', function (string $command, string $branch) {
    expect(runGitGuard($command, $branch))->toBe(['status' => 0, 'checks' => ['php'], 'asked' => null]);
})->with([
    'current branch' => ['git push 2>&1 | tail -2', 'feature'],
    'named branch' => ['git push -u origin feature', 'main'],
    'new branch' => ['git switch -c feature && git push -u origin HEAD', 'main'],
]);

it('asks before a push to main', function (string $command, string $branch) {
    expect(runGitGuard($command, $branch)['asked'])->toContain('main');
})->with([
    'current branch' => ['git push', 'main'],
    'refspec' => ['git push origin HEAD:main', 'feature'],
    'full ref' => ['git push origin +HEAD:refs/heads/main', 'feature'],
    'after switching' => ['git checkout main && git push', 'feature'],
    'every branch' => ['git push --all origin', 'feature'],
    'deleted' => ['git push origin :main', 'feature'],
]);

it('asks before a push to the branch of a finished pull request', function (string $state) {
    expect(runGitGuard('git push', pullRequest: $state)['asked'])->toContain($state);
})->with(['MERGED', 'CLOSED']);
