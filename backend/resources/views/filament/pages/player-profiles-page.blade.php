<x-filament-panels::page>
    <div class="mx-auto w-full max-w-screen-2xl space-y-6">
        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-100">
            <div class="font-medium">玩家档案快照</div>
            <div class="mt-1">
                后台展示的是客户端关键节点同步上来的最近玩家快照，主要用于联调和配置核对，不是实时在线内存状态。
            </div>
        </div>

        {{ $this->form }}

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 text-sm font-medium text-gray-950 dark:border-white/10 dark:text-white">
                最近同步玩家
            </div>

            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($this->recentProfiles() as $row)
                    <button
                        type="button"
                        wire:click="openProfile('{{ $row['player_id'] }}')"
                        class="flex w-full items-center justify-between gap-4 px-4 py-3 text-left transition hover:bg-gray-50 dark:hover:bg-white/5"
                    >
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ $row['nickname'] !== '' ? $row['nickname'] . '｜' : '' }}{{ $row['player_id'] }}
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $row['stage_name'] !== '—' ? $row['stage_name'] : '暂无主线进度' }}
                            </div>
                        </div>

                        <div class="shrink-0 text-right text-xs text-gray-500 dark:text-gray-400">
                            <div>Lv{{ $row['level'] }}</div>
                            <div class="mt-1">{{ $row['updated_at'] }}</div>
                        </div>
                    </button>
                @empty
                    <div class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">
                        当前还没有任何玩家快照。请先让客户端进入游戏并完成一次档案同步。
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
