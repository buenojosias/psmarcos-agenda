<div class="space-y-6">
    <div class="header"><h1>Missas</h1></div>

    @can('create', \App\Models\Mass::class)
        <div class="flex flex-wrap gap-3">
            <livewire:masses.create-schedule @created="$refresh" />
            <livewire:masses.create-extraordinary @created="$refresh" />
        </div>
    @endcan

    <x-tab selected="scheduled">
        <x-tab.items tab="scheduled" title="Missas programadas">
            <div class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-select.native wire:model.live="weekday" label="Dia da semana">
                        <option value="">Todos os dias</option>
                        <option value="0">Domingo</option>
                        <option value="1">Segunda-feira</option>
                        <option value="2">Terça-feira</option>
                        <option value="3">Quarta-feira</option>
                        <option value="4">Quinta-feira</option>
                        <option value="5">Sexta-feira</option>
                        <option value="6">Sábado</option>
                    </x-select.native>

                    <x-select.native wire:model.live="community_id" label="Comunidade">
                        <option value="0">Todas as comunidades</option>
                        @foreach ($communities as $community)
                            <option wire:key="community-{{ $community->id }}" value="{{ $community->id }}">{{ $community->name }}</option>
                        @endforeach
                    </x-select.native>

                    <x-input wire:model.live.debounce.300ms="motivation" label="Motivação" placeholder="Buscar motivação" clearable />
                    <x-date wire:model.live="dateRange" label="Intervalo de datas" range format="DD/MM/YYYY" />
                </div>
                <x-toggle wire:model.live="showPast" label="Incluir missas de datas anteriores" />

                <x-table :headers="[
                    ['index' => 'date', 'label' => 'Data', 'sortable' => false],
                    ['index' => 'starts_at', 'label' => 'Horário', 'sortable' => false],
                    ['index' => 'community', 'label' => 'Comunidade', 'sortable' => false],
                    ['index' => 'motivation', 'label' => 'Motivação', 'sortable' => false],
                ]" :rows="$masses" paginate loading empty="Nenhuma missa encontrada.">
                    @interact('column_date', $row)
                        {{ $row->starts_at->format('d/m/Y') }} ({{ $row->starts_at->locale('pt_BR')->isoFormat('dddd') }})
                    @endinteract

                    @interact('column_starts_at', $row)
                        <div class="flex items-center gap-2">
                            {{ $row->starts_at->format('H:i') }}
                            @if ($row->has_reservation_conflict)
                                <x-tooltip icon="exclamation-triangle" color="amber" text="Existe conflito de reserva de espaço neste horário." />
                            @endif
                        </div>
                    @endinteract

                    @interact('column_community', $row)
                        {{ $row->primaryReservation?->place?->community?->name ?? 'Comunidade não informada' }}
                    @endinteract
                </x-table>
            </div>
        </x-tab.items>
        <x-tab.items tab="weekly" title="Cronograma semanal">
            <div class="grid gap-6 lg:grid-cols-2">
                <section class="space-y-4">
                    <h2 class="text-lg font-semibold">Por dia da semana</h2>
                    <x-accordion>
                        @forelse ($schedulesByWeekday as $day => $schedules)
                            <x-accordion.items :title="$weekdays[$day]" id="weekday-{{ $day }}" wire:key="weekday-{{ $day }}">
                                <ul class="space-y-3">
                                    @foreach ($schedules as $schedule)
                                        <li wire:key="day-schedule-{{ $schedule->id }}">
                                            <p class="font-medium">{{ substr($schedule->starts_at, 0, 5) }} - {{ $schedule->community->name }}</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $schedule->motivation ?? 'Missa' }}</p>
                                        </li>
                                    @endforeach
                                </ul>
                            </x-accordion.items>
                        @empty
                            <p>Nenhum horário fixo vigente.</p>
                        @endforelse
                    </x-accordion>
                </section>
                <section class="space-y-4">
                    <h2 class="text-lg font-semibold">Por comunidade</h2>
                    <x-accordion>
                        @forelse ($schedulesByCommunity as $communityId => $schedules)
                            <x-accordion.items :title="$schedules->first()->community->name" id="schedule-community-{{ $communityId }}" wire:key="schedule-community-{{ $communityId }}">
                                <ul class="space-y-3">
                                    @foreach ($schedules as $schedule)
                                        <li wire:key="community-schedule-{{ $schedule->id }}">
                                            <p class="font-medium">{{ $weekdays[$schedule->weekday] }} — {{ substr($schedule->starts_at, 0, 5) }}</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $schedule->motivation ?? 'Missa' }}</p>
                                        </li>
                                    @endforeach
                                </ul>
                            </x-accordion.items>
                        @empty
                            <p>Nenhum horário fixo vigente.</p>
                        @endforelse
                    </x-accordion>
                </section>
            </div>
        </x-tab.items>
    </x-tab>
</div>
