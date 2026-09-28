<div align="center">

<img src="https://psmarcos.org.br/wp-content/uploads/2025/07/Logo-2-300x84.png" alt="Paróquia São Marcos" width="300">

# Agenda Paroquial

**Gestão colaborativa da agenda de eventos, atividades, comunidades e grupos da Paróquia São Marcos.**

[🌐 Site da Paróquia](https://psmarcos.org.br) · [📅 Acessar Agenda Paroquial](https://agenda.psmarcos.org.br)

</div>

---

## Sobre o projeto

A **Agenda Paroquial** é uma plataforma desenvolvida para auxiliar a Paróquia São Marcos na organização, planejamento e acompanhamento de seus eventos e atividades.

O sistema centraliza as agendas das comunidades, pastorais, movimentos e demais grupos da paróquia, facilitando o planejamento conjunto e reduzindo conflitos de datas, horários e espaços.

A plataforma também estabelece um fluxo organizado entre os responsáveis pelos grupos e a coordenação paroquial, permitindo que eventos sejam cadastrados, analisados, ajustados, aprovados e posteriormente utilizados como referência para organização interna e divulgação.

Além da gestão dos eventos, o sistema concentra informações relacionadas às comunidades, grupos, ambientes e usuários envolvidos na vida pastoral da paróquia.

---

## Objetivos

A Agenda Paroquial busca:

- centralizar o planejamento das atividades paroquiais;
- facilitar a organização anual das agendas dos grupos e comunidades;
- identificar conflitos de datas, horários e ambientes;
- organizar o processo de análise e aprovação dos eventos;
- facilitar a comunicação entre grupos, comunidades e coordenação paroquial;
- disponibilizar informações confiáveis para secretaria, coordenações e Pascom;
- manter um histórico das alterações e decisões relacionadas aos eventos.

---

## Funcionalidades

### 👤 Usuários

Gerenciamento dos usuários que possuem acesso à plataforma.

O cadastro pode ser realizado pelo próprio usuário, que informa também as comunidades das quais participa.

Novos cadastros permanecem inativos até serem analisados e aprovados por um usuário autorizado.

Durante a aprovação são definidos os níveis de acesso do usuário.

---

### ⛪ Comunidades

Cadastro e gerenciamento das comunidades pertencentes à paróquia.

Cada comunidade pode possuir seus próprios:

- grupos e pastorais;
- ambientes;
- eventos;
- informações institucionais.

A página de cada comunidade organiza essas informações em diferentes seções para facilitar a navegação.

---

### 👥 Grupos, pastorais e movimentos

Gerenciamento dos grupos responsáveis pela realização das atividades paroquiais.

Os grupos ficam associados às respectivas comunidades e aos usuários responsáveis por suas atividades.

Essa vinculação também é utilizada para determinar quais eventos cada usuário pode criar, acompanhar e administrar.

---

### 🏠 Ambientes

Cadastro dos espaços que podem ser utilizados para a realização dos eventos.

Os ambientes podem possuir estrutura hierárquica, permitindo representar locais que contêm outros espaços.

Exemplos:

- Igreja;
- Salão Paroquial;
- Sala de reuniões;
- Cozinha;
- Auditório;
- ambientes pertencentes a um espaço maior.

Essas informações são utilizadas durante o planejamento dos eventos e na identificação de conflitos de utilização.

---

### 📅 Eventos

Cadastro e gerenciamento das atividades realizadas pelos grupos e comunidades.

Os eventos podem conter informações como:

- título;
- descrição;
- grupo responsável;
- comunidade;
- data;
- horário inicial e final;
- ambientes utilizados;
- situação do evento;
- informações públicas;
- observações internas.

O evento passa por diferentes estados durante seu ciclo de planejamento e aprovação.

---

### ✅ Fluxo de aprovação

Eventos sujeitos à aprovação podem ser analisados pela coordenação responsável.

Durante esse processo, os responsáveis podem:

- aprovar o evento;
- rejeitar a solicitação;
- solicitar ajustes;
- registrar observações;
- acompanhar alterações realizadas posteriormente.

Alterações relevantes em eventos já aprovados, como mudanças de data, horário ou ambiente, podem exigir uma nova análise da coordenação.

---

### 🗺️ Mapa de disponibilidade

Visualização da ocupação dos ambientes ao longo do dia.

O recurso facilita a identificação de horários disponíveis e conflitos entre eventos, apresentando graficamente a utilização dos espaços.

As barras exibidas no mapa representam visualmente a duração dos eventos, sem limitar os horários a intervalos fixos. Um evento pode, por exemplo, começar às `14:10` e terminar às `18:40`.

A visualização também respeita as permissões do usuário, evitando a exposição indevida de eventos ainda não aprovados pertencentes a outros grupos.

---

### 🚫 Detecção de conflitos

O sistema auxilia na identificação de eventos que pretendem utilizar o mesmo ambiente em períodos incompatíveis.

Isso permite que os conflitos sejam percebidos ainda durante o planejamento, reduzindo ajustes posteriores na agenda paroquial.

---

### 📝 Informações públicas do evento

Os eventos podem possuir informações detalhadas destinadas à divulgação e consulta.

Esses dados são mantidos separadamente das informações administrativas e internas utilizadas durante o processo de organização.

---

### 💬 Notas internas

Usuários autorizados podem registrar observações relacionadas ao processo de organização e aprovação de um evento.

As notas podem ser utilizadas, por exemplo, para:

- solicitar ajustes;
- justificar uma rejeição;
- registrar orientações da coordenação;
- solicitar uma nova análise;
- manter informações internas sobre determinada atividade.

Essas informações não fazem parte da divulgação pública do evento.

---

### 📜 Histórico e auditoria

Alterações relevantes realizadas nos eventos podem ser registradas para consulta posterior.

O histórico permite acompanhar ações como mudanças de status, alterações importantes e decisões tomadas durante o processo de aprovação.

---

### 🔎 Visualização conforme permissões

O acesso às informações dos eventos considera o relacionamento do usuário com os grupos e comunidades.

De forma geral:

- usuários podem acompanhar integralmente os eventos dos grupos aos quais pertencem;
- eventos confirmados podem ser visualizados pelos demais usuários;
- eventos de outros grupos que ainda estejam em processos internos de aprovação possuem acesso restrito.

---

## Níveis de acesso

O sistema utiliza níveis de acesso baseados em **roles**.

Um usuário pode possuir mais de uma função simultaneamente.

### `MEMBER`

Nível padrão de acesso.

Destinado aos membros dos grupos, pastorais, movimentos e comunidades.

Pode acessar os recursos gerais disponíveis aos usuários autenticados e acompanhar as atividades dos grupos aos quais está vinculado.

---

### `SECRETARY`

Usuário responsável por atividades administrativas e de secretaria.

Possui permissões adicionais para auxiliar na gestão da agenda e também pode realizar a aprovação de novos cadastros de usuários.

---

### `CPP`

Representa usuários vinculados à coordenação do **Conselho Pastoral Paroquial**.

Possui permissões ampliadas para acompanhamento e coordenação das atividades paroquiais, incluindo processos de aprovação.

Usuários com essa função podem atribuir a role `CPP` durante a aprovação de novos usuários.

---

### `ADMIN`

Nível administrativo da aplicação.

Possui acesso às funções administrativas gerais do sistema e às configurações que exigem maior nível de permissão.

Somente usuários `ADMIN` podem atribuir a role `ADMIN` a outros usuários.

---

## Aprovação de usuários

Qualquer pessoa pode acessar a página de cadastro da Agenda Paroquial.

Durante o cadastro, o usuário informa as comunidades das quais participa ou às quais deseja ser vinculado.

A conta é criada inicialmente como **inativa**.

Após o cadastro:

1. o usuário recebe a confirmação de criação da conta;
2. a conta permanece aguardando aprovação;
3. um usuário autorizado analisa o cadastro;
4. são definidas as roles do novo usuário;
5. a conta é ativada;
6. o responsável pela aprovação fica registrado no sistema.

Enquanto a conta não estiver ativa, o usuário não poderá acessar normalmente os recursos internos da plataforma.

A role `MEMBER` é considerada a função padrão.

A aprovação de cadastros pode ser realizada por usuários:

- `SECRETARY`;
- `CPP`;
- `ADMIN`.

As funções que podem ser atribuídas dependem do nível de acesso de quem realiza a aprovação.

---

## Estrutura organizacional

De forma simplificada, a organização das informações da plataforma segue a estrutura:

```text
Paróquia
│
├── Comunidades
│   │
│   ├── Grupos / Pastorais / Movimentos
│   │   └── Eventos
│   │
│   └── Ambientes
│
├── Usuários
│
└── Agenda Paroquial
```

Os eventos possuem relacionamento direto com sua comunidade e podem utilizar um ou mais ambientes para sua realização.

---

## Tecnologias

A aplicação é desenvolvida principalmente com:

- **Laravel**
- **Livewire**
- **Alpine.js**
- **Tailwind CSS**
- **TallStackUI**

A arquitetura busca aproveitar os recursos nativos do ecossistema Laravel, mantendo o projeto organizado, sustentável e de fácil manutenção.

---

## Links

| Recurso | Endereço |
|---|---|
| Site oficial da Paróquia São Marcos | https://psmarcos.org.br |
| Agenda Paroquial | https://agenda.psmarcos.org.br |

---

## Paróquia São Marcos

A Agenda Paroquial é uma ferramenta desenvolvida para apoiar a organização pastoral e administrativa da **Paróquia São Marcos**, promovendo uma visão integrada das atividades realizadas por suas comunidades, pastorais, movimentos e grupos.

<div align="center">

[psmarcos.org.br](https://psmarcos.org.br)

</div>