<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Group;
use App\Models\Community;
use Illuminate\Http\Request;
use App\Livewire\Users\Index;
use App\Livewire\User\Profile;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Builder;

Route::middleware(['auth'])->group(function () {
    Route::view('/', 'dashboard')->name('dashboard');

    Route::get('/usuarios', Index::class)->can('viewAny', User::class)->name('users.index');

    Route::get('/comunidades', App\Livewire\Communities\Index::class)->can('viewAny', Community::class)->name('communities.index');
    Route::get('/comunidades/{community}', App\Livewire\Communities\Show::class)->can('view', 'community')->name('communities.show');

    Route::get('/espacos/{place}/reservas', App\Livewire\Places\Reservations::class)->name('places.reservations');

    Route::get('/grupos', App\Livewire\Groups\Index::class)->name('groups.index');
    Route::get('/grupos/{group}', App\Livewire\Groups\Show::class)->name('groups.show');
    Route::get('/grupos/{group}/eventos', App\Livewire\Groups\Events::class)->name('groups.events.index');

    Route::get('/eventos/{event}', App\Livewire\Events\Show::class)->name('events.show');

    Route::get('/grupos/{group}/usuarios/buscar', function (Request $request, Group $group) {
        $search = mb_substr(mb_trim($request->string('search')->toString()), 0, 100);

        return User::query()
            ->whereDoesntHave('groups', fn (Builder $query) => $query->whereKey($group->id))
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['label' => $user->name, 'value' => $user->id]);
    })->can('manageUsers', 'group')->name('groups.members.search');

    Route::get('/grupos/usuarios/buscar', function (Request $request) {
        $search = mb_substr(mb_trim($request->string('search')->toString()), 0, 100);

        return User::query()
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['label' => $user->name, 'value' => $user->id]);
    })->can('assignUser', Group::class)->name('groups.users.search');

    Route::get('/user/profile', Profile::class)->name('user.profile');
});
