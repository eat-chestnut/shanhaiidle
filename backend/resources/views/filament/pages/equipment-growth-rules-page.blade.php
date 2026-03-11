<x-filament-panels::page>
    <div class="mx-auto w-full max-w-screen-2xl">
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <div class="sticky bottom-0 z-10 flex items-center justify-end gap-3 rounded-xl border border-gray-200 bg-white/95 p-4 shadow-sm backdrop-blur dark:border-white/10 dark:bg-gray-900/95">
                <x-filament::button type="button" color="gray" x-on:click="window.history.back()">
                    返回
                </x-filament::button>

                <x-filament::button type="submit" icon="heroicon-o-check">
                    保存配置
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
