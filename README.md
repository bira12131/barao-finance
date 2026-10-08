# Barão Finance

Aplicação web (PWA) de **finanças pessoais** com integração ao **Open Finance** pela API da [Pierre Finance](https://www.pierre.finance). As contas e os cartões bancários são sincronizados automaticamente; a partir daí o app organiza as transações, planeja o mês, acompanha dívidas e metas e responde perguntas sobre o dinheiro, inclusive pelo WhatsApp.

## Funcionalidades

**Finanças**
- Sincronização com Open Finance: contas, saldos, transações, cartões e assinaturas
- Dashboard com saldo, gastos e o orçamento do dia ("quanto posso gastar hoje")
- Transações com categorias personalizadas
- Fatura do cartão por período (atual, próxima e anterior), com resumo por categoria
- Assinaturas recorrentes com o logo de cada serviço
- Despesas previstas para planejar os próximos meses
- Empréstimos: parcelas pagas e restantes, data de término, adiantamentos e contratos sugeridos a partir do extrato
- Metas: quanto guardar por mês, aportes e análise de gastos
- Investimentos por conta, com saldo informado manualmente
- Classificação que evita contar duas vezes pagamento de fatura, transferências entre contas próprias e aplicações/resgates
- Relatórios

**Assistente com IA**
- Chat financeiro com a API da Anthropic usando o resumo das finanças do usuário como contexto
- O assistente propõe um plano de quanto separar por mês e a estratégia de quitação das dívidas; o valor é validado no servidor antes de gravar
- Limite diário de mensagens por usuário para controlar o custo
- Robô no WhatsApp (Evolution API): recebe texto e áudio (transcrito pela OpenAI), responde pelo mesmo chat e atende apenas o número autorizado

**Agenda**
- Eventos, tarefas e vencimentos financeiros no mesmo calendário
- Sincronização nos dois sentidos com o Calendário do iPhone (iCloud, CalDAV); os demais calendários entram como leitura
- Importação de arquivos .ics

**Ferramentas**
- Cofre de senhas cifrado no navegador (PBKDF2-SHA256 com 600 mil voltas e AES-256-GCM, com chave de recuperação); o servidor guarda apenas texto cifrado
- Biblioteca de códigos (snippets) com busca, tags e favoritos
- Conversor de consultas SQL para código VB.NET e o inverso

**Geral**
- Cadastro por código de convite (fechado por padrão) e login com sessão
- PWA: instalável no celular, com service worker

## Stack

| Camada | Tecnologia |
|---|---|
| Front-end | HTML5, CSS3, JavaScript (vanilla), PWA |
| Back-end | PHP em camadas: controllers → services → repositories → models |
| Banco de dados | PostgreSQL (UUID, triggers) |
| Integrações | Pierre Finance (Open Finance), API da Anthropic, Evolution API (WhatsApp), OpenAI (áudio), iCloud CalDAV |

## Arquitetura

```
├── backend/
│   ├── api/              # Endpoints REST (auth, agenda, assistente, cofre, metas, empréstimos, /pierre/*...)
│   ├── controllers/
│   ├── services/         # Regras de negócio e clientes das APIs externas
│   ├── repositories/     # Acesso a dados (PDO)
│   ├── models/
│   ├── utils/            # Classificação de lançamentos, ICS, marcas, schema
│   └── config/           # Lê tudo de variáveis de ambiente
├── views/pages/          # Dashboard, transações, contas, cartões, metas, agenda, assistente...
├── js/ e css/
├── database/create_tables.sql
├── manifest.webmanifest
└── sw.js
```

## Como rodar localmente

1. PHP 8+ com `pdo_pgsql` e um PostgreSQL 13+.
2. Rode `database/create_tables.sql`.
3. Defina as variáveis listadas em `.env.example` no ambiente. As integrações opcionais (assistente, WhatsApp, iCloud) ficam desligadas enquanto suas variáveis estiverem vazias.
4. `php -S localhost:8000`

## Segurança

Nenhuma chave ou credencial fica no código: tudo vem de variáveis de ambiente, e a pasta de configuração bloqueia acesso HTTP direto.

## Autor

**Ryan Morais**
