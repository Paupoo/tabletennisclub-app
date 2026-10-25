{{--
    L'étape des paires, pour tout tournoi de double : le comité compose les
    paires, ou retouche celles que les joueurs ont choisies — un absent le jour
    même, et deux joueurs seuls font une paire.
--}}
@if ($this->currentTournament?->match_type === 'double')
    @php
        $board = $this->pairBoard;
        $locked = $this->isLaunched;
        $benchCount = count($board['bench']);
    @endphp
    <div data-pair-board
        x-data="{
            dragging: null,
            over: null,
            selected: null,
            drawn: @js($this->poolsGenerated),
            move(player, target) {
                if (! this.drawn) { $wire.movePlayer(player, target); return; }
                this.$dispatch('app-confirm', {
                    message: @js(__('The pools are drawn: this change erases the pools and their matches, and you will have to draw them again. Continue?')),
                    method: 'movePlayer',
                    params: [player, target],
                    danger: true,
                    id: $wire.$id,
                });
            },
            pick(id) {
                if (this.selected === null) { this.selected = id; return; }
                if (this.selected !== id) { this.move(this.selected, id); }
                this.selected = null;
            },
            drop(target) {
                if (this.dragging !== null && this.dragging !== target) { this.move(this.dragging, target); }
                this.dragging = null;
                this.over = null;
            },
        }">
        <x-card :title="__('Pair composition')" shadow class="mt-6">
            <x-slot:menu>
                <span class="text-xs text-base-content/70">
                    {{ trans_choice(':count pair|:count pairs', count($board['pairs']), ['count' => count($board['pairs'])]) }}
                    &mdash;
                    {{ trans_choice(':count on the bench|:count on the bench', $benchCount, ['count' => $benchCount]) }}
                </span>
            </x-slot:menu>

            @if ($locked)
                <p class="mb-4 flex items-center gap-1.5 text-sm text-base-content/70">
                    <x-icon name="o-lock-closed" class="h-4 w-4" /> {{ __('The tournament is launched: pairs can no longer change.') }}
                </p>
            @else
                @if ($this->poolsGenerated)
                    <p class="mb-4 flex items-start gap-1.5 text-sm text-warning-content" data-draw-warning>
                        <x-icon name="o-exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                        {{ __('The pools are drawn: changing a pair erases them, with their matches.') }}
                    </p>
                @endif
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <x-button :label="__('Propose pairs')" icon="o-sparkles" class="btn-primary btn-sm"
                        wire:click="proposePairs" spinner="proposePairs" :disabled="$benchCount < 2" />
                    <x-button :label="__('Send everyone back to the bench')" icon="o-arrow-uturn-left" class="btn-ghost btn-sm"
                        x-on:click="$dispatch('app-confirm', { ...{{ Js::from(['message' => $this->poolsGenerated ? __('Break every pair, send everyone back to the bench, and erase the pools and their matches?') : __('Break every pair and send everyone back to the bench?'), 'method' => 'resetPairs', 'params' => [], 'danger' => true]) }}, id: $wire.$id })" spinner="resetPairs"
                        :disabled="$board['pairs'] === []" />
                    @if ($this->currentTournament->hasPairsComposedByClub() && $this->currentTournament->pairs_published_at === null)
                        <x-button :label="__('Publish the pairs')" icon="o-paper-airplane" class="btn-outline btn-sm"
                            x-on:click="$dispatch('app-confirm', { ...{{ Js::from(['message' => __('Each paired player will receive an email naming their partner. After that, any change is emailed to the players it concerns. Publish?'), 'method' => 'publishPairs', 'params' => [], 'danger' => false]) }}, id: $wire.$id })" spinner="publishPairs"
                            :disabled="$board['pairs'] === []" />
                    @elseif ($this->currentTournament->pairs_published_at !== null)
                        <x-badge icon="o-check" class="badge-success badge-soft"
                            :value="__('Published on :date', ['date' => $this->currentTournament->pairs_published_at->format('d/m H:i')])" />
                    @else
                        <x-badge icon="o-user-group" class="badge-ghost"
                            :value="__('Pairs chosen by the players')" />
                    @endif
                    <p class="text-xs text-base-content/70 sm:ml-auto">
                        {{ __('Drag a player onto another to swap them, or tap one then the other.') }}
                    </p>
                </div>
            @endif

            @if ($benchCount % 2 === 1 && ! $locked)
                <x-alert icon="o-exclamation-triangle" class="alert-warning alert-soft mb-4"
                    :title="__('One player is missing for the last pair.')"
                    :description="__('Register one more player, or cancel a registration, before drawing the pools.')" />
            @endif

            <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
                {{-- Pairs --}}
                <div class="grid content-start gap-2 sm:grid-cols-2">
                    @forelse ($board['pairs'] as $pair)
                        <div wire:key="pair-{{ $pair['id'] }}" class="space-y-1 rounded-xl border border-base-300 bg-base-200/50 p-2">
                            @foreach ($pair['players'] as $player)
                                @include('admin.club-events.tournaments.partials.shared.pair-board-player', ['player' => $player, 'locked' => $locked])
                            @endforeach
                        </div>
                    @empty
                        <p class="text-sm text-base-content/70 sm:col-span-2">{{ __('No pairs yet.') }}</p>
                    @endforelse
                </div>

                {{-- Bench --}}
                <div
                    @unless ($locked)
                        @dragover.prevent="over = 'bench'"
                        @dragleave="over === 'bench' && (over = null)"
                        @drop.prevent="drop(null)"
                        :class="over === 'bench' ? 'border-primary ring-2 ring-primary/30' : 'border-base-300'"
                    @endunless
                    class="space-y-1 rounded-xl border border-dashed p-2">
                    <div class="flex items-center justify-between px-1 pb-1">
                        <p class="text-sm font-semibold">{{ __('Bench') }}</p>
                        @unless ($locked)
                            <x-button x-show="selected !== null" x-cloak :label="__('Send to the bench')" class="btn-ghost btn-xs"
                                @click="move(selected, null); selected = null" />
                        @endunless
                    </div>
                    @forelse ($board['bench'] as $player)
                        @include('admin.club-events.tournaments.partials.shared.pair-board-player', ['player' => $player, 'locked' => $locked])
                    @empty
                        <p class="px-1 text-sm text-success flex items-center gap-1.5">
                            <x-icon name="o-check-circle" class="w-4 h-4" /> {{ __('All registered players are paired.') }}
                        </p>
                    @endforelse
                </div>
            </div>
        </x-card>
        <div data-pair-board-end></div>
    </div>
@endif
