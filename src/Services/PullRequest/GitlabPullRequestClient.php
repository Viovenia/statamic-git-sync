<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\PullRequest;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Viovenia\GitSync\Contracts\PullRequestClientContract;
use Viovenia\GitSync\DataTransferObjects\Config\PullRequestConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Git\BranchNameDTO;
use Viovenia\GitSync\DataTransferObjects\Git\RepositorySlugDTO;
use Viovenia\GitSync\DataTransferObjects\PullRequest\PullRequestDTO;
use Viovenia\GitSync\DataTransferObjects\PullRequest\PullRequestRequestDTO;
use Viovenia\GitSync\Exceptions\PullRequestFailedException;

/**
 * GitLab merge requests.
 *
 * Enough differs from GitHub that a shared implementation would be worse than
 * two, which is exactly what the contract is for:
 *
 *  - the project is addressed by its URL-encoded full path, and GitLab groups
 *    nest, so `acme/websites/site` is one project;
 *  - authentication is the PRIVATE-TOKEN header, not a bearer token;
 *  - the branches are `source_branch` and `target_branch`, and open is
 *    `opened`, not `open`;
 *  - labels are a comma-separated string on the create call, so there is no
 *    second request for them;
 *  - there is no draft flag: a merge request is a draft when its title starts
 *    with "Draft:";
 *  - the number people see is `iid`, the per-project one, not `id`.
 */
final class GitlabPullRequestClient implements PullRequestClientContract
{
    private const DRAFT_PREFIX = 'Draft: ';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly PullRequestConfigDTO $config,
    ) {}

    public function findOpen(RepositorySlugDTO $repository, BranchNameDTO $head): ?PullRequestDTO
    {
        $response = $this->send(
            'look for an open merge request',
            fn (): Response => $this->request()->get($this->url($repository), [
                'state' => 'opened',
                'source_branch' => $head->value,
                'per_page' => 1,
            ]),
        );

        $this->guard($response, 'look for an open merge request');

        /** @var list<array<string, mixed>> $payload */
        $payload = $response->json() ?? [];
        $first = $payload[0] ?? null;

        return is_array($first) ? $this->toPullRequest($first, openedNow: false) : null;
    }

    public function open(PullRequestRequestDTO $request): PullRequestDTO
    {
        $response = $this->send(
            'open a merge request',
            fn (): Response => $this->request()->post($this->url($request->repository), [
                'title' => $this->title($request),
                'description' => $request->body,
                'source_branch' => $request->head->value,
                'target_branch' => $request->base->value,
                'labels' => implode(',', $request->labels),
            ]),
        );

        $this->guard($response, 'open a merge request');

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        return $this->toPullRequest($payload, openedNow: true);
    }

    /** GitLab marks drafts by title, so the flag has to become one. */
    private function title(PullRequestRequestDTO $request): string
    {
        if (! $request->draft || str_starts_with($request->title, self::DRAFT_PREFIX)) {
            return $request->title;
        }

        return self::DRAFT_PREFIX.$request->title;
    }

    /** @param array<string, mixed> $payload */
    private function toPullRequest(array $payload, bool $openedNow): PullRequestDTO
    {
        $action = $openedNow ? 'open a merge request' : 'look for an open merge request';

        // iid, not id: the per-project number is the one shown in the UI and
        // the one anybody reading a notification would recognise.
        $number = $payload['iid'] ?? null;
        $url = $payload['web_url'] ?? null;

        if (! is_int($number) || ! is_string($url)) {
            throw PullRequestFailedException::unexpectedResponse($action);
        }

        return new PullRequestDTO(
            number: $number,
            url: $url,
            title: is_string($payload['title'] ?? null) ? $payload['title'] : '',
            head: BranchNameDTO::fromString(
                is_string($payload['source_branch'] ?? null) ? $payload['source_branch'] : 'unknown',
            ),
            base: BranchNameDTO::fromString(
                is_string($payload['target_branch'] ?? null) ? $payload['target_branch'] : 'unknown',
            ),
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

    private function request(): PendingRequest
    {
        return $this->http
            ->withHeaders(['PRIVATE-TOKEN' => $this->config->token ?? ''])
            ->acceptJson()
            ->timeout(15);
    }

    private function url(RepositorySlugDTO $repository): string
    {
        return sprintf('%s/projects/%s/merge_requests', rtrim($this->config->apiUrl, '/'), $repository->encoded());
    }
}
