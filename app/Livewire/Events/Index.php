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

    #[Url(except: 'all')]
    public mixed $scope = 'all';

    #[Url(except: '')]
    public mixed $group = '';

    #[Url(except: '')]
    public mixed $community = '';

    #[Url(except: false)]
    public bool $externalOnly = false;

    public function render(): View
    {
        $query         = $this->listingQuery();
        $user          = auth()->user();
        $type          = is_string($this->type) ? EventTypeEnum::tryFrom($this->type) : null;
        $this->type    = $type?->value ?? '';
        $allowedScopes = $user->isMemberOnly() ? ['all', 'mine'] : ['all', 'mine', 'review'];
        $this->scope   = in_array($this->scope, $allowedScopes, true) ? $this->scope : 'all';
        $groupId       = filter_var($this->group, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $this->group   = $groupId && Group::query()->whereKey($groupId)->exists() ? (string) $groupId : '';
        $communityId   = is_int($this->community) || is_string($this->community)
            ? filter_var($this->community, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        $this->community = $this->community === 'none'
            ? 'none'
            : ($communityId && Community::query()->whereKey($communityId)->exists() ? (string) $communityId : '');

        $query
            ->when($this->scope === 'mine', fn (Builder $query): Builder => $query->fromUserGroups($user))
            ->when($this->scope === 'review', fn (Builder $query): Builder => $query->awaitingReview())
            ->when($this->type !== '', fn (Builder $query): Builder => $query->where('type', $this->type))
            ->when($this->group !== '', fn (Builder $query): Builder => $query->where('group_id', $this->group))
            ->when($this->community === 'none', fn (Builder $query): Builder => $query->whereNull('community_id'))
            ->when($this->community !== '' && $this->community !== 'none', fn (Builder $query): Builder => $query->where('community_id', $this->community))
            ->when($this->externalOnly, fn (Builder $query): Builder => $query->where('is_external', true));

        return view('livewire.events.index', [
            ...$this->listingData($query),
            'groups'      => Group::query()->orderBy('name')->get(['id', 'name']),
            'communities' => Community::query()->orderBy('name')->get(['id', 'name']),
            'types'       => EventTypeEnum::cases(),
        ]);
    }
}
