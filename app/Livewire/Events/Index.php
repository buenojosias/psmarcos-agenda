<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Group;
use App\Models\Community;
use App\Enums\EventTypeEnum;
use Livewire\Attributes\Url;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class Index extends Listing
{
    #[Url(except: '')]
    public mixed $type = '';

    #[Url(except: false)]
    public bool $onlyMyGroups = false;

    #[Url(except: '')]
    public mixed $group = '';

    #[Url(except: '')]
    public mixed $community = '';

    #[Url(except: false)]
    public bool $externalOnly = false;

    public function render(): View
    {
        $query       = $this->listingQuery();
        $user        = auth()->user();
        $type        = is_string($this->type) ? EventTypeEnum::tryFrom($this->type) : null;
        $this->type  = $type === null ? '' : $type->value;
        $groupId     = filter_var($this->group, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $this->group = $groupId && Group::query()->whereKey($groupId)->exists() ? (string) $groupId : '';
        $communityId = is_int($this->community) || is_string($this->community)
            ? filter_var($this->community, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        $this->community = $this->community === 'none'
            ? 'none'
            : ($communityId && Community::query()->whereKey($communityId)->exists() ? (string) $communityId : '');

        $query
            ->when($this->onlyMyGroups, fn (Builder $query): Builder => $query->fromUserGroups($user))
            ->when($this->type !== '', fn (Builder $query): Builder => $query->where('type', $this->type))
            ->when($this->group !== '', fn (Builder $query): Builder => $query->where('group_id', $this->group))
            ->when($this->community === 'none', fn (Builder $query): Builder => $query->whereNull('community_id'))
            ->when($this->community !== '' && $this->community !== 'none', fn (Builder $query): Builder => $query->where('community_id', $this->community))
            ->when($this->externalOnly, fn (Builder $query): Builder => $query->where('is_external', true));

        return view('livewire.events.index', [
            ...$this->listingData($query),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name', 'abbreviation'])
                ->map(fn (Group $group): array => [
                    'label' => $group->abbreviation ? $group->name.' ('.$group->abbreviation.')' : $group->name,
                    'value' => $group->id,
                ]),
            'communities' => Community::query()->orderBy('id')->get(['id', 'name']),
            'types'       => EventTypeEnum::cases(),
        ]);
    }
}
