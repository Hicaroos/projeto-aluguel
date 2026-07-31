# Projeto-Aluguel (Ainda não tem nome definido)

⚠️ Esse é um projeto em desenvolvimento. Funcionalidades, telas e até a estrutura do banco de dados ainda estão sendo construídas e podem mudar.

Um SaaS de gestão de aluguel de imóveis. A ideia é permitir que quem administra imóveis alugados, seja um único dono ou uma imobiliária que controle propriedades, proprietários, inquilinos, contratos e pagamentos em um só lugar.

## Como vai funcionar

O sistema é multi-tenant: cada conta (`Account`) tem seus próprios dados, isolados dos de outras contas.

O produto é pensado em duas fases:

### Fase 1 — Dono único (`single_owner`)

Foco atual do desenvolvimento. Uma pessoa que possui um ou mais imóveis próprios se cadastra e passa a gerenciar tudo sozinha: cadastro dos imóveis, inquilinos, contratos, pagamentos etc.

### Fase 2 — Imobiliária (`agency`)

Contas do tipo imobiliária administram imóveis de **múltiplos proprietários**. Isso muda a modelagem: a imobiliária cadastra os proprietários (`Owner`) antes de conseguir cadastrar imóveis em nome deles, e cada imóvel passa a pertencer a um proprietário específico dentro da conta. O banco de dados já foi desenhado pensando nesse cenário (toda propriedade tem um `owner_id`), mesmo enquanto o desenvolvimento está concentrado na Fase 1.

O tipo de conta é escolhido no cadastro (`account_type`) e várias partes da aplicação já se adaptam de acordo com ele — por exemplo, o formulário de imóvel só mostra um seletor de proprietário para contas do tipo `agency` (para `single_owner`, o proprietário é preenchido automaticamente).

## Funcionalidades já construídas

- Cadastro (registro em 2 etapas: dados da conta + dados do proprietário, para contas `single_owner`)
- Autenticação (login, verificação de e-mail, recuperação de senha)
- Configurações de conta e perfil (nome, e-mail, telefone, nome da conta, CPF/CNPJ)
- CRUD de imóveis e Inquilinos: busca, paginação, criação/edição/visualização via modais


## Tecnologias

**Backend**
- PHP 8.5
- Laravel 13
- Inertia.js (Laravel adapter) v3
- Pest v4 + PHPUnit v12 — testes

**Frontend**
- Vue 3
- Inertia.js (Vue adapter) v3
- TypeScript
- Tailwind CSS v4
- Lucide — ícones
- vue-sonner — notificações (toast)
- VueUse — utilitários de composição
- Vite + `@laravel/vite-plugin-wayfinder`
- ESLint + Prettier

**Banco de dados**
- MySQL
- Schema multi-tenant desde o início (`account_id` em todas as tabelas de negócio), preparado para suportar tanto contas de dono único quanto de imobiliária.
