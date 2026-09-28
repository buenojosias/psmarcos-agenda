<nav aria-label="Acessos rápidos">
    <x-card header="Acessos rápidos" bordered>
        <div class="flex flex-col gap-2">
            @foreach ($actions as $action)
                <x-button :text="$action['label']" :icon="$action['icon']" :href="$action['url']" color="gray" flat block class="justify-start" />
            @endforeach
        </div>
    </x-card>
</nav>
