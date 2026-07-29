{{--
    Deliberately plain: Statamic's control panel styles change between majors,
    so this leans on a handful of long-lived utility classes and nothing else.
    No JavaScript, no build step.
--}}
<div class="card p-0 overflow-hidden">
    <div class="flex items-center justify-between p-4 border-b">
        <h2>{{ $title }}</h2>
    </div>

    @unless ($enabled)
        <div class="p-4 text-sm text-gray-600">
            History is switched off, so there is nothing to show here.
            Enable <code>statamic-git-sync.history.enabled</code> to use this widget.
        </div>
    @else
        @forelse ($entries as $entry)
            <div class="flex items-baseline justify-between p-4 {{ ! $loop->last ? 'border-b' : '' }}">
                <div class="flex items-baseline">
                    <span class="mr-2" title="{{ $entry->outcome->value }}">
                        {{ $entry->successful() ? '✓' : '✕' }}
                    </span>

                    <div>
                        <div class="text-sm">
                            @if ($entry->pullRequestUrl)
                                <a href="{{ $entry->pullRequestUrl }}" target="_blank" rel="noopener">
                                    #{{ $entry->pullRequestNumber }}
                                </a>
                            @elseif ($entry->commit)
                                <code>{{ $entry->commit }}</code>
                            @endif

                            {{ $entry->failure ?? $entry->message }}
                        </div>

                        <div class="text-2xs text-gray-600 mt-1">
                            {{ $entry->files }} file(s) · {{ $entry->trigger->value }} ·
                            {{ \Illuminate\Support\Carbon::createFromTimestamp($entry->at)->diffForHumans() }}
                        </div>
                    </div>
                </div>

                <span class="text-2xs text-gray-600">{{ $entry->durationMs }}ms</span>
            </div>
        @empty
            <div class="p-4 text-sm text-gray-600">
                Nothing synced yet.
            </div>
        @endforelse
    @endunless
</div>
