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
use Viovenia\GitSync\Enums\PullRequestDriver;
use Viovenia\GitSync\Exceptions\PullRequestFailedException;
use Viovenia\GitSync\Services\PullRequest\GitlabPullRequestClient;

final class GitlabPullRequestClientTest extends TestCase
{
    private HttpFactory $http;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new HttpFactory;
    }

    #[Test]
    public function it_opens_a_merge_request_with_gitlabs_own_field_names(): void
    {
        $this->http->fake(['*' => $this->http->response($this->gitlabPayload(), 201)]);

        $merged = $this->client()->open($this->request());

        $this->assertSame(7, $merged->number, 'The iid is the number people see, not the global id.');
        $this->assertSame('https://gitlab.com/acme/websites/site/-/merge_requests/7', $merged->url);
        $this->assertSame('content/staging', $merged->head->value);
        $this->assertSame('main', $merged->base->value);

        $this->http->assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request['source_branch'] === 'content/staging'
            && $request['target_branch'] === 'main');
    }

    #[Test]
    public function the_project_is_addressed_by_its_url_encoded_path(): void
    {
        $this->http->fake(['*' => $this->http->response($this->gitlabPayload(), 201)]);

        $this->client()->open($this->request());

        // Nested groups are the reason the whole path matters here.
        $this->http->assertSent(
            fn (Request $request): bool => $request->url() === 'https://gitlab.com/api/v4/projects/acme%2Fwebsites%2Fsite/merge_requests',
        );
    }

    #[Test]
    public function it_authenticates_with_the_private_token_header(): void
    {
        $this->http->fake(['*' => $this->http->response($this->gitlabPayload(), 201)]);

        $this->client()->open($this->request());

        $this->http->assertSent(fn (Request $request): bool => $request->hasHeader('PRIVATE-TOKEN', 'token-123'));
    }

    #[Test]
    public function a_draft_becomes_a_title_prefix_because_gitlab_has_no_flag_for_it(): void
    {
        $this->http->fake(['*' => $this->http->response($this->gitlabPayload(), 201)]);

        $this->client()->open($this->request(draft: true));

        $this->http->assertSent(fn (Request $request): bool => $request['title'] === 'Draft: Content changes from content/staging');
    }

    #[Test]
    public function a_title_that_already_says_draft_is_not_prefixed_twice(): void
    {
        $this->http->fake(['*' => $this->http->response($this->gitlabPayload(), 201)]);

        $this->client()->open($this->request(draft: true, title: 'Draft: already said so'));

        $this->http->assertSent(fn (Request $request): bool => $request['title'] === 'Draft: already said so');
    }

    #[Test]
    public function labels_travel_as_a_comma_separated_string_on_the_create_call(): void
    {
        $this->http->fake(['*' => $this->http->response($this->gitlabPayload(), 201)]);

        $this->client()->open($this->request(labels: ['content', 'automated']));

        // No second request: unlike GitHub, GitLab accepts them here.
        $this->http->assertSent(fn (Request $request): bool => $request['labels'] === 'content,automated');
        $this->http->assertSentCount(1);
    }

    #[Test]
    public function it_finds_an_open_merge_request_by_source_branch(): void
    {
        $this->http->fake(['*' => $this->http->response([$this->gitlabPayload()], 200)]);

        $found = $this->client()->findOpen(
            RepositorySlugDTO::fromString('acme/websites/site'),
            BranchNameDTO::fromString('content/staging'),
        );

        $this->assertNotNull($found);
        $this->assertSame(7, $found->number);
        $this->assertFalse($found->openedNow);

        // "opened", not "open" — GitHub's word does not work here.
        $this->http->assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_contains($request->url(), 'state=opened')
            && str_contains($request->url(), 'source_branch=content%2Fstaging'));
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
    public function a_rejected_request_surfaces_with_its_status_and_body(): void
    {
        $this->http->fake([
            '*' => $this->http->response(['message' => ['Another open merge request already exists']], 409),
        ]);

        try {
            $this->client()->open($this->request());
            $this->fail('Expected the rejection to surface.');
        } catch (PullRequestFailedException $exception) {
            $this->assertStringContainsString('409', $exception->getMessage());
            $this->assertStringContainsString('already exists', $exception->getMessage());
            $this->assertTrue($exception->retryable());
        }
    }

    #[Test]
    public function a_response_without_an_iid_is_not_silently_accepted(): void
    {
        $this->http->fake(['*' => $this->http->response(['id' => 900, 'web_url' => 'https://x'], 201)]);

        $this->expectException(PullRequestFailedException::class);

        $this->client()->open($this->request());
    }

    #[Test]
    public function a_self_hosted_api_url_is_honoured(): void
    {
        $this->http->fake(['*' => $this->http->response($this->gitlabPayload(), 201)]);

        $this->client(apiUrl: 'https://git.example.com/api/v4/')->open($this->request());

        $this->http->assertSent(
            fn (Request $request): bool => str_starts_with($request->url(), 'https://git.example.com/api/v4/projects/'),
        );
    }

    /** @return array<string, mixed> */
    private function gitlabPayload(): array
    {
        return [
            'id' => 900,
            'iid' => 7,
            'web_url' => 'https://gitlab.com/acme/websites/site/-/merge_requests/7',
            'title' => 'Content changes from content/staging',
            'draft' => false,
            'source_branch' => 'content/staging',
            'target_branch' => 'main',
        ];
    }

    /** @param list<string> $labels */
    private function request(
        bool $draft = false,
        array $labels = [],
        string $title = 'Content changes from content/staging',
    ): PullRequestRequestDTO {
        return new PullRequestRequestDTO(
            repository: RepositorySlugDTO::fromString('acme/websites/site'),
            head: BranchNameDTO::fromString('content/staging'),
            base: BranchNameDTO::fromString('main'),
            title: $title,
            body: '- content/home.md',
            draft: $draft,
            labels: $labels,
        );
    }

    private function client(string $apiUrl = 'https://gitlab.com/api/v4'): GitlabPullRequestClient
    {
        return new GitlabPullRequestClient($this->http, new PullRequestConfigDTO(
            driver: PullRequestDriver::GitLab,
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
