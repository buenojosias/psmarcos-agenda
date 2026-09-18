<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="tallstackui_darkTheme()">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <tallstackui:script />
        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php
        $fullName = auth()->user()->name;
        $firstName = explode(' ', $fullName)[0];
    @endphp
    <body class="font-sans antialiased"
          x-cloak
          x-data="{ name: @js($firstName) }"
          x-on:name-updated.window="name = $event.detail.name"
          x-bind:class="{ 'dark bg-dark-800': darkTheme, 'bg-white': !darkTheme }">
    <x-layout>
        <x-slot:top>
            <x-dialog />
            <x-toast />
        </x-slot:top>
        <x-slot:header>
            <x-layout.header>
                <x-slot:right>
                    <x-dropdown>
                        <x-slot:action>
                            <div>
                                <x-avatar :model="auth()->user()" color="fff" borderless sm x-on:click="show = !show" class="cursor-pointer" />                                    
                            </div>
                        </x-slot:action>
                        <x-slot:header>
                            <x-theme-switch block />
                        </x-slot:header>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown.items :text="__('Profile')" :href="route('user.profile')" />
                            <x-dropdown.items :text="__('Logout')" onclick="event.preventDefault(); this.closest('form').submit();" separator />
                        </form>
                    </x-dropdown>
                </x-slot:right>
            </x-layout.header>
        </x-slot:header>
        <x-slot:menu>
            <x-side-bar smart collapsible>
                <x-slot:brand>
                    <div class="my-4 flex items-center justify-center">
                        <img src="{{ asset('/assets/images/psm.png') }}" class="dark:invert" width="40" height="40" />
                    </div>
                </x-slot:brand>
                <x-slot:brand-collapsed>
                    <div class="my-4 flex items-center justify-center">
                        <img src="{{ asset('/assets/images/psm.png') }}" class="dark:invert" width="20" height="20" />
                    </div>
                </x-slot:brand-collapsed>
                <x-side-bar.item text="Dashboard" icon="home" :current="request()->routeIs('dashboard')" :route="route('dashboard')" />
                <x-side-bar.item text="Grupos" icon="user-group" :current="request()->routeIs('groups.*')" :route="route('groups.index')" />
                @can('viewAny', \App\Models\Event::class)
                    <x-side-bar.item text="Eventos" icon="calendar-days" :current="request()->routeIs('events.index')" :route="route('events.index')" />
                    <x-side-bar.item text="Missas" icon="building-library" :current="request()->routeIs('masses.index')" :route="route('masses.index')" />
                @endcan
                @can('viewAny', \App\Models\Community::class)
                    <x-side-bar.item text="Comunidades" icon="building-library" :current="request()->routeIs('communities.*')" :route="route('communities.index')" />
                @endcan
                @can('viewAny', \App\Models\User::class)
                    <x-side-bar.item text="Usuários" icon="users" :current="request()->routeIs('users.*')" :route="route('users.index')" />
                @endcan
            </x-side-bar>
        </x-slot:menu>
        {{ $slot }}
    </x-layout>
    @livewireScripts
    </body>
</html>
