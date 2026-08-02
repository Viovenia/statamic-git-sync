<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\PullRequest;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Viovenia\GitSync\Contracts\PullRequestClientContract;
use Viovenia\GitSync\DataTransferObjects\Config\PullRequestConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Git\BranchNameDTO;
use Viovenia\GitSync\DataTransferObjects\Git\RepositorySlugDTO;
use Viovenia\GitSync\DataTransferObjects\PullRequest\PullRequestDTO;
use Viovenia\GitSync\DataTransferObjects\PullRequest\PullRequestRequestDTO;
use Viovenia\GitSync\Exceptions\PullRequestFailedException;

/**
 * The GitHub REST implementation of the pull request client.
 *
 * Uses Laravel's HTTP client rather than the `gh` binary on purpose: a
 * production web server rarely has `gh` installed and authenticated for the
 * user PHP runs as, whereas a token in the environment always works. It also
 * means the whole thing is coverable with `Http::fake()`.
 *
 * Labels are applied through the issues endpoint because GitHub does not
 * accept them when creating a pull request. A failure there is swallowed:
 * the pull request exists, and losing a label is not worth failing a sync.
 */
final class GithubPullRequestClient implements PullRequestClientContract
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly PullRequestConfigDTO $config,
    ) {}

    public function findOpen(RepositorySlugDTO $repository, BranchNameDTO $head): ?PullRequestDTO
    {
        $response = $this->send(
            'look for an open pull request',
            fn () => $this->request()->get($this->url($repository, 'pulls'), [
                'state' => 'open',
                // GitHub scopes a head branch by account; it never nests, so
                // the owner is always a single segment here.
                'head' => $repository->owner().':'.$head->value,
                'per_page' => 1,
            ]),
        );

        $this->guard($response, 'look for an open pull request');

        /** @var list<array<string, mixed>> $payload */
        $payload = $response->json() ?? [];
        $first = $payload[0] ?? null;

        return is_array($first) ? $this->toPullRequest($first, openedNow: false) : null;
    }

    public function open(PullRequestRequestDTO $request): PullRequestDTO
    {
        $response = $this->send(
            'open a pull request',
            fn () => $this->request()->post($this->url($request->repository, 'pulls'), [
                'title' => $request->title,
                'body' => $request->body,
                'head' => $request->head->value,
                'base' => $request->base->value,
                'draft' => $request->draft,
            ]),
        );

        $this->guard($response, 'open a pull request');

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $pullRequest = $this->toPullRequest($payload, openedNow: true);

        if ($request->labels !== []) {
            $this->applyLabels($request, $pullRequest);
        }

        return $pullRequest;
    }

    /** Best effort — a pull request without its labels is still a pull request. */
    private function applyLabels(PullRequestRequestDTO $request, PullRequestDTO $pullRequest): void
    {
        try {
            $this->request()->post(
                $this->url($request->repository, 'issues/'.$pullRequest->number.'/labels'),
                ['labels' => $request->labels],
            );
        } catch (ConnectionException) {
            // Ignored on purpose: see the class docblock.
        }
    }

    /** @param array<string, mixed> $payload */
    private function toPullRequest(array $payload, bool $openedNow): PullRequestDTO
    {
        $number = $payload['number'] ?? null;
        $url = $payload['html_url'] ?? null;

        if (! is_int($number) || ! is_string($url)) {
            throw PullRequestFailedException::unexpectedResponse(
                $openedNow ? 'open a pull request' : 'look for an open pull request',
            );
        }

        /** @var array<string, mixed> $head */
        $head = is_array($payload['head'] ?? null) ? $payload['head'] : [];
        /** @var array<string, mixed> $base */
        $base = is_array($payload['base'] ?? null) ? $payload['base'] : [];

        return new PullRequestDTO(
            number: $number,
            url: $url,
            title: is_string($payload['title'] ?? null) ? $payload['title'] : '',
            head: BranchNameDTO::fromString(is_string($head['ref'] ?? null) ? $head['ref'] : 'unknown'),
            base: BranchNameDTO::fromString(is_string($base['ref'] ?? null) ? $base['ref'] : 'unknown'),
            draft: (bool) ($payload['draft'] ?? false),
            openedNow: $openedNow,
        );
    }

    /** @param callable(): Response $call */
    private function send(string $action, callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException $exception) {
            throw PullRequestFailedException::unreachable($action, $exception->getMessage());
        }
    }

    private function guard(Response $response, string $action): void
    {
        if ($response->failed()) {
            throw PullRequestFailedException::requestFailed($action, $response->status(), $response->body());
        }
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->http
            ->withToken($this->config->token ?? '')
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->acceptJson()
            ->timeout(15);
    }

    private function url(RepositorySlugDTO $repository, string $path): string
    {
        return sprintf('%s/repos/%s/%s', rtrim($this->config->apiUrl, '/'), $repository->toString(), $path);
    }
}
