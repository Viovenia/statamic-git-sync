<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Strategies;

use Illuminate\Contracts\Events\Dispatcher;
use Viovenia\GitSync\Contracts\CommitMessageBuilderContract;
use Viovenia\GitSync\Contracts\GitRepositoryContract;
use Viovenia\GitSync\Contracts\GitRepositoryLocatorContract;
use Viovenia\GitSync\Contracts\PathFilterContract;
use Viovenia\GitSync\Contracts\PullRequestClientContract;
use Viovenia\GitSync\Contracts\SyncStrategyContract;
use Viovenia\GitSync\DataTransferObjects\Config\GitSyncConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Content\ChangeSetDTO;
use Viovenia\GitSync\DataTransferObjects\Git\BranchNameDTO;
use Viovenia\GitSync\DataTransferObjects\Git\RepositorySlugDTO;
use Viovenia\GitSync\DataTransferObjects\PullRequest\PullRequestDTO;
use Viovenia\GitSync\DataTransferObjects\PullRequest\PullRequestRequestDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\DurationDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncContextDTO;
use Viovenia\GitSync\DataTransferObjects\Sync\SyncResultDTO;
use Viovenia\GitSync\Events\PullRequestOpened;
use Viovenia\GitSync\Exceptions\ConfigurationException;

/**
 * Commit, push the current branch, and open a pull request against the base
 * branch — the thing the built-in Pro automation cannot do.
 *
 * **This strategy never changes which branch is checked out.** The head of the
 * pull request is whatever branch the instance is already on. Checking out a
 * different branch would swap the content files on disk underneath the editor
 * who just saved them, so the strategy refuses instead: run the authoring
 * instance on its own branch, and let `base` be the branch it should merge
 * into.
 *
 * Within a debounce window several runs land on the same branch. The first
 * opens a pull request; the rest push commits onto it and say so, because
 * opening one pull request per save would be worse than useless.
 */
final class PullRequestSyncStrategy implements SyncStrategyContract
{
    public const IDENTIFIER = 'pull-request';

    public function __construct(
        private readonly GitRepositoryContract $repository,
        private readonly GitRepositoryLocatorContract $locator,
        private readonly PathFilterContract $filter,
        private readonly CommitMessageBuilderContract $messages,
        private readonly PullRequestClientContract $client,
        private readonly Dispatcher $events,
        private readonly GitSyncConfigDTO $config,
    ) {}

    public function identifier(): string
    {
        return self::IDENTIFIER;
    }

    public function execute(SyncContextDTO $context): SyncResultDTO
    {
        $start = microtime(true);

        $this->locator->ensureExists();

        $changes = $this->repository
            ->status($this->config->content->paths)
            ->filteredBy($this->filter);

        if ($changes->isEmpty() && ! $this->config->commit->allowEmpty) {
            return SyncResultDTO::nothingToCommit(DurationDTO::between($start, microtime(true)));
        }

        $head = $this->repository->currentBranch();
        $base = $this->config->pullRequest->base;

        $this->guardBranches($head, $base);

        if ($context->dryRun || $this->config->dryRun) {
            return SyncResultDTO::skipped(
                sprintf(
                    'Dry run: %d file(s) would have been committed to %s and proposed against %s.',
                    $changes->count(),
                    $head->value,
                    $base->value,
                ),
                DurationDTO::between($start, microtime(true)),
                $changes,
            );
        }

        $this->repository->stage($changes->paths());

        $commit = $this->repository->commit(
            $this->messages->build($changes, $context),
            $context->author,
            $this->config->commit->allowEmpty,
            $this->config->commit->sign,
        );

        // The push has to happen before the forge is asked for anything: a
        // pull request for a branch the remote has never seen is rejected.
        $push = $this->repository->push(
            $this->config->push->remote,
            $head,
            $this->config->push->forceWithLease,
        );

        $pullRequest = $this->proposeChanges($context, $changes, $head, $base);

        return SyncResultDTO::committed(
            $changes,
            $commit,
            $push,
            DurationDTO::between($start, microtime(true)),
            $pullRequest,
        );
    }

    private function proposeChanges(
        SyncContextDTO $context,
        ChangeSetDTO $changes,
        BranchNameDTO $head,
        BranchNameDTO $base,
    ): PullRequestDTO {
        $repository = $this->repositorySlug();
        $config = $this->config->pullRequest;

        if ($config->reuseOpen) {
            $existing = $this->client->findOpen($repository, $head);

            if ($existing !== null) {
                // The commits are already on the branch, so the open pull
                // request now contains them. Nothing else to do, and
                // deliberately no event — this is not news.
                return $existing;
            }
        }

        $pullRequest = $this->client->open(new PullRequestRequestDTO(
            repository: $repository,
            head: $head,
            base: $base,
            title: $this->render($config->titleTemplate, $changes, $context, $head, $base),
            body: $this->render($config->bodyTemplate, $changes, $context, $head, $base),
            draft: $config->draft,
            labels: $config->labels,
        ));

        $this->events->dispatch(new PullRequestOpened($context, $pullRequest));

        return $pullRequest;
    }

    private function render(
        string $template,
        ChangeSetDTO $changes,
        SyncContextDTO $context,
        BranchNameDTO $head,
        BranchNameDTO $base,
    ): string {
        return strtr($template, [
            '{summary}' => $this->messages->build($changes, $context->withoutAuthor()),
            '{count}' => (string) $changes->count(),
            '{user}' => $context->author !== null ? $context->author->name : 'system',
            '{head}' => $head->value,
            '{base}' => $base->value,
            '{files}' => implode("\n", array_map(
                static fn ($file): string => '- '.$file->describe(),
                $changes->files,
            )),
        ]);
    }

    private function repositorySlug(): RepositorySlugDTO
    {
        $configured = $this->config->pullRequest->repository;

        if ($configured !== null) {
            return $configured;
        }

        $url = $this->repository->remoteUrl($this->config->push->remote);
        $slug = $url !== null ? RepositorySlugDTO::fromRemoteUrl($url) : null;

        return $slug ?? throw ConfigurationException::missing(
            'pull_request.repository — it could not be derived from the URL of remote "'
            .$this->config->push->remote.'"',
        );
    }

    /**
     * Proposing a branch to itself is not a pull request, and this is the
     * mistake somebody makes on their first afternoon with this mode.
     */
    private function guardBranches(BranchNameDTO $head, BranchNameDTO $base): void
    {
        if ($head->value !== $base->value) {
            return;
        }

        throw ConfigurationException::invalidValue(
            'pull_request.base',
            $base->value,
            sprintf(
                'a branch other than the one this instance is on (%s). Pull request mode never checks out '
                .'another branch, so the instance has to live on its own content branch.',
                $head->value,
            ),
        );
    }
}
