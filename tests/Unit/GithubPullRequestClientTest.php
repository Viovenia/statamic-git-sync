<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Tests\Unit;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Viovenia\GitSync\DataTransferObjects\Config\PullRequestConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Git\BranchNameDTO;
use Viovenia\GitSync\DataTransferObjects\Git\RepositorySlugDTO;
use Viovenia\GitSync\DataTransferObjects\PullRequest\PullRequestRequestDTO;
use Viovenia\GitSync\Enums\FailureType;
use Viovenia\GitSync\Enums\PullRequestDriver;
use Viovenia\GitSync\Exceptions\PullRequestFailedException;
use Viovenia\GitSync\Services\PullRequest\GithubPullRequestClient;

final class GithubPullRequestClientTest extends TestCase
{
    private HttpFactory $http;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new HttpFactory;
    }

    #[Test]
    public function it_opens_a_pull_request_and_reads_the_answer(): void
    {
        $this->http->fake([
            '*' => $this->http->response($this->githubPayload(), 201),
        ]);

        $pullRequest = $this->client()->open($this->request());

        $this->assertSame(42, $pullRequest->number);
        $this->assertSame('https://github.com/acme/site/pull/42', $pullRequest->url);
        $this->assertSame('content/staging', $pullRequest->head->value);
        $this->assertSame('main', $pullRequest->base->value);
        $this->assertTrue($pullRequest->openedNow);

        $this->http->assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/acme/site/pulls'
            && $request->method() === 'POST'
            && $request['head'] === 'content/staging'
            && $request['base'] === 'main'
            && $request['draft'] === false);
    }

    #[Test]
    public function it_authenticates_with_the_configured_token(): void
    {
        $this->http->fake(['*' => $this->http->response($this->githubPayload(), 201)]);

        $this->client()->open($this->request());

        $this->http->assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer token-123')
            && $request->hasHeader('X-GitHub-Api-Version', '2022-11-28'));
    }

    #[Test]
    public function it_finds_an_open_pull_request_for_the_branch(): void
    {
        $this->http->fake(['*' => $this->http->response([$this->githubPayload()], 200)]);

        $pullRequest = $this->client()->findOpen(
            RepositorySlugDTO::fromString('acme/site'),
            BranchNameDTO::fromString('content/staging'),
        );

        $this->assertNotNull($pullRequest);
        $this->assertSame(42, $pullRequest->number);
        $this->assertFalse($pullRequest->openedNow, 'This one already existed.');

        $this->http->assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_contains($request->url(), 'head=acme%3Acontent%2Fstaging')
            && str_contains($request->url(), 'state=open'));
    }

    #[Test]
    public function an_empty_list_means_there_is_none(): void
    {
        $this->http->fake(['*' => $this->http->response([], 200)]);

        $this->assertNull($this->client()->findOpen(
            RepositorySlugDTO::fromString('acme/site'),
            BranchNameDTO::fromString('content/staging'),
        ));
    }

    #[Test]
    public function a_rejected_request_becomes_a_retryable_failure(): void
    {
        $this->http->fake([
            '*' => $this->http->response(['message' => 'Validation Failed'], 422),
        ]);

        try {
            $this->client()->open($this->request());
            $this->fail('Expected the rejection to surface.');
        } catch (PullRequestFailedException $exception) {
            $this->assertStringContainsString('422', $exception->getMessage());
            $this->assertStringContainsString('Validation Failed', $exception->getMessage());
            $this->assertSame(FailureType::PullRequestFailed, $exception->failureType());
            $this->assertTrue(
                $exception->retryable(),
                'The commit and push already succeeded, so a retry only re-attempts the forge call.',
            );
        }
    }

    #[Test]
    public function a_response_without_a_number_is_not_silently_accepted(): void
    {
        $this->http->fake(['*' => $this->http->response(['message' => 'ok'], 201)]);

        $this->expectException(PullRequestFailedException::class);

        $this->client()->open($this->request());
    }

    #[Test]
    public function labels_are_applied_after_creation_because_github_ignores_them_on_create(): void
    {
        $this->http->fake(['*' => $this->http->response($this->githubPayload(), 201)]);

        $this->client()->open($this->request(labels: ['content']));

        $this->http->assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/acme/site/issues/42/labels'
            && $request['labels'] === ['content']);
    }

    #[Test]
    public function a_self_hosted_api_url_is_honoured(): void
    {
        $this->http->fake(['*' => $this->http->response($this->githubPayload(), 201)]);

        $this->client(apiUrl: 'https://ghe.example.com/api/v3/')->open($this->request());

        $this->http->assertSent(
            fn (Request $request): bool => $request->url() === 'https://ghe.example.com/api/v3/repos/acme/site/pulls',
        );
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

    /** @param list<string> $labels */
    private function request(array $labels = []): PullRequestRequestDTO
    {
        return new PullRequestRequestDTO(
            repository: RepositorySlugDTO::fromString('acme/site'),
            head: BranchNameDTO::fromString('content/staging'),
            base: BranchNameDTO::fromString('main'),
            title: 'Content changes from content/staging',
            body: '- content/home.md',
            draft: false,
            labels: $labels,
        );
    }

    private function client(string $apiUrl = 'https://api.github.com'): GithubPullRequestClient
    {
        return new GithubPullRequestClient($this->http, new PullRequestConfigDTO(
            driver: PullRequestDriver::GitHub,
            apiUrl: $apiUrl,
            token: 'token-123',
            repository: null,
            base: BranchNameDTO::fromString('main'),
            titleTemplate: '{head}',
            bodyTemplate: '{files}',
            draft: false,
            labels: [],
            reuseOpen: true,
        ));
    }
}
