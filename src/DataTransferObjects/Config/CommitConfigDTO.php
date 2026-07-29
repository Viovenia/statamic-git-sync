<?php

declare(strict_types=1);

namespace Viovenia\GitSync\DataTransferObjects\Config;

use Viovenia\GitSync\DataTransferObjects\Git\CommitAuthorDTO;

final readonly class CommitConfigDTO
{
    public function __construct(
        public string $messageTemplate,
        public int $summaryMaxFiles,
        /** Appended as a trailer line. Null disables the loop guard marker. */
        public ?string $trailer,
        public bool $authorFromUser,
        public CommitAuthorDTO $fallbackAuthor,
        public bool $allowEmpty,
        public bool $sign,
    ) {}
}
