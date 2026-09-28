<div class="mx-auto max-w-2xl">
    <x-card shadowless bordered header="Seu acesso à Agenda Paroquial">
        <div class="space-y-4 text-gray-700 dark:text-gray-200">
            @if (auth()->user()->isPending())
                <x-alert title="Cadastro recebido!" text="Sua conta foi criada e está aguardando a aprovação de um responsável." color="yellow" light />
                <p>Assim que seu cadastro for aprovado, você poderá acessar a agenda e participar das atividades das suas comunidades.</p>
            @else
                <x-alert title="Conta inativa" text="Seu acesso está desativado. Procure um responsável para solicitar a reativação." color="yellow" light />
            @endif
            <p>Se precisar de ajuda ou se a aprovação estiver demorando, entre em contato com a secretaria paroquial ou com um administrador.</p>
            <p>Você está conectado como <strong>{{ auth()->user()->name }}</strong>.</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-button text="Sair da conta" submit round />
            </form>
        </div>
    </x-card>
</div>
