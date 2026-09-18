<x-app-layout>
    @php
        $stats = [
            ['12', 'Meus eventos', 'neste ano', 'blue'],
            ['2', 'Solicitações', 'em análise', 'amber'],
            ['8', 'Eventos confirmados', 'neste ano', 'emerald'],
            ['1', 'Conflito de data', 'precisa de atenção', 'rose'],
        ];
        $events = [
            ['20', 'SET', 'Ensaio do Coral Doce Canto', '14h00 – 16h00  •  Salão Paroquial', 'blue'],
            ['22', 'SET', 'Reunião da Pastoral da Família', '19h30 – 21h00  •  Sala 1', 'violet'],
            ['27', 'SET', 'Grupo de Oração', '19h30 – 21h00  •  Igreja Matriz', 'emerald'],
            ['05', 'OUT', 'Missa das Crianças', '10h00 – 11h00  •  Igreja Matriz', 'amber'],
        ];
        $requests = [
            ['Retiro do Coral Doce Canto', '12/10/2025  •  Salão Paroquial', 'Em análise'],
            ['Ensaio Especial de Natal', '15/11/2025  •  Igreja Matriz', 'Em análise'],
            ['Apresentação de Natal', '20/12/2025  •  Igreja Matriz', 'Aprovado'],
            ['Ensaio Geral', '18/12/2025  •  Salão Paroquial', 'Aprovado'],
        ];
        $calendar = [
            ['31', 1, ''], ['1', 0, ''], ['2', 0, 'violet'], ['3', 0, ''], ['4', 0, ''], ['5', 0, ''], ['6', 0, ''],
            ['7', 0, ''], ['8', 0, ''], ['9', 0, ''], ['10', 0, 'emerald'], ['11', 0, ''], ['12', 0, ''], ['13', 0, ''],
            ['14', 0, ''], ['15', 0, 'emerald'], ['16', 0, 'selected'], ['17', 0, ''], ['18', 0, 'amber'], ['19', 0, ''], ['20', 0, 'emerald'],
            ['21', 0, ''], ['22', 0, 'gray'], ['23', 0, ''], ['24', 0, ''], ['25', 0, 'amber'], ['26', 0, ''], ['27', 0, 'emerald'],
            ['28', 0, ''], ['29', 0, ''], ['30', 0, ''], ['1', 1, ''], ['2', 1, ''], ['3', 1, ''], ['4', 1, ''],
        ];
        $actions = [
            ['Nova solicitação', 'de evento', 'blue'], ['Ver calendário', 'completo', 'indigo'],
            ['Consultar', 'ambientes', 'violet'], ['Conhecer', 'comunidades', 'emerald'], ['Ajuda e suporte', '', 'amber'],
        ];
    @endphp

    <div class="min-h-full bg-slate-50/80 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <section class="relative isolate overflow-hidden border-b border-slate-200/70 bg-gradient-to-r from-sky-50 via-white to-emerald-50 px-4 pb-24 pt-8 dark:border-slate-800 dark:from-slate-900 dark:via-slate-900 dark:to-emerald-950/30 sm:px-6 lg:px-8">
            <div class="pointer-events-none absolute inset-0 -z-10">
                <div class="absolute -right-12 -top-24 size-96 rounded-full bg-emerald-200/45 blur-3xl dark:bg-emerald-800/20"></div>
                <div class="absolute left-1/3 top-4 size-64 rounded-full bg-sky-200/50 blur-3xl dark:bg-sky-800/20"></div>
                <svg class="absolute bottom-0 right-8 hidden h-44 w-80 text-emerald-900/10 lg:block dark:text-emerald-300/10" viewBox="0 0 320 176" fill="currentColor" aria-hidden="true"><path d="M70 176V88l90-65 90 65v88h-32v-72l-58-42-58 42v72H70Z"/><path d="M137 176v-54a23 23 0 0 1 46 0v54h-16v-54a7 7 0 0 0-14 0v54h-16ZM154 0h12v44h-12zM141 13h38v10h-38z"/></svg>
            </div>
            <div class="mx-auto flex max-w-[1500px] flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Terça-feira, 16 de setembro de 2025</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950 dark:text-white sm:text-4xl">Bem-vindo, Josias!</h1>
                    <p class="mt-1 text-base text-slate-600 dark:text-slate-300 sm:text-lg">Organizar hoje para uma comunidade mais unida amanhã.</p>
                </div>
                <blockquote class="max-w-sm rounded-2xl border border-white/70 bg-white/70 px-6 py-4 text-center text-sm italic leading-6 text-slate-600 shadow-sm backdrop-blur dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300">
                    “Onde dois ou mais estiverem reunidos em meu nome,<br class="hidden sm:block"> ali estou eu no meio deles.”
                    <footer class="mt-1 text-xs not-italic text-slate-400">Mateus 18,20</footer>
                </blockquote>
            </div>
        </section>

        <main class="mx-auto -mt-16 max-w-[1500px] space-y-5 px-4 pb-10 sm:px-6 lg:px-8">
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumo">
                @foreach ($stats as [$value, $title, $subtitle, $tone])
                    <article @class([
                        'flex min-h-24 items-center gap-4 rounded-2xl border p-4 shadow-sm backdrop-blur',
                        'border-blue-100 bg-blue-50/90 dark:border-blue-900 dark:bg-blue-950/60' => $tone === 'blue',
                        'border-amber-100 bg-amber-50/90 dark:border-amber-900 dark:bg-amber-950/60' => $tone === 'amber',
                        'border-emerald-100 bg-emerald-50/90 dark:border-emerald-900 dark:bg-emerald-950/60' => $tone === 'emerald',
                        'border-rose-100 bg-rose-50/90 dark:border-rose-900 dark:bg-rose-950/60' => $tone === 'rose',
                    ])>
                        <span @class(['grid size-14 shrink-0 place-items-center rounded-xl', 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' => $tone === 'blue', 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' => $tone === 'amber', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $tone === 'emerald', 'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' => $tone === 'rose'])>
                            @if ($tone === 'emerald')
                                <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="9"/></svg>
                            @elseif ($tone === 'rose')
                                <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4m0 4h.01M10.3 3.7 2.5 17.2A2 2 0 0 0 4.2 20h15.6a2 2 0 0 0 1.7-2.8L13.7 3.7a2 2 0 0 0-3.4 0Z"/></svg>
                            @else
                                <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4m8-4v4M4 10h16"/></svg>
                            @endif
                        </span>
                        <div class="min-w-0 grow"><p class="text-2xl font-bold leading-none">{{ $value }}</p><p class="mt-1 truncate font-semibold">{{ $title }}</p><p class="text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p></div>
                        <svg class="size-5 shrink-0 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m9 18 6-6-6-6"/></svg>
                    </article>
                @endforeach
            </section>

            <section class="grid gap-5 xl:grid-cols-12">
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-5">
                    <header class="flex items-center justify-between border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5"><h2 class="text-lg font-bold">Próximos eventos</h2><a href="#" class="text-sm font-medium text-teal-700 dark:text-teal-400">Ver todos</a></header>
                    <div class="divide-y divide-slate-100 px-4 dark:divide-slate-800 sm:px-5">
                        @foreach ($events as [$day, $month, $title, $details, $tone])
                            <div class="flex items-center gap-3 py-3">
                                <div @class(['grid size-12 shrink-0 place-items-center rounded-xl text-center', 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300' => $tone === 'blue', 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300' => $tone === 'violet', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $tone === 'emerald', 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $tone === 'amber'])><span class="text-lg font-bold leading-4">{{ $day }}</span><span class="text-[10px] font-bold leading-none">{{ $month }}</span></div>
                                <div class="min-w-0 grow"><p class="truncate text-sm font-semibold">{{ $title }}</p><p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $details }}</p></div>
                                <span class="hidden rounded-lg bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 sm:inline">Confirmado</span>
                                <svg class="size-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-5">
                    <header class="flex items-center justify-between border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5"><h2 class="text-lg font-bold">Minhas solicitações</h2><a href="#" class="text-sm font-medium text-teal-700 dark:text-teal-400">Ver todas</a></header>
                    <div class="divide-y divide-slate-100 px-4 dark:divide-slate-800 sm:px-5">
                        @foreach ($requests as [$title, $details, $status])
                            <div class="flex items-center gap-3 py-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5M10 13h5m-5 4h5"/></svg></span>
                                <div class="min-w-0 grow"><p class="truncate text-sm font-semibold">{{ $title }}</p><p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $details }}</p></div>
                                <span @class(['hidden rounded-lg px-2.5 py-1 text-[11px] font-medium sm:inline', 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $status === 'Em análise', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $status === 'Aprovado'])>{{ $status }}</span>
                                <svg class="size-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5 xl:col-span-2">
                    <header class="flex items-center justify-between"><button type="button" class="p-1 text-slate-500" aria-label="Mês anterior">‹</button><h2 class="text-sm font-bold">Setembro de 2025</h2><button type="button" class="p-1 text-slate-500" aria-label="Próximo mês">›</button></header>
                    <div class="mt-5 grid grid-cols-7 gap-y-2 text-center text-[11px]">
                        @foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $weekday)<span class="font-semibold text-slate-400">{{ $weekday }}</span>@endforeach
                        @foreach ($calendar as [$day, $muted, $marker])
                            <span @class(['relative mx-auto grid size-7 place-items-center rounded-full font-medium', 'text-slate-300 dark:text-slate-600' => $muted, 'bg-teal-700 text-white shadow-sm' => $marker === 'selected', 'text-slate-700 dark:text-slate-300' => ! $muted && $marker !== 'selected'])>{{ $day }}@if ($marker && $marker !== 'selected')<span @class(['absolute bottom-0 size-1 rounded-full', 'bg-emerald-500' => $marker === 'emerald', 'bg-violet-500' => $marker === 'violet', 'bg-amber-500' => $marker === 'amber', 'bg-slate-400' => $marker === 'gray'])></span>@endif</span>
                        @endforeach
                    </div>
                </article>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Ações rápidas">
                @foreach ($actions as [$title, $subtitle, $tone])
                    <a href="#" class="group flex min-h-16 items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <span @class(['grid size-11 shrink-0 place-items-center rounded-xl', 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300' => $tone === 'blue', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' => $tone === 'indigo', 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300' => $tone === 'violet', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $tone === 'emerald', 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $tone === 'amber'])><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M12 8v8m-4-4h8"/></svg></span>
                        <span class="min-w-0 grow text-sm leading-5"><span class="block font-medium">{{ $title }}</span>@if ($subtitle)<span class="block text-slate-500 dark:text-slate-400">{{ $subtitle }}</span>@endif</span><span class="text-slate-400 transition group-hover:translate-x-0.5">›</span>
                    </a>
                @endforeach
            </section>

            <section class="grid gap-5 xl:grid-cols-12">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5 xl:col-span-4">
                    <header class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800"><h2 class="flex items-center gap-2 font-bold">Avisos importantes <span class="size-1.5 rounded-full bg-rose-500"></span></h2><a href="#" class="text-sm font-medium text-teal-700 dark:text-teal-400">Ver todos</a></header>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ([['Prazo para solicitação de eventos em 2026', 'Solicitações até 30/11/2025.', '10/09'], ['Manutenção no Salão Paroquial', 'Salão indisponível de 01/10 a 05/10.', '05/09'], ['Reunião de CPP', 'Dia 28/09, às 19h30, na sala 2.', '01/09']] as [$title, $description, $date])
                            <div class="flex items-start gap-3 py-3"><span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-950 dark:text-rose-300">!</span><div class="min-w-0 grow"><p class="truncate text-sm font-medium">{{ $title }}</p><p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $description }}</p></div><time class="text-xs text-slate-400">{{ $date }}</time></div>
                        @endforeach
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5 xl:col-span-5">
                    <header class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800"><h2 class="font-bold">Disponibilidade dos ambientes</h2><a href="#" class="text-sm font-medium text-teal-700 dark:text-teal-400">Ver todos</a></header>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ([['Igreja Matriz', 'Disponível hoje', true], ['Salão Paroquial', 'Ocupado agora', false], ['Sala 1', 'Disponível hoje', true], ['Sala 2', 'Disponível hoje', true]] as [$place, $status, $available])
                            <div class="flex items-center gap-3 py-2.5"><span class="grid size-8 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 10 9-7 9 7v10H3z"/><path d="M9 20v-6h6v6"/></svg></span><span class="grow text-sm font-medium">{{ $place }}</span><span @class(['rounded-lg px-2.5 py-1 text-[11px] font-medium', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $available, 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300' => ! $available])>{{ $status }}</span><span class="text-slate-400">›</span></div>
                        @endforeach
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5 xl:col-span-3">
                    <header class="border-b border-slate-100 pb-3 dark:border-slate-800"><h2 class="font-bold">Links rápidos</h2></header>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach (['Site da Paróquia', 'Horários de Missas', 'Evangelho do Dia', 'Formação e Materiais'] as $link)
                            <a href="#" class="group flex items-center gap-3 py-2.5 text-sm hover:text-teal-700 dark:hover:text-teal-400"><span class="grid size-8 place-items-center rounded-lg bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-300"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg></span><span class="grow font-medium">{{ $link }}</span><span class="text-slate-400">↗</span></a>
                        @endforeach
                    </div>
                </article>
            </section>
        </main>
    </div>
</x-app-layout>
