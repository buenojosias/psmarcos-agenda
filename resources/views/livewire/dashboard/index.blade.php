<div class="flex min-w-0 flex-col gap-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h1 class="break-words text-2xl font-semibold text-primary-950 dark:text-gray-100">{{ $greeting }}, {{ $firstName }}</h1>
            <p class="mt-1 text-sm text-gray-500 first-letter:uppercase dark:text-dark-400">{{ $date }}</p>
        </div>
        @if ($canCreateEvent)
            <div class="shrink-0"><x-button text="Novo evento" icon="tabler.plus" :href="route('events.create')" /></div>
        @endif
    </header>

    <livewire:dashboard.attention />

    <div class="grid min-w-0 grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="min-w-0 lg:col-span-2"><livewire:dashboard.upcoming-events /></div>
        <div class="min-w-0"><livewire:dashboard.quick-actions /></div>
    </div>

    @if ($isPascom)
        <livewire:dashboard.pascom-overview />
    @endif
    @if ($isPriest)
        <livewire:dashboard.upcoming-masses />
    @endif
</div>
