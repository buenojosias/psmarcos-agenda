<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;
use Carbon\Carbon;
use App\Models\Place;
use App\Models\EventLog;
use App\Models\Community;
use Illuminate\Support\Str;
use App\Enums\EventTypeEnum;
use App\Enums\EventStatusEnum;

class EventLogFormatter
{
    private const LABELS = [
        'name'                       => 'Nome',
        'type'                       => 'Tipo',
        'status'                     => 'Status',
        'subtitle'                   => 'Complemento',
        'description'                => 'Descrição',
        'target_audience'            => 'Público-alvo',
        'participation_instructions' => 'Orientações para participação',
        'registration_required'      => 'Inscrição necessária',
        'registration_url'           => 'Link de inscrição',
        'registration_deadline'      => 'Prazo de inscrição',
        'participation_cost'         => 'Valor / contribuição',
        'contact_name'               => 'Contato',
        'contact_phone'              => 'Telefone',
        'is_public'                  => 'Evento público',
        'advertisable'               => 'Divulgação solicitada',
        'starts_at'                  => 'Início',
        'ends_at'                    => 'Encerramento',
        'community_id'               => 'Comunidade',
        'external_location_name'     => 'Local',
        'external_location_address'  => 'Endereço',
        'external_location_url'      => 'Referência do local',
    ];

    private const BOOLEAN_FIELDS = ['registration_required', 'is_public', 'advertisable'];

    private const DATETIME_FIELDS = ['starts_at', 'ends_at'];

    /**
     * @param  iterable<EventLog>  $logs
     * @return array<int, array{fields: list<array{label: string, old: string, new: string}>, reservations: list<array{place: string, kind: string, old: ?string, new: ?string}>}>
     */
    public function formatMany(iterable $logs): array
    {
        $communityIds = [];
        $placeIds     = [];

        foreach ($logs as $log) {
            $changes = $log->changes ?? [];

            if (isset($changes['community_id']) && is_array($changes['community_id'])) {
                foreach (['old', 'new'] as $side) {
                    $id = $this->identifier($changes['community_id'][$side] ?? null);

                    if ($id !== null) {
                        $communityIds[$id] = $id;
                    }
                }
            }

            if (isset($changes['reservations']) && is_array($changes['reservations'])) {
                foreach (['old', 'new'] as $side) {
                    foreach ($this->reservationList($changes['reservations'][$side] ?? null) as $reservation) {
                        $id = $this->identifier($reservation['place_id'] ?? null);

                        if ($id !== null) {
                            $placeIds[$id] = $id;
                        }
                    }
                }
            }
        }

        $communities = $communityIds === []
            ? []
            : Community::query()->whereKey(array_values($communityIds))->pluck('name', 'id')->all();
        $places = $placeIds === []
            ? []
            : Place::query()->with('main')->whereKey(array_values($placeIds))->get()->keyBy('id')->all();

        $formatted = [];

        foreach ($logs as $log) {
            $fields       = [];
            $reservations = [];

            foreach ($log->changes ?? [] as $name => $change) {
                if (! is_array($change)) {
                    continue;
                }

                if ($name === 'reservations') {
                    $reservations = $this->formatReservations($change, $places);

                    continue;
                }

                $fields[] = [
                    'label' => self::LABELS[$name] ?? Str::headline(Str::beforeLast((string) $name, '_id')),
                    'old'   => $this->formatValue((string) $name, $change['old'] ?? null, $communities),
                    'new'   => $this->formatValue((string) $name, $change['new'] ?? null, $communities),
                ];
            }

            $formatted[$log->id] = ['fields' => $fields, 'reservations' => $reservations];
        }

        return $formatted;
    }

    /** @param  array<int, string>  $communities */
    private function formatValue(string $name, mixed $value, array $communities): string
    {
        if ($value === null || $value === '') {
            return 'Não informado';
        }

        if ($name === 'community_id') {
            $id = $this->identifier($value);

            return $id === null ? 'Comunidade não disponível' : ($communities[$id] ?? 'Comunidade não disponível');
        }

        if ($name === 'type') {
            return $value instanceof EventTypeEnum
                ? $value->label()
                : (EventTypeEnum::tryFrom((string) $value)?->label() ?? 'Não informado');
        }

        if ($name === 'status') {
            return $value instanceof EventStatusEnum
                ? $value->label()
                : (EventStatusEnum::tryFrom((string) $value)?->label() ?? 'Não informado');
        }

        if (str_ends_with($name, '_id')) {
            return 'Não informado';
        }

        if (in_array($name, self::BOOLEAN_FIELDS, true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Sim' : 'Não';
        }

        if (in_array($name, self::DATETIME_FIELDS, true)) {
            return $this->formatDate($value, 'd/m/Y H:i');
        }

        if ($name === 'registration_deadline') {
            return $this->formatDate($value, 'd/m/Y');
        }

        return is_scalar($value) ? (string) $value : 'Não informado';
    }

    private function formatDate(mixed $value, string $format): string
    {
        if ($value === null || $value === '') {
            return 'Não informado';
        }

        try {
            return Carbon::parse($value)->format($format);
        } catch (Throwable) {
            return 'Não informado';
        }
    }

    private function identifier(mixed $value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        return $id !== false && $id > 0 ? $id : null;
    }

    /** @return list<array<string, mixed>> */
    private function reservationList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_array(...)));
    }

    /**
     * @param  array<string, mixed>  $change
     * @param  array<int, Place>  $places
     * @return list<array{place: string, kind: string, old: ?string, new: ?string}>
     */
    private function formatReservations(array $change, array $places): array
    {
        $old    = $this->reservationList($change['old'] ?? null);
        $new    = $this->reservationList($change['new'] ?? null);
        $result = [];

        foreach ($old as $oldIndex => $oldReservation) {
            foreach ($new as $newIndex => $newReservation) {
                if ($this->sameReservation($oldReservation, $newReservation)) {
                    unset($old[$oldIndex], $new[$newIndex]);

                    continue 2;
                }
            }
        }

        foreach ($old as $oldIndex => $oldReservation) {
            $placeId = $this->identifier($oldReservation['place_id'] ?? null);

            if ($placeId === null) {
                continue;
            }

            foreach ($new as $newIndex => $newReservation) {
                if ($placeId !== $this->identifier($newReservation['place_id'] ?? null)) {
                    continue;
                }

                $result[] = [
                    'place' => $this->placeName($placeId, $places),
                    'kind'  => 'Alterada',
                    'old'   => $this->reservationPeriod($oldReservation),
                    'new'   => $this->reservationPeriod($newReservation),
                ];
                unset($old[$oldIndex], $new[$newIndex]);

                continue 2;
            }
        }

        foreach ($old as $reservation) {
            $result[] = [
                'place' => $this->placeName($this->identifier($reservation['place_id'] ?? null), $places),
                'kind'  => 'Removida',
                'old'   => $this->reservationPeriod($reservation),
                'new'   => null,
            ];
        }

        foreach ($new as $reservation) {
            $result[] = [
                'place' => $this->placeName($this->identifier($reservation['place_id'] ?? null), $places),
                'kind'  => 'Adicionada',
                'old'   => null,
                'new'   => $this->reservationPeriod($reservation),
            ];
        }

        return $result;
    }

    /** @param  array<string, mixed>  $old  @param  array<string, mixed>  $new */
    private function sameReservation(array $old, array $new): bool
    {
        return $this->identifier($old['place_id'] ?? null) === $this->identifier($new['place_id'] ?? null)
            && ($old['reserved_from'] ?? null) === ($new['reserved_from'] ?? null)
            && ($old['reserved_to'] ?? null) === ($new['reserved_to'] ?? null)
            && (bool) ($old['is_primary'] ?? false) === (bool) ($new['is_primary'] ?? false);
    }

    /** @param  array<int, Place>  $places */
    private function placeName(?int $id, array $places): string
    {
        $place = $id === null ? null : ($places[$id] ?? null);

        if ($place === null) {
            return 'Ambiente não disponível';
        }

        return $place->main ? $place->main->name.': '.$place->name : $place->name;
    }

    /** @param  array<string, mixed>  $reservation */
    private function reservationPeriod(array $reservation): string
    {
        $from = $this->formatDate($reservation['reserved_from'] ?? null, 'd/m/Y H:i');
        $to   = $this->formatDate($reservation['reserved_to'] ?? null, 'd/m/Y H:i');

        if ($from !== 'Não informado' && $to !== 'Não informado' && mb_substr($from, 0, 10) === mb_substr($to, 0, 10)) {
            $to = mb_substr($to, 11);
        }

        $period = $from.' — '.$to;

        return ($reservation['is_primary'] ?? false) ? $period.' · Principal' : $period;
    }
}
