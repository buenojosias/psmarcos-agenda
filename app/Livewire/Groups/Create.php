<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Models\Group;
use Livewire\Component;
use App\Models\Community;
use Illuminate\Support\Str;
use App\Enums\GroupTypeEnum;
use App\Livewire\Traits\Alert;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class Create extends Component
{
    use Alert;

    public string $name = '';

    public string $type = '';

    public ?int $communityId = null;

    public ?string $description = null;

    public ?int $userId = null;

    public bool $isLeader = false;

    public bool $modal = false;

    public function render(): View
    {
        return view('livewire.groups.create', [
            'canAssignUser' => Gate::allows('assignUser', Group::class),
            'types'         => array_map(fn (GroupTypeEnum $type): array => [
                'label' => $type->label(),
                'value' => $type->value,
            ], GroupTypeEnum::cases()),
            'communities' => Community::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Community $community): array => [
                    'label' => $community->name,
                    'value' => $community->id,
                ])->all(),
        ]);
    }

    public function save(): void
    {
        Gate::authorize('create', Group::class);

        $canAssignUser = Gate::allows('assignUser', Group::class);
        abort_if(! $canAssignUser && $this->userId !== null, 403);

        $slug = Str::slug($this->name);

        $this->validate([
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', Rule::enum(GroupTypeEnum::class)],
            'communityId' => ['nullable', 'integer', Rule::exists('communities', 'id')],
            'description' => ['nullable', 'string'],
            'userId'      => ['nullable', 'integer', Rule::exists('users', 'id')],
            'isLeader'    => ['boolean'],
        ]);

        if ($slug === '' || Group::withTrashed()->where('slug', $slug)->exists()) {
            $this->addError('name', 'Já existe um grupo com esse nome ou o nome é inválido.');

            return;
        }

        $group = Group::create([
            'name'         => $this->name,
            'type'         => $this->type,
            'community_id' => $this->communityId,
            'description'  => $this->description,
            'slug'         => $slug,
        ]);

        $relatedUserId = $canAssignUser ? $this->userId : Auth::id();

        if ($relatedUserId !== null) {
            $group->users()->attach($relatedUserId, ['is_coordinator' => $this->isLeader]);
        }

        $this->dispatch('created');
        $this->reset();
        $this->success('Grupo criado com sucesso.', 'Concluído');
    }
}
