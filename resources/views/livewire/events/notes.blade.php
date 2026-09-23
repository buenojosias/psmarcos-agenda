<x-card header="Notas" shadowless bordered>
    <div class="space-y-4">
        @forelse ($notes as $note)
            <div wire:key="event-note-{{ $note->id }}" class="border-b border-gray-200 pb-4 last:border-b-0 last:pb-0 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="font-medium text-gray-900 dark:text-white">{{ $note->user_id === auth()->id() ? 'Mim' : ($note->user?->name ?? 'Usuário removido') }}</span>
                    <time class="text-sm text-gray-500 dark:text-gray-400" datetime="{{ $note->created_at->toIso8601String() }}">{{ $note->created_at->format('d/m/Y H:i') }}</time>
                </div>
                <p class="mt-2 whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">{{ $note->content }}</p>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">Nenhuma nota cadastrada.</p>
        @endforelse
    </div>

    <form wire:submit="save" class="mt-6 space-y-4">
        <x-textarea wire:model="content" label="Nova nota" maxlength="2000" count />
        <div class="flex justify-end">
            <x-button submit text="Adicionar nota" loading="save" />
        </div>
    </form>
</x-card>
