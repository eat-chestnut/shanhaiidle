<x-filament-panels::page>
    <div class="mx-auto w-full max-w-screen-2xl space-y-6">
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
            <div class="font-medium">GM / 调试系统</div>
            <div class="mt-1">
                仅开发与联调环境使用。所有改档操作都会写入 GM 日志，失败操作也会记录。
            </div>
        </div>

        {{ $this->form }}
    </div>
</x-filament-panels::page>
