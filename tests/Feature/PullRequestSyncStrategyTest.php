<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;
use Viovenia\GitSync\Contracts\SyncManagerContract;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;
use Viovenia\GitSync\Enums\FailureType;
use Viovenia\GitSync\Enums\SyncOutcome;
use Viovenia\GitSync\Events\PullRequestOpened;
use Viovenia\GitSync\Tests\TestCase;

#[WithConfig('statamic-git-sync.strategy', 'pull-request')]
#[WithConfig('statamic-git-sync.push.enabled', true)]
#[WithConfig('statamic-git-sync.pull_request.token', 'token-123')]
#[WithConfig('statamic-git-sync.pull_request.base', 'main')]
#[WithConfig('statamic-git-sync.pull_request.repository', 'acme/site')]
final class PullRequestSyncStrategyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The strategy checks that a repository exists before it does anything.
        // Every git call is faked, so this only has to be a real repository —
        // and the package's own checkout is one.
        $this->configure(['repository.path' => dirname(__DIR__, 2)]);
    }

    #[Test]
    public function it_commits_pushes_and_opens_a_pull_request(): void
    {
        Event::fake([PullRequestOpened::class]);
        $this->fakeGit();
        Http::fake([
            '*/pulls?*' => Http::response([], 200),
            '*/pulls' => Http::response($this->githubPayload(), 201),
        ]);

        $result = $this->sync();

        $this->assertSame(SyncOutcome::Committed, $result->outcome, $result->failure?->message ?? '');
        $this->assertSame(42, $result->pullRequest?->number);
        $this->assertTrue($result->pullRequest?->openedNow);
        Event::assertDispatched(PullRequestOpened::class);
    }

    #[Test]
    public function the_branch_is_pushed_before_the_forge_is_asked_anything(): void
    {
        $this->fakeGit();
        Http::fake([
            '*/pulls?*' => Http::response([], 200),
            '*/pulls' => Http::response($this->githubPayload(), 201),
        ]);

        $this->sync();

        Process::assertRan(fn ($process): bool => $process->command === [
            'git', 'push', 'origin', 'content/staging',
        ]);
    }

    #[Test]
    public function a_second_run_appends_to_the_open_pull_request_instead_of_opening_another(): void
    {
        Event::fake([PullRequestOpened::class]);
        $this->fakeGit();
        Http::fake([
            '*/pulls?*' => Http::response([$this->githubPayload()], 200),
        ]);

        $result = $this->sync();

        $this->assertSame(42, $result->pullRequest?->number);
        $this->assertFalse($result->pullRequest?->openedNow);

        Http::assertNotSent(fn ($request): bool => $request->method() === 'POST');
        Event::assertNotDispatched(
            PullRequestOpened::class,
            'Announcing the same pull request every debounce window would be noise.',
        );
    }

    #[Test]
    #[WithConfig('statamic-git-sync.pull_request.base', 'content/staging')]
    public function it_refuses_to_propose_a_branch_to_itself(): void
    {
        $this->fakeGit();
        Http::fake();

        $result = $this->sync();

        $this->assertSame(SyncOutcome::Failed, $result->outcome);
        $this->assertSame(FailureType::Configuration, $result->failure?->type);
        $this->assertStringContainsString('pull_request.base', $result->failure?->message ?? '');
        Http::assertNothingSent();
    }

    #[Test]
    public function a_forge_that_rejects_the_request_leaves_the_commit_in_place_and_asks_to_retry(): void
    {
        $this->fakeGit();
        Http::fake([
            '*/pulls?*' => Http::response([], 200),
            '*/pulls' => Http::response(['message' => 'Validation Failed'], 422),
        ]);

        $result = $this->sync();

        $this->assertSame(SyncOutcome::Failed, $result->outcome);
        $this->assertSame(FailureType::PullRequestFailed, $result->failure?->type);
        $this->assertTrue($result->retryable());

        // The work is safely committed and pushed; only the proposal failed.
        Process::assertRan(fn ($process): bool => ($process->command[1] ?? '') === 'push');
    }

    #[Test]
    public function a_dry_run_says_what_it_would_propose_and_touches_nothing(): void
    {
        $this->fakeGit();
        Http::fake();

        $result = $this->sync(dryRun: true);

        $this->assertSame(SyncOutcome::Skipped, $result->outcome);
        $this->assertStringContainsString('content/staging', $result->reason ?? '');
        $this->assertStringContainsString('main', $result->reason ?? '');
        Http::assertNothingSent();
        Process::assertDidntRun(fn ($process): bool => ($process->command[1] ?? '') === 'commit');
    }

    /** Every git call the strategy makes, with a single pending content file. */
    private function fakeGit(): void
    {
        Process::fake([
            '*status*' => Process::result("M  content/home.md\0"),
            '*rev-parse*' => Process::result('content/staging'),
            '*log*' => Process::result("2f1a3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b\ncontent: home.md"),
            '*' => Process::result(''),
        ]);
    }

    /** @return array<string, mixed> */
    private function githubPayload(): array
    {
        return [
            'number' => 42,
            'html_url' => 'https://github.com/acme/site/pull/42',
            'title' => 'Content changes from content/staging',
            'draft' => false,
            'head' => ['ref' => 'content/staging'],
            'base' => ['ref' => 'main'],
        ];
    }

    private function sync(bool $dryRun = false): SyncResultDTO
    {
        return $this->app->make(SyncManagerContract::class)
            ->sync(SyncContextDTO::fromConsole(time(), $dryRun));
    }
}
