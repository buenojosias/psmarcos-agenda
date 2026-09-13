<x-guest-layout>
    <x-card shadowless bordered header="Criar conta">
        <form id="register" method="POST" action="{{ route('register.store') }}" class="space-y-4">
            @csrf

            <x-input label="Nome *"
                     name="name"
                     :value="old('name')"
                     required
                     autofocus
                     autocomplete="name" />

            <x-input label="E-mail *"
                     type="email"
                     name="email"
                     :value="old('email')"
                     required
                     autocomplete="username" />

            <x-password label="Senha *"
                        name="password"
                        required
                        rules
                        generator="password_confirmation"
                        autocomplete="new-password" />

            <x-password label="Confirmar Senha *"
                        name="password_confirmation"
                        required
                        autocomplete="new-password" />
        </form>

        <x-slot:footer>
            <div class="flex w-full flex-col gap-y-2">
                <x-button submit form="register" :text="__('Register')" block round />

                <span class="text-center text-sm text-gray-600">
                    Já tem uma conta?
                    <x-link :href="route('login')" text="Entrar!" sm bold />
                </span>
            </div>
        </x-slot:footer>
    </x-card>
</x-guest-layout>
