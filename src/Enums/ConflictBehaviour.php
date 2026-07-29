<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Enums;

enum ConflictBehaviour: string
{
    /**
     * Undo the rebase or merge, leave the working tree exactly as it was,
     * and report the conflict. The default, and the only safe option for
     * an unattended process.
     */
    case Abort = 'abort';

    /**
     * Undo the integration as well, but let the exception escape so the
     * queue marks the job as failed and your monitoring notices.
     */
    case Throw = 'throw';
}
