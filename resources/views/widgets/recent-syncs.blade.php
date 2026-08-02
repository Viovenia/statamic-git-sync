{{--
    Deliberately plain: Statamic's control panel styles change between majors,
    so this leans on a handful of long-lived utility classes and nothing else.
    No JavaScript — the file list is a native <details> element.

    The files are listed by name only. A real diff would mean keeping a second
    copy of the content in storage, and git already has the first one, so the
    commit and the pull request are linked instead.
--}}
<div class="card p-0 overflow-hidden">
    <div class="flex items-center justify-between p-4 border-b">
        <h2>{{ $title }}</h2>
    </div>

    @unless ($enabled)
        <div class="p-4 text-sm text-gray-600">
            {{ __('statamic-git-sync::widgets.recent_syncs.disabled') }}
            {{ __('statamic-git-sync::widgets.recent_syncs.disabled_hint', ['key' => 'statamic-git-sync.history.enabled']) }}
        </div>
    @else
        @forelse ($entries as $entry)
            <div class="p-4 {{ ! $loop->last ? 'border-b' : '' }}">
                <div class="flex items-baseline justify-between">
                    <div class="flex items-baseline">
                        <span class="mr-2" title="{{ $entry->outcome->label() }}">
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
                                {{ trans_choice('statamic-git-sync::widgets.recent_syncs.files', $entry->files) }}
                                @if ($entry->hasStat())
                                    · {{ $entry->stat() }}
                                @endif
                                · {{ $entry->trigger->label() }}
                                · {{ \Illuminate\Support\Carbon::createFromTimestamp($entry->at)->diffForHumans() }}
                            </div>
                        </div>
                    </div>

                    <span class="text-2xs text-gray-600">
                        {{ __('statamic-git-sync::widgets.recent_syncs.duration', ['milliseconds' => $entry->durationMs]) }}
                    </span>
                </div>

                @if ($entry->paths !== [])
                    <details class="mt-2">
                        <summary class="text-2xs text-gray-600 cursor-pointer">
                            {{ __('statamic-git-sync::widgets.recent_syncs.show_files') }}
                        </summary>

                        <ul class="mt-2 ml-6">
                            @foreach ($entry->paths as $file)
                                <li class="text-2xs text-gray-600 flex justify-between py-px">
                                    <code>{{ $file->describe() }}</code>
                                    <span class="ml-4">{{ $file->status->label() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        @empty
            <div class="p-4 text-sm text-gray-600">
                {{ __('statamic-git-sync::widgets.recent_syncs.empty') }}
            </div>
        @endforelse
    @endunless
</div>
