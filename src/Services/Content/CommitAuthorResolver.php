<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Services\Content;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Statamic\Auth\User as StatamicUser;
use Viovenia\GitSync\Contracts\CommitAuthorResolverContract;
use Viovenia\GitSync\DataTransferObjects\Config\CommitConfigDTO;
use Viovenia\GitSync\DataTransferObjects\Git\CommitAuthorDTO;

/**
 * Credits the control panel user who caused the change.
 *
 * Resolution happens in the request that fired the event, never in the queue
 * worker — by the time the job runs there is no authenticated user left, and
 * guessing one would attribute somebody else's edit.
 */
final class CommitAuthorResolver implements CommitAuthorResolverContract
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly CommitConfigDTO $config,
    ) {}

    public function resolve(): ?CommitAuthorDTO
    {
        if (! $this->config->authorFromUser) {
            return $this->fallback();
        }

        $user = $this->currentUser();

        if ($user === null) {
            return $this->fallback();
        }

        $email = $user->email();

        if (! is_string($email) || $email === '') {
            return $this->fallback();
        }

        $name = $user->name();

        return new CommitAuthorDTO(
            name: is_string($name) && $name !== '' ? $name : $email,
            email: $email,
        );
    }

    /**
     * The abstract Statamic user rather than the contract: `name()` lives on
     * the class, not the interface, and both the file and Eloquent drivers
     * extend it.
     */
    private function currentUser(): ?StatamicUser
    {
        $user = $this->auth->guard()->user();

        return $user instanceof StatamicUser ? $user : null;
    }

    private function fallback(): ?CommitAuthorDTO
    {
        $author = $this->config->fallbackAuthor;

        return $author->name !== '' && $author->email !== '' ? $author : null;
    }
}
