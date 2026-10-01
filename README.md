# Barão Finance

Aplicação web (PWA) de **finanças pessoais** com integração ao **Open Finance** pela API da [Pierre Finance](https://www.pierre.finance). As contas e os cartões bancários são sincronizados automaticamente, e o usuário organiza as transações em categorias, acompanha assinaturas e planeja as despesas futuras.

## Funcionalidades

- Cadastro e login de usuários com sessão
- **Sincronização com Open Finance:** contas, saldos, transações e assinaturas
- Dashboard com a visão geral do saldo e dos gastos
- Transações com **categorias personalizadas**
- Controle de **cartões** e **assinaturas** recorrentes
- **Despesas previstas** para planejar os próximos meses
- Relatórios
- PWA: dá para instalar no celular e funciona com service worker

## Stack

| Camada | Tecnologia |
|---|---|
| Front-end | HTML5, CSS3, JavaScript (vanilla), PWA |
| Back-end | PHP 8 em camadas: controllers → services → repositories → models |
| Banco de dados | PostgreSQL (UUID, triggers) |
| Integração | Pierre Finance API (Open Finance) |

## Arquitetura

```
├── backend/
│   ├── api/              # Endpoints REST (auth + /pierre/*)
│   ├── controllers/
│   ├── services/         # Regras de negócio e cliente da Pierre API
│   ├── repositories/     # Acesso a dados (PDO)
│   ├── models/
│   └── config/           # Banco, CORS e Pierre (via variáveis de ambiente)
├── views/pages/          # Dashboard, transações, contas, relatórios…
├── js/ e css/
├── database/create_tables.sql
├── manifest.webmanifest
└── sw.js
```

## Como rodar localmente

1. PHP 8+ com `pdo_pgsql` e um PostgreSQL 13+.
2. Rode `database/create_tables.sql`.
3. Configure as variáveis listadas em `.env.example` no ambiente.
4. `php -S localhost:8000`

## Autor

**Ryan Morais**
