<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Group;
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

    public function render(): View
    {
        $query         = $this->listingQuery()->where('type', '!=', EventTypeEnum::MASS);
        $user          = auth()->user();
        $type          = is_string($this->type) ? EventTypeEnum::tryFrom($this->type) : null;
        $this->type    = $type !== null && $type !== EventTypeEnum::MASS ? $type->value : '';
        $allowedScopes = $user->isMemberOnly() ? ['all', 'mine'] : ['all', 'mine', 'review'];
        $this->scope   = in_array($this->scope, $allowedScopes, true) ? $this->scope : 'all';
        $groupId       = filter_var($this->group, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $this->group   = $groupId && Group::query()->whereKey($groupId)->exists() ? (string) $groupId : '';

        $query
            ->when($this->scope === 'mine', fn (Builder $query): Builder => $query->fromUserGroups($user))
            ->when($this->scope === 'review', fn (Builder $query): Builder => $query->awaitingReview())
            ->when($this->type !== '', fn (Builder $query): Builder => $query->where('type', $this->type))
            ->when($this->group !== '', fn (Builder $query): Builder => $query->where('group_id', $this->group));

        return view('livewire.events.index', [
            ...$this->listingData($query),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name']),
            'types'  => array_filter(EventTypeEnum::cases(), fn (EventTypeEnum $type): bool => $type !== EventTypeEnum::MASS),
        ]);
    }
}
