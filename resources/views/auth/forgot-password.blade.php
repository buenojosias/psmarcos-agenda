<x-guest-layout>
    <x-card shadowless bordered header="Recuperar senha">
        @if (session('status'))
            <div class="mb-4">
                <x-alert :text="session('status')" color="green" />
            </div>
        @endif

        <p class="mb-4 text-sm text-gray-600">
            Esqueceu sua senha? Sem problemas. Basta nos informar seu endereço de e-mail e enviaremos um link para redefinir a senha.
        </p>

        <form id="forgot-password" method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf

            <x-input label="E-mail *"
                     type="email"
                     name="email"
                     :value="old('email')"
                     required
                     autofocus
                     autocomplete="username"/>
        </form>

        <x-slot:footer between>
            <div class="flex flex-col w-full gap-y-2">
                <x-button submit form="forgot-password" :text="__('Email Password Reset Link')" block round/>

                <span class="text-sm text-gray-600 text-center">
                    Lembrou sua senha?
                    <x-link :href="route('login')" text="Voltar para login" sm bold />
                </span>
            </div>
        </x-slot:footer>
    </x-card>
</x-guest-layout>
