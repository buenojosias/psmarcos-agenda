<?php

declare(strict_types=1);

namespace App\Livewire\Events\Edit;

use App\Models\Event;
use App\Models\Place;
use Livewire\Component;
use App\Models\Community;
use App\Models\EventDetail;
use Carbon\CarbonImmutable;
use App\Enums\EventStatusEnum;
use Illuminate\Validation\Rule;
use App\Models\PlaceReservation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use App\Actions\RescheduleEventAction;
use Illuminate\Support\Facades\Validator;
use App\Actions\CheckPlaceAvailabilityAction;
use Illuminate\Validation\ValidationException;

class Reschedule extends Component
{
    public Event $event;

    public string $date = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public ?int $community_id = null;

    public ?int $original_community_id = null;

    /** @var list<array{place_id: int|string|null, reserved_from: string, reserved_to: string, is_primary: bool}> */
    public array $reservations = [];

    /** @var list<int|string> */
    public array $selected_place_ids = [];

    public ?int $primary_place_id = null;

    /** @var list<array<string, mixed>> */
    public array $conflicts = [];

    /** @var array<int, bool> */
    public array $adjusting_reservations = [];

    public string $external_location_name = '';

    public string $external_location_address = '';

    public string $external_location_url = '';

    public bool $previewReady = false;

    public bool $previewSlide = false;

    public bool $canConfirm = false;

    public bool $confirmationModal = false;

    public bool $saved = false;

    public string $previewFingerprint = '';

    public bool $proposalGenerated = false;

    public function mount(Event $event): void
    {
        Gate::authorize('update', $event);

        abort_if($event->status === EventStatusEnum::CANCELED, 403);

        $this->event = $event;
        $this->loadForm();
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'reservations.')) {
            if ($this->community_id !== $this->original_community_id) {
                $this->syncSelectedPlacesFromReservations();
            }

            $this->invalidatePreview(clearConflicts: false);

            return;
        }

        if (str_starts_with($property, 'external_location_')) {
            $this->invalidatePreview();
        }
    }

    public function updatedDate(): void
    {
        $this->resetGeneratedProposal();
        $this->refreshAutomaticProposal();
    }

    public function updatedStartsAt(): void
    {
        $this->resetGeneratedProposal();
        $this->refreshAutomaticProposal();
    }

    public function updatedEndsAt(): void
    {
        $this->resetGeneratedProposal();
        $this->refreshAutomaticProposal();
    }

    public function updatedCommunityId(): void
    {
        if ($this->event->is_external) {
            return;
        }

        $this->selected_place_ids     = [];
        $this->primary_place_id       = null;
        $this->reservations           = [];
        $this->adjusting_reservations = [];
        $this->proposalGenerated      = false;
        $this->invalidatePreview();

        if ($this->community_id === $this->originalCommunityId()) {
            $this->proposeCurrentReservations();
        }
    }

    public function updatedSelectedPlaceIds(): void
    {
        $this->selected_place_ids = collect($this->selected_place_ids)
            ->map(fn (int|string $placeId): int => (int) $placeId)
            ->unique()
            ->values()
            ->all();

        if ($this->primary_place_id !== null
            && ! in_array($this->primary_place_id, $this->selected_place_ids, true)) {
            $this->primary_place_id = null;
        }

        $this->resetGeneratedProposal(clearReservations: true);
    }

    public function updatedPrimaryPlaceId(): void
    {
        $this->resetGeneratedProposal(clearReservations: true);
    }

    public function adjustReservation(int $index): void
    {
        if (! $this->hasConflictForReservation($index)) {
            return;
        }

        $this->adjusting_reservations[$index] = true;
    }

    public function removeReservationFromProposal(
        int $index,
        CheckPlaceAvailabilityAction $checkPlaceAvailability,
    ): void {
        Gate::authorize('update', $this->event);

        if (! $this->hasConflictForReservation($index)
            || ! array_key_exists($index, $this->reservations)) {
            return;
        }

        if ($this->reservations[$index]['is_primary']) {
            $this->addError('reservations', 'A reserva principal não pode ser removida da remarcação.');

            return;
        }

        unset($this->reservations[$index]);
        $this->reservations           = array_values($this->reservations);
        $this->adjusting_reservations = [];
        $this->proposalGenerated      = true;
        $this->syncSelectedPlacesFromReservations();

        $this->preview($checkPlaceAvailability);
    }

    public function preview(CheckPlaceAvailabilityAction $checkPlaceAvailability): void
    {
        Gate::authorize('update', $this->event);

        $this->resetValidation();
        $this->saved = false;
        $this->validateForm();

        [$startsAt, $endsAt] = $this->newEventInterval();

        if (! $this->event->is_external) {
            $this->prepareReservationProposal($startsAt, $endsAt);
            $this->validateReservationProposal();
            $this->validatePrimaryReservation($startsAt, $endsAt);
            $this->conflicts = $this->findConflicts($checkPlaceAvailability, $startsAt, $endsAt);

            if ($this->conflicts !== []) {
                $this->addError('reservations', 'Existem conflitos de ambiente na proposta. Resolva os itens abaixo e verifique novamente.');
            }
        } else {
            $this->conflicts = [];
        }

        $this->previewReady       = true;
        $this->previewFingerprint = $this->currentFingerprint();
        $this->canConfirm         = $this->getErrorBag()->isEmpty() && $this->conflicts === [];
        $this->previewSlide       = true;
    }

    public function openConfirmation(CheckPlaceAvailabilityAction $checkPlaceAvailability): void
    {
        $this->preview($checkPlaceAvailability);

        if ($this->canConfirm) {
            $this->previewSlide      = false;
            $this->confirmationModal = true;
        }
    }

    public function confirm(
        RescheduleEventAction $rescheduleEvent,
        CheckPlaceAvailabilityAction $checkPlaceAvailability,
    ): void {
        if (! $this->previewReady || $this->previewFingerprint !== $this->currentFingerprint()) {
            $this->addError('reschedule', 'A proposta foi alterada. Gere um novo preview antes de confirmar.');
            $this->confirmationModal = false;

            return;
        }

        [$startsAt, $endsAt] = $this->newEventInterval();

        try {
            $this->event = $rescheduleEvent->handle(
                event: $this->event,
                startsAt: $startsAt,
                endsAt: $endsAt,
                reservations: $this->event->is_external ? [] : $this->actionReservations(),
                communityId: $this->event->is_external ? null : $this->community_id,
                externalLocation: $this->event->is_external ? [
                    'external_location_name'    => $this->external_location_name,
                    'external_location_address' => $this->external_location_address,
                    'external_location_url'     => $this->external_location_url ?: null,
                ] : null,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }

            if (! $this->event->is_external) {
                $this->conflicts = $this->findConflicts($checkPlaceAvailability, $startsAt, $endsAt);
            }

            $this->canConfirm        = false;
            $this->previewSlide      = $this->conflicts !== [];
            $this->confirmationModal = false;

            return;
        }

        $this->confirmationModal = false;
        $this->previewSlide      = false;
        $this->saved             = true;
        $this->loadForm();
        $this->dispatch('event-rescheduled', eventId: $this->event->id);
    }

    public function render(): View
    {
        $places = $this->community_id === null
            ? collect()
            : Place::query()->where('community_id', $this->community_id)->orderBy('name')->get(['id', 'name']);

        return view('livewire.events.edit.reschedule', [
            'communities'  => Community::query()->orderBy('name')->get(['id', 'name']),
            'places'       => $places,
            'placeOptions' => $places->map(fn (Place $place): array => [
                'label' => $place->name,
                'value' => $place->id,
            ]),
            'primaryPlaceOptions' => $places
                ->whereIn('id', collect($this->selected_place_ids)->map(fn (int|string $id): int => (int) $id))
                ->map(fn (Place $place): array => [
                    'label' => $place->name,
                    'value' => $place->id,
                ])
                ->values(),
        ]);
    }

    private function loadForm(): void
    {
        $this->resetValidation();
        $this->event->refresh();

        $this->date      = $this->event->starts_at->format('Y-m-d');
        $this->starts_at = $this->event->starts_at->format('H:i');
        $this->ends_at   = $this->event->ends_at->format('H:i');

        $currentReservations = PlaceReservation::query()
            ->where('event_id', $this->event->id)
            ->orderBy('id')
            ->get();
        $primaryReservation = $currentReservations->firstWhere('is_primary', true);
        $this->community_id = $primaryReservation === null
            ? null
            : Place::query()->whereKey($primaryReservation->place_id)->value('community_id');
        $this->original_community_id = $this->community_id;
        $detail                      = EventDetail::query()->where('event_id', $this->event->id)->first();

        if ($detail === null) {
            $this->external_location_name    = '';
            $this->external_location_address = '';
            $this->external_location_url     = '';
        } else {
            $this->external_location_name    = (string) ($detail->external_location_name ?? '');
            $this->external_location_address = (string) ($detail->external_location_address ?? '');
            $this->external_location_url     = (string) ($detail->external_location_url ?? '');
        }

        $this->reservations = $currentReservations
            ->map(fn ($reservation): array => [
                'place_id'      => $reservation->place_id,
                'reserved_from' => $reservation->reserved_from->format('Y-m-d\TH:i'),
                'reserved_to'   => $reservation->reserved_to->format('Y-m-d\TH:i'),
                'is_primary'    => $reservation->is_primary,
            ])
            ->values()
            ->all();
        $this->conflicts              = [];
        $this->selected_place_ids     = [];
        $this->primary_place_id       = null;
        $this->adjusting_reservations = [];
        $this->previewReady           = false;
        $this->previewSlide           = false;
        $this->canConfirm             = false;
        $this->previewFingerprint     = '';
        $this->proposalGenerated      = false;
    }

    private function refreshAutomaticProposal(): void
    {
        if ($this->event->is_external
            || $this->community_id !== $this->originalCommunityId()
            || ! $this->hasValidDateTimeFields()) {
            return;
        }

        $this->proposeCurrentReservations();
    }

    private function proposeCurrentReservations(): void
    {
        if (! $this->hasValidDateTimeFields()) {
            return;
        }

        $event = $this->event->fresh();

        if (! $event instanceof Event) {
            return;
        }

        [$newStartsAt, $newEndsAt] = $this->newEventInterval();
        $originalStartsAt          = $event->starts_at->toImmutable();
        $originalEndsAt            = $event->ends_at->toImmutable();

        $currentReservations = PlaceReservation::query()
            ->where('event_id', $event->id)
            ->orderBy('id')
            ->get();

        $this->reservations = $currentReservations
            ->map(function ($reservation) use ($newStartsAt, $newEndsAt, $originalStartsAt, $originalEndsAt): array {
                $startOffset = $reservation->reserved_from->getTimestamp() - $originalStartsAt->getTimestamp();
                $endOffset   = $reservation->reserved_to->getTimestamp() - $originalEndsAt->getTimestamp();

                return [
                    'place_id'      => $reservation->place_id,
                    'reserved_from' => $newStartsAt->addSeconds($startOffset)->format('Y-m-d\TH:i'),
                    'reserved_to'   => $newEndsAt->addSeconds($endOffset)->format('Y-m-d\TH:i'),
                    'is_primary'    => $reservation->is_primary,
                ];
            })
            ->values()
            ->all();
    }

    private function originalCommunityId(): ?int
    {
        $primaryReservation = PlaceReservation::query()
            ->where('event_id', $this->event->id)
            ->where('is_primary', true)
            ->first();

        return $primaryReservation === null
            ? null
            : Place::query()->whereKey($primaryReservation->place_id)->value('community_id');
    }

    private function validateForm(): void
    {
        $rules = [
            'date'      => ['required', 'date_format:Y-m-d'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at'   => ['required', 'date_format:H:i'],
        ];

        if ($this->event->is_external) {
            $rules += [
                'external_location_name'    => ['required', 'string', 'max:255'],
                'external_location_address' => ['required', 'string', 'max:255'],
                'external_location_url'     => ['nullable', 'url', 'max:255'],
            ];
        } else {
            $rules += [
                'community_id' => ['required', 'integer', Rule::exists('communities', 'id')],
            ];

            if ($this->community_id !== $this->original_community_id) {
                $rules += [
                    'selected_place_ids'   => ['bail', 'required', 'array', 'min:1'],
                    'selected_place_ids.*' => [
                        'integer',
                        'distinct',
                        Rule::exists('places', 'id')->where('community_id', $this->community_id),
                    ],
                    'primary_place_id' => [
                        'bail',
                        'required',
                        'integer',
                        Rule::in(collect($this->selected_place_ids)->map(fn (int|string $id): int => (int) $id)->all()),
                    ],
                ];
            }
        }

        $this->validate($rules, [
            'ends_at.date_format'                => 'Informe um horário final válido.',
            'community_id.required'              => 'Selecione a comunidade.',
            'selected_place_ids.required'        => 'Selecione ao menos um ambiente para a nova comunidade.',
            'selected_place_ids.min'             => 'Selecione ao menos um ambiente para a nova comunidade.',
            'selected_place_ids.*.distinct'      => 'Cada ambiente pode ser selecionado somente uma vez.',
            'primary_place_id.required'          => 'Selecione o ambiente principal.',
            'primary_place_id.in'                => 'O ambiente principal deve estar entre os ambientes selecionados.',
            'external_location_name.required'    => 'Informe o nome do local externo.',
            'external_location_address.required' => 'Informe o endereço do local externo.',
        ]);

        [$startsAt, $endsAt] = $this->newEventInterval();

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'O horário final deve ser posterior ao horário inicial.',
            ]);
        }
    }

    private function prepareReservationProposal(CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        if ($this->proposalGenerated) {
            return;
        }

        if ($this->community_id === $this->original_community_id) {
            $this->proposeCurrentReservations();
        } else {
            $this->reservations = collect($this->selected_place_ids)
                ->map(fn (int|string $placeId): array => [
                    'place_id'      => (int) $placeId,
                    'reserved_from' => $startsAt->format('Y-m-d\TH:i'),
                    'reserved_to'   => $endsAt->format('Y-m-d\TH:i'),
                    'is_primary'    => (int) $placeId === $this->primary_place_id,
                ])
                ->values()
                ->all();
        }

        $this->proposalGenerated = true;
    }

    private function validateReservationProposal(): void
    {
        Validator::make([
            'reservations' => $this->reservations,
        ], [
            'reservations'                 => ['required', 'array', 'min:1'],
            'reservations.*.place_id'      => ['required', 'integer', 'distinct', Rule::exists('places', 'id')],
            'reservations.*.reserved_from' => ['required', 'date_format:Y-m-d\TH:i'],
            'reservations.*.reserved_to'   => ['required', 'date_format:Y-m-d\TH:i'],
            'reservations.*.is_primary'    => ['required', 'boolean'],
        ], [
            'reservations.required'            => 'Configure ao menos uma reserva para o evento.',
            'reservations.min'                 => 'Configure ao menos uma reserva para o evento.',
            'reservations.*.place_id.distinct' => 'Cada ambiente pode aparecer somente uma vez na proposta.',
        ])->validate();

        foreach ($this->reservations as $index => $reservation) {
            if (CarbonImmutable::parse($reservation['reserved_to'])
                ->lessThanOrEqualTo(CarbonImmutable::parse($reservation['reserved_from']))) {
                throw ValidationException::withMessages([
                    "reservations.{$index}.reserved_to" => 'O fim da reserva deve ser posterior ao início.',
                ]);
            }
        }

        $invalidPlace = collect($this->reservations)->contains(function (array $reservation): bool {
            return ! Place::query()
                ->whereKey((int) $reservation['place_id'])
                ->where('community_id', $this->community_id)
                ->exists();
        });

        if ($invalidPlace) {
            throw ValidationException::withMessages([
                'reservations' => 'Todas as reservas devem pertencer à comunidade selecionada.',
            ]);
        }
    }

    private function validatePrimaryReservation(CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        $primaryReservations = collect($this->reservations)->where('is_primary', true);

        if ($primaryReservations->count() !== 1) {
            $this->addError('reservations', 'A proposta deve conter exatamente uma reserva principal.');

            return;
        }

        $primary = $primaryReservations->first();

        if (CarbonImmutable::parse($primary['reserved_from'])->greaterThan($startsAt)
            || CarbonImmutable::parse($primary['reserved_to'])->lessThan($endsAt)) {
            $this->addError('reservations', 'A reserva principal deve cobrir integralmente o novo horário do evento.');
        }
    }

    /** @return list<array<string, mixed>> */
    private function findConflicts(
        CheckPlaceAvailabilityAction $checkPlaceAvailability,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): array {
        $reservations = collect($this->actionReservations());
        $places       = Place::query()->whereKey($reservations->pluck('place_id'))->get();
        $placeHours   = $reservations->mapWithKeys(function (array $reservation) use ($startsAt, $endsAt): array {
            $reservedFrom = CarbonImmutable::parse($reservation['reserved_from']);
            $reservedTo   = CarbonImmutable::parse($reservation['reserved_to']);

            return [$reservation['place_id'] => [
                'before_hours' => ($startsAt->getTimestamp() - $reservedFrom->getTimestamp()) / 3600,
                'after_hours'  => ($reservedTo->getTimestamp() - $endsAt->getTimestamp()) / 3600,
            ]];
        })->all();

        $conflicts = $checkPlaceAvailability->handle(
            $startsAt,
            $endsAt,
            $places,
            $placeHours,
            PlaceReservation::query()->where('event_id', $this->event->id)->pluck('id')->all(),
        );

        return collect($conflicts)
            ->map(function (array $conflict) use ($reservations): array {
                $reservationIndex = $reservations->search(
                    fn (array $reservation): bool => $reservation['place_id'] === $conflict['requested_place']['id'],
                );

                $conflict['reservation_index'] = $reservationIndex;
                $conflict['is_primary']        = $reservationIndex !== false
                    && (bool) $reservations->get($reservationIndex)['is_primary'];

                return $conflict;
            })
            ->values()
            ->all();
    }

    /** @return list<array{place_id: int, reserved_from: string, reserved_to: string, is_primary: bool}> */
    private function actionReservations(): array
    {
        return collect($this->reservations)
            ->map(fn (array $reservation): array => [
                'place_id'      => (int) $reservation['place_id'],
                'reserved_from' => CarbonImmutable::parse($reservation['reserved_from'])->format('Y-m-d H:i:s'),
                'reserved_to'   => CarbonImmutable::parse($reservation['reserved_to'])->format('Y-m-d H:i:s'),
                'is_primary'    => (bool) $reservation['is_primary'],
            ])
            ->values()
            ->all();
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function newEventInterval(): array
    {
        return [
            CarbonImmutable::parse("{$this->date} {$this->starts_at}:00"),
            CarbonImmutable::parse("{$this->date} {$this->ends_at}:00"),
        ];
    }

    private function hasValidDateTimeFields(): bool
    {
        return Validator::make([
            'date'      => $this->date,
            'starts_at' => $this->starts_at,
            'ends_at'   => $this->ends_at,
        ], [
            'date'      => ['required', 'date_format:Y-m-d'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at'   => ['required', 'date_format:H:i'],
        ])->passes();
    }

    private function currentFingerprint(): string
    {
        return hash('sha256', json_encode([
            $this->date,
            $this->starts_at,
            $this->ends_at,
            $this->community_id,
            $this->reservations,
            $this->selected_place_ids,
            $this->primary_place_id,
            $this->external_location_name,
            $this->external_location_address,
            $this->external_location_url,
        ], JSON_THROW_ON_ERROR));
    }

    private function resetGeneratedProposal(bool $clearReservations = false): void
    {
        $this->proposalGenerated      = false;
        $this->adjusting_reservations = [];

        if ($clearReservations) {
            $this->reservations = [];
        }

        $this->invalidatePreview();
    }

    private function syncSelectedPlacesFromReservations(): void
    {
        $reservations = collect($this->reservations);

        $this->selected_place_ids = $reservations
            ->pluck('place_id')
            ->filter()
            ->map(fn (int|string $placeId): int => (int) $placeId)
            ->values()
            ->all();
        $primary                = $reservations->firstWhere('is_primary', true);
        $this->primary_place_id = $primary === null ? null : (int) $primary['place_id'];
    }

    private function hasConflictForReservation(int $index): bool
    {
        return collect($this->conflicts)->contains(
            fn (array $conflict): bool => $conflict['reservation_index'] === $index,
        );
    }

    private function invalidatePreview(bool $clearConflicts = true): void
    {
        $this->resetValidation();
        $this->previewReady       = false;
        $this->canConfirm         = false;
        $this->confirmationModal  = false;
        $this->previewFingerprint = '';

        if ($clearConflicts) {
            $this->previewSlide = false;
            $this->conflicts    = [];
        }
    }
}
