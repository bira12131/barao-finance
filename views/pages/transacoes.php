<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Transações</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <script src="../../js/app-runtime.js"></script>
  <script src="../../js/finance-rules.js?v=20261003c"></script>
  <script src="../../js/pierre-sync.js"></script>
  <style>
    .filters {
      display: flex;
      gap: 0.625rem;
      flex-wrap: wrap;
      margin-bottom: 0;
      align-items: center;
    }
    .tx-summary {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 0.7rem;
      margin-bottom: 0.9rem;
    }
    .tx-mini-card {
      background: transparent;
      border: none;
      padding: 0.72rem 0.82rem;
      box-shadow: none;
    }
    .tx-mini-label {
      font-size: 0.66rem;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.06em;
      font-weight: 700;
      margin-bottom: 0.22rem;
    }
    .tx-mini-value {
      font-size: 0.98rem;
      font-weight: 800;
      color: var(--text-heading);
      line-height: 1.2;
    }
    .filter-select, .filter-input {
      height: 36px;
      padding: 0 0.75rem;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      background: var(--bg-input);
      color: var(--text-body);
      font-size: 0.83rem;
      font-family: inherit;
      outline: none;
      transition: border-color 0.15s;
    }
    .filter-select:focus, .filter-input:focus { border-color: var(--primary); }
    .filter-input { width: 200px; }
    .btn-filter {
      height: 36px;
      padding: 0 1rem;
      border-radius: var(--radius-sm);
      font-size: 0.83rem;
      font-weight: 600;
      cursor: pointer;
      border: 1.5px solid var(--primary);
      background: var(--primary);
      color: #fff;
      transition: opacity 0.15s;
    }
    .btn-filter:hover { opacity: 0.88; }
    .tx-valor.CREDIT { color: #16a34a; font-weight: 600; }
    .tx-valor.DEBIT  { color: var(--danger); font-weight: 600; }
    .tx-cat-select {
      width: 156px;
      max-width: 156px;
      height: 32px;
      border: 1px solid color-mix(in srgb, var(--page-accent) 24%, rgba(80, 98, 128, 0.9));
      border-radius: 9px;
      padding: 0 1.55rem 0 0.55rem;
      background:
        linear-gradient(45deg, transparent 50%, color-mix(in srgb, var(--page-accent) 74%, #cddcff) 50%),
        linear-gradient(135deg, color-mix(in srgb, var(--page-accent) 74%, #cddcff) 50%, transparent 50%),
        linear-gradient(165deg, #1a2437, #121b2b);
      background-position:
        calc(100% - 12px) 13px,
        calc(100% - 7px) 13px,
        0 0;
      background-size:
        5px 5px,
        5px 5px,
        100% 100%;
      background-repeat: no-repeat;
      color: #d7e2f8;
      font-size: 0.74rem;
      font-weight: 600;
      outline: none;
      appearance: none;
    }
    .tx-cat-select:focus {
      border-color: var(--page-accent);
      box-shadow: 0 0 0 2px color-mix(in srgb, var(--page-accent) 28%, transparent);
    }
    .badge-tipo {
      display: inline-flex;
      align-items: center;
      padding: 2px 8px;
      border-radius: 20px;
      font-size: 0.72rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .badge-tipo.CREDIT { background: #dcfce7; color: #16a34a; }
    .badge-tipo.DEBIT  { background: #fee2e2; color: #dc2626; }
    .empty-tx {
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      padding: 3.5rem 1rem; gap: 0.625rem;
      color: var(--text-muted);
    }
    .empty-tx svg { width: 48px; height: 48px; opacity: 0.35; margin-bottom: 0.25rem; }
    .empty-tx p { font-size: 0.875rem; margin: 0; }
    .empty-tx strong { color: var(--text-heading); font-size: 0.95rem; }
    .loading-state { display: flex; align-items: center; gap: 8px; color: var(--text-muted); font-size: 0.875rem; padding: 2rem; }
    .spinner { width: 18px; height: 18px; border: 2px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.7s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .tx-count { font-size: 0.8rem; color: var(--text-muted); margin-left: auto; }
    .hint-link {
      font-size: 0.77rem;
      color: var(--text-muted);
      margin-left: auto;
    }
    @media (max-width: 760px) {
      .filters {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: stretch;
        gap: 0.5rem;
      }
      .filters > * {
        min-width: 0;
      }
      .filter-input[type="date"] {
        height: 34px !important;
        min-height: 34px !important;
        padding: 0 1.45rem 0 0.7rem !important;
        font-size: 13px !important;
        line-height: 34px !important;
        border-radius: 8px;
        min-width: 0;
        width: min(100%, 126px);
        max-width: 126px;
        justify-self: center;
        text-align: center;
        box-sizing: border-box;
        overflow: hidden;
        position: relative;
      }
      .filter-input[type="date"]::-webkit-datetime-edit {
        padding: 0;
        font-size: 13px;
        line-height: 34px;
        display: flex;
        justify-content: center;
        align-items: center;
        width: 100%;
      }
      .filter-input[type="date"]::-webkit-datetime-edit-fields-wrapper {
        padding: 0;
        display: inline-flex;
        justify-content: center;
        width: 100%;
      }
      .filter-input[type="date"]::-webkit-date-and-time-value {
        text-align: center;
        min-height: 34px;
        width: 100%;
      }
      .filter-input[type="date"]::-webkit-calendar-picker-indicator {
        transform: scale(0.82);
        opacity: 0.88;
        margin-left: 0;
        position: absolute;
        right: 6px;
      }
      .filter-input,
      .filter-select,
      .btn-filter {
        width: 100%;
      }
      .filter-select,
      .btn-filter {
        height: 34px !important;
        min-height: 34px !important;
      }
      .filter-select {
        font-size: 13px !important;
        padding: 0 0.58rem !important;
        border-radius: 8px;
      }
      .btn-filter {
        font-size: 13px !important;
        padding: 0 0.75rem !important;
        border-radius: 8px;
      }
      .filter-select,
      .btn-filter,
      .tx-count,
      .hint-link {
        grid-column: 1 / -1;
      }
      .tx-count,
      .hint-link {
        margin-left: 0;
        width: 100%;
        font-size: 11px;
        line-height: 1.2;
      }
      .tx-cat-select {
        max-width: 100%;
        width: 100%;
      }
    }

    @media (max-width: 390px) {
      .filters {
        grid-template-columns: 1fr;
      }
      .filter-input[type="date"] {
        width: 100%;
        max-width: 100%;
        justify-self: stretch;
      }
      .filter-select,
      .btn-filter,
      .tx-count,
      .hint-link {
        grid-column: auto;
      }
    }
  </style>
</head>
<body class="inner-page tone-transacoes">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Transações</h1>
      <p>Histórico de entradas e saídas</p>
    </div>
    <div class="page-header-actions">
      <button class="btn-sync secondary" id="btn-sync-tx" onclick="PierreSync.atualizar(carregarTransacoes, this)">↻ Sincronizar</button>
    </div>
  </div>

  <div class="card panel-filters">
    <div class="filters">
      <input class="filter-input" type="date" id="f-inicio" title="Data inicial" />
      <input class="filter-input" type="date" id="f-fim" title="Data final" />
      <select class="filter-select" id="f-tipo">
        <option value="">Todos os tipos</option>
        <option value="BANK">Conta bancária</option>
        <option value="CREDIT">Cartão de crédito</option>
      </select>
      <button class="btn-filter" onclick="carregarTransacoes()">Filtrar</button>
      <span class="tx-count" id="tx-count"></span>
      <span class="hint-link">Use o menu Categorias para cadastrar e selecionar.</span>
    </div>
  </div>

  <div class="summary-panel">
    <div class="tx-summary summary-grid">
      <div class="tx-mini-card summary-item">
        <div class="tx-mini-label summary-label">Receita total</div>
        <div class="tx-mini-value summary-value" id="sum-receita">R$ —</div>
      </div>
      <div class="tx-mini-card summary-item">
        <div class="tx-mini-label summary-label">Despesa total</div>
        <div class="tx-mini-value summary-value" id="sum-despesa">R$ —</div>
      </div>
      <div class="tx-mini-card summary-item">
        <div class="tx-mini-label summary-label">Saldo período</div>
        <div class="tx-mini-value summary-value" id="sum-liquido">R$ —</div>
      </div>
    </div>
  </div>

  <div id="loading" class="card loading-state">
    <div class="spinner"></div>
    Carregando transações...
  </div>

  <div class="card panel-card" id="tx-card" style="display:none;">
    <table class="data-table">
      <thead>
        <tr>
          <th>Data</th>
          <th>Descrição</th>
          <th>Categoria</th>
          <th>Conta</th>
          <th>Tipo</th>
          <th style="text-align:right">Valor</th>
        </tr>
      </thead>
      <tbody id="tx-body"></tbody>
    </table>
    <div class="empty-tx" id="tx-empty" style="display:none">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="1" x2="12" y2="23"/>
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
      </svg>
      <strong>Nenhuma transação encontrada</strong>
      <p>Ajuste os filtros ou aguarde a sincronização dos dados.</p>
    </div>
  </div>

  <div class="card panel-card empty-tx" id="tx-erro" style="display:none">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <strong>Erro ao carregar transações</strong>
    <p id="tx-erro-msg"></p>
  </div>

  <script>
    let categoriasDisponiveis = [];

    function formatBRL(v) {
      return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Math.abs(v));
    }
    function formatData(d) {
      if (!d) return '—';
      const dt = new Date(d);
      if (!Number.isNaN(dt.getTime())) {
        return dt.toLocaleDateString('pt-BR');
      }
      const [y, m, dia] = String(d).split('-');
      if (!y || !m || !dia) return '—';
      return `${dia}/${m}/${y}`;
    }
    function _apiBase() {
      const { origin, pathname } = window.location;
      const root = pathname
        .replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '')
        .replace(/\/[^/]*\.(?:html|php)$/, '');
      return origin + root + '/backend/api';
    }

    async function carregarCategorias() {
      try {
        const res = await fetch(_apiBase() + '/pierre/categorias.php', { cache: 'no-store' });
        const json = await res.json();
        if (json.success) {
          categoriasDisponiveis = json.data.categorias || [];
        }
      } catch (_) {}
    }

    function opcoesCategoriaSelecionavel(categoriaAtual) {
      const atual = categoriaAtual || 'Sem categoria';
      const todas = Array.from(new Set([atual, ...categoriasDisponiveis].filter(Boolean)));
      return todas
        .sort((a, b) => a.localeCompare(b, 'pt-BR'))
        .map((nome) => `<option value="${nome}" ${nome === categoriaAtual ? 'selected' : ''}>${nome}</option>`)
        .join('');
    }

    function atualizarResumo(transacoes) {
      // Ignora pagamento de fatura, aplicações e transferências entre contas próprias.
      const { receitas: receita, despesas: despesa } = BFFinance.totais(transacoes);

      const liquido = receita - despesa;
      document.getElementById('sum-receita').textContent = formatBRL(receita);
      document.getElementById('sum-despesa').textContent = formatBRL(despesa);
      const liquidoEl = document.getElementById('sum-liquido');
      liquidoEl.textContent = formatBRL(Math.abs(liquido));
      liquidoEl.style.color = liquido >= 0 ? '#16a34a' : 'var(--danger)';
    }

    async function atualizarCategoria(transactionId, categoria) {
      try {
        const res = await fetch(_apiBase() + '/pierre/categorias.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ transactionId, categoria }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Erro ao atualizar');
      } catch (e) {
        await BFApp.modalAlert('Não foi possível atualizar a categoria.', 'Erro');
      }
    }

    function toDateInputValueLocal(date) {
      const y = date.getFullYear();
      const m = String(date.getMonth() + 1).padStart(2, '0');
      const d = String(date.getDate()).padStart(2, '0');
      return `${y}-${m}-${d}`;
    }

    // Define período padrão: mês atual
    (function() {
      const hoje  = new Date();
      const inicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
      document.getElementById('f-fim').value    = toDateInputValueLocal(hoje);
      document.getElementById('f-inicio').value = toDateInputValueLocal(inicio);
    })();

    async function carregarTransacoes() {
      document.getElementById('loading').style.display   = 'flex';
      document.getElementById('tx-card').style.display   = 'none';
      document.getElementById('tx-erro').style.display   = 'none';
      document.getElementById('tx-count').textContent    = '';

      const params = new URLSearchParams();
      const inicio = document.getElementById('f-inicio').value;
      const fim    = document.getElementById('f-fim').value;
      const tipo   = document.getElementById('f-tipo').value;
      if (inicio) params.set('startDate',   inicio);
      if (fim)    params.set('endDate',     fim);
      if (tipo)   params.set('accountType', tipo);

      try {
        const res  = await fetch(_apiBase() + '/pierre/transacoes.php?' + params.toString(), { cache: 'no-store' });
        const json = await res.json();
        document.getElementById('loading').style.display = 'none';

        if (!json.success) {
          document.getElementById('tx-erro-msg').textContent = json.message || 'Erro desconhecido.';
          document.getElementById('tx-erro').style.display = 'flex';
          return;
        }

        const transacoes = BFFinance.ordenarRecentes(json.data.transacoes || []);
        atualizarResumo(transacoes);
        document.getElementById('tx-count').textContent = transacoes.length + ' transação(ões)';
        document.getElementById('tx-card').style.display = 'block';

        const tbody = document.getElementById('tx-body');
        tbody.innerHTML = '';

        if (transacoes.length === 0) {
          document.getElementById('tx-empty').style.display = 'flex';
          return;
        }
        document.getElementById('tx-empty').style.display = 'none';

        transacoes.forEach(tx => {
          const tipo  = tx.type || (tx.amount >= 0 ? 'CREDIT' : 'DEBIT');
          const sinal = tipo === 'CREDIT' ? '+' : '-';
          const label = tipo === 'CREDIT' ? 'Receita' : 'Despesa';
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td data-label="Data">${BFFinance.formatarDataHora(tx.date)}</td>
            <td data-label="Descrição">${tx.description || '—'}</td>
            <td data-label="Categoria">
              <select class="tx-cat-select" data-tx-id="${tx.id || ''}">
                ${opcoesCategoriaSelecionavel(tx.category || '')}
              </select>
            </td>
            <td data-label="Conta">${tx.account_name || '—'}</td>
            <td data-label="Tipo"><span class="badge-tipo ${tipo}">${label}</span></td>
            <td data-label="Valor" class="tx-valor ${tipo}" style="text-align:right">${sinal} ${formatBRL(tx.amount)}</td>
          `;
          tbody.appendChild(tr);
        });

        tbody.querySelectorAll('.tx-cat-select').forEach((el) => {
          el.addEventListener('change', async (ev) => {
            const transactionId = ev.target.dataset.txId;
            const categoria = ev.target.value;
            if (!transactionId || !categoria) return;
            await atualizarCategoria(transactionId, categoria);
          });
        });
      } catch (e) {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('tx-erro-msg').textContent = 'Erro de conexão. Tente novamente.';
        document.getElementById('tx-erro').style.display = 'flex';
      }
    }

    (async function init() {
      await carregarCategorias();
      await carregarTransacoes();
    })();
  </script>

</body>
</html>
