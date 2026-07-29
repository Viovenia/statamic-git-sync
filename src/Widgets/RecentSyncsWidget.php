<?php

declare(strict_types=1);

namespace Viovenia\GitSync\Widgets;

use Illuminate\Contracts\View\View;
use Statamic\Widgets\Widget;
use Viovenia\GitSync\Contracts\HistoryRepositoryContract;

/**
 * Shows the last few syncs on the control panel dashboard.
 *
 * `extends Widget` is one of the few places this package inherits: Statamic
 * discovers widgets by that base class, so there is no interface to implement
 * instead.
 *
 * Add it to `config/statamic/cp.php`:
 *
 *     'widgets' => [
 *         ['type' => 'recent_syncs', 'width' => 50, 'limit' => 5],
 *     ],
 */
final class RecentSyncsWidget extends Widget
{
    public function __construct(private readonly HistoryRepositoryContract $history) {}

    public function html(): View
    {
        $limit = (int) $this->config('limit', 5);

        return view('statamic-git-sync::widgets.recent-syncs', [
            'title' => $this->config('title', 'Recent content syncs'),
            'entries' => $this->history->recent(max(1, $limit)),
            'enabled' => $this->history->enabled(),
        ]);
    }
}
