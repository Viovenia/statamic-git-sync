<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Feature;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;
use Viovenia\GitSync\DataTransferObjects\Content\ChangedFileDTO;
use Viovenia\GitSync\DataTransferObjects\Content\ChangeSetDTO;
use Viovenia\GitSync\DataTransferObjects\Git\BranchNameDTO;
use Viovenia\GitSync\DataTransferObjects\Git\CommitReferenceDTO;
use Viovenia\GitSync\DataTransferObjects\PullRequest\PullRequestDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\DurationDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\FailureDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;
use Viovenia\GitSync\Enums\FailureType;
use Viovenia\GitSync\Enums\FileStatus;
use Viovenia\GitSync\Events\ConflictDetected;
use Viovenia\GitSync\Events\PullRequestOpened;
use Viovenia\GitSync\Events\SyncCompleted;
use Viovenia\GitSync\Events\SyncFailed;
use Viovenia\GitSync\Notifications\ConflictDetectedNotification;
use Viovenia\GitSync\Notifications\PullRequestOpenedNotification;
use Viovenia\GitSync\Notifications\SyncCompletedNotification;
use Viovenia\GitSync\Notifications\SyncFailedNotification;
use Viovenia\GitSync\Tests\TestCase;

#[WithConfig('statamic-git-sync.notifications.enabled', true)]
#[WithConfig('statamic-git-sync.notifications.mail.to', ['ops@example.com'])]
final class NotificationSubscriberTest extends TestCase
{
    #[Test]
    public function a_failed_sync_reaches_the_configured_mail_recipient(): void
    {
        Notification::fake();

        event(new SyncFailed($this->context(), $this->failedResult()));

        Notification::assertSentOnDemand(
            SyncFailedNotification::class,
            fn (SyncFailedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['mail']
                && $notifiable->routeNotificationFor('mail') === ['ops@example.com' => 'ops@example.com'],
        );
    }

    #[Test]
    public function a_conflict_sends_the_conflict_message_and_not_the_generic_failure_one(): void
    {
        Notification::fake();

        $failure = new FailureDTO(FailureType::RebaseConflict, 'Pulling with rebase conflicted.', false);
        $result = SyncResultDTO::conflicted($failure, DurationDTO::zero());

        // Both events fire for one conflict; only one message should go out.
        event(new ConflictDetected($this->context(), $failure));
        event(new SyncFailed($this->context(), $result));

        Notification::assertSentOnDemandTimes(ConflictDetectedNotification::class, 1);
        Notification::assertSentOnDemandTimes(SyncFailedNotification::class, 0);
    }

    #[Test]
    public function an_opened_pull_request_is_announced(): void
    {
        Notification::fake();

        event(new PullRequestOpened($this->context(), $this->pullRequest()));

        Notification::assertSentOnDemand(PullRequestOpenedNotification::class);
    }

    #[Test]
    #[WithConfig('statamic-git-sync.notifications.on', ['sync_failed'])]
    public function events_that_are_not_selected_are_not_even_listened_to(): void
    {
        Notification::fake();

        event(new PullRequestOpened($this->context(), $this->pullRequest()));

        Notification::assertNothingSent();
    }

    #[Test]
    #[WithConfig('statamic-git-sync.notifications.enabled', false)]
    public function nothing_is_sent_while_notifications_are_off(): void
    {
        Notification::fake();

        event(new SyncFailed($this->context(), $this->failedResult()));

        Notification::assertNothingSent();
    }

    #[Test]
    #[WithConfig('statamic-git-sync.notifications.mail.to', [])]
    #[WithConfig('statamic-git-sync.notifications.slack.channel', null)]
    public function without_a_recipient_nothing_is_attempted(): void
    {
        Notification::fake();

        event(new SyncFailed($this->context(), $this->failedResult()));

        Notification::assertNothingSent();
    }

    #[Test]
    #[WithConfig('statamic-git-sync.notifications.on', ['sync_completed'])]
    public function a_run_that_committed_nothing_is_not_worth_a_message(): void
    {
        Notification::fake();

        event(new SyncCompleted($this->context(), SyncResultDTO::nothingToCommit(DurationDTO::zero())));

        Notification::assertNothingSent();

        event(new SyncCompleted($this->context(), $this->committedResult()));

        Notification::assertSentOnDemand(SyncCompletedNotification::class);
    }

    #[Test]
    #[WithConfig('statamic-git-sync.notifications.slack.channel', '#content')]
    public function a_configured_slack_channel_is_added_as_a_second_route(): void
    {
        Notification::fake();

        event(new SyncFailed($this->context(), $this->failedResult()));

        Notification::assertSentOnDemand(
            SyncFailedNotification::class,
            fn (SyncFailedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['mail', 'slack']
                && $notifiable->routeNotificationFor('slack') === '#content',
        );
    }

    #[Test]
    public function the_mail_body_names_the_reason_and_whether_a_retry_will_help(): void
    {
        $notification = new SyncFailedNotification($this->context(), $this->failedResult());

        $rendered = $notification->toMail(new AnonymousNotifiable)->render()->toHtml();

        $this->assertStringContainsString('git binary', $rendered);
        $this->assertStringContainsString('will not fix itself', $rendered);
    }

    private function context(): SyncContextDTO
    {
        return SyncContextDTO::fromConsole(1_785_326_400);
    }

    private function failedResult(): SyncResultDTO
    {
        return SyncResultDTO::failed(
            new FailureDTO(FailureType::GitBinaryMissing, 'The git binary "git" could not be executed.', false),
            DurationDTO::zero(),
        );
    }

    private function committedResult(): SyncResultDTO
    {
        return SyncResultDTO::committed(
            ChangeSetDTO::of(new ChangedFileDTO('content/home.md', FileStatus::Modified)),
            new CommitReferenceDTO('2f1a3c4d5e6f7a8b', 'content: home.md'),
            null,
            DurationDTO::zero(),
        );
    }

    private function pullRequest(): PullRequestDTO
    {
        return new PullRequestDTO(
            number: 42,
            url: 'https://github.com/acme/site/pull/42',
            title: 'Content changes',
            head: BranchNameDTO::fromString('content/staging'),
            base: BranchNameDTO::fromString('main'),
            draft: false,
            openedNow: true,
        );
    }
}
