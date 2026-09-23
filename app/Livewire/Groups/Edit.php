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
use Illuminate\Support\Facades\Gate;

class Edit extends Component
{
    use Alert;

    public string $name = '';

    public ?string $abbreviation = null;

    public string $type = '';

    public ?int $communityId = null;

    public ?string $description = null;

    public Group $group;

    public bool $modal = false;

    public function render(): View
    {
        return view('livewire.groups.edit', [
            'types' => array_map(fn (GroupTypeEnum $type): array => [
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

    public function open(): void
    {
        Gate::authorize('update', $this->group);

        $this->resetValidation();
        $this->group->refresh();
        $this->name         = $this->group->name;
        $this->abbreviation = $this->group->abbreviation;
        $this->type         = $this->group->type->value;
        $this->communityId  = $this->group->community_id;
        $this->description  = $this->group->description;
        $this->modal        = true;
    }

    public function save(): void
    {
        Gate::authorize('update', $this->group);

        $slug = Str::slug($this->name);

        $this->validate([
            'name'         => ['required', 'string', 'max:255'],
            'abbreviation' => ['nullable', 'string', 'max:30'],
            'type'         => ['required', Rule::enum(GroupTypeEnum::class)],
            'communityId'  => ['nullable', 'integer', Rule::exists('communities', 'id')],
            'description'  => ['nullable', 'string'],
        ]);

        if ($slug === '' || Group::withTrashed()->where('slug', $slug)->whereKeyNot($this->group->id)->exists()) {
            $this->addError('name', 'Já existe um grupo com esse nome ou o nome é inválido.');

            return;
        }

        $this->group->update([
            'name'         => $this->name,
            'abbreviation' => $this->abbreviation,
            'type'         => $this->type,
            'community_id' => $this->communityId,
            'description'  => $this->description,
            'slug'         => $slug,
        ]);

        $this->modal = false;
        $this->dispatch('group-updated');
        $this->success('Grupo atualizado com sucesso.', 'Concluído');
    }
}
