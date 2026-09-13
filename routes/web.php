<?php

declare(strict_types=1);

use App\Models\User;
use App\Livewire\Users\Index;
use App\Livewire\User\Profile;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');

Route::middleware(['auth'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::get('/usuarios', Index::class)->can('viewAny', User::class)->name('users.index');

    Route::get('/user/profile', Profile::class)->name('user.profile');
});
