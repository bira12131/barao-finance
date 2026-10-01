<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Seleção de Categorias</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <script src="../../js/app-runtime.js"></script>
  <style>
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 0.7rem;
      margin-bottom: 1rem;
    }
    .stat {
      background: transparent;
      border: none;
      box-shadow: none;
      padding: 0.72rem 0.82rem;
    }
    .stat-label {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-muted);
      font-weight: 700;
      margin-bottom: 0.24rem;
    }
    .stat-value {
      font-size: 1.06rem;
      color: var(--text-heading);
      font-weight: 700;
    }
    .info {
      font-size: 0.82rem;
      color: var(--text-muted);
      margin-bottom: 0.8rem;
    }
    .cat-name {
      font-weight: 600;
      color: var(--text-heading);
      letter-spacing: 0.01em;
      display: flex;
      align-items: center;
      gap: 0.45rem;
    }
    .cat-edit-inline {
      padding: 0.12rem 0.48rem;
      cursor: pointer;
    }
    .cat-total {
      font-weight: 700;
      color: var(--danger);
      text-align: right;
    }
    .cat-count {
      color: var(--text-muted);
      text-align: center;
      width: 120px;
    }
    .empty {
      padding: 1rem;
      color: var(--text-muted);
      font-size: 0.85rem;
    }
    .actions {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 0.9rem;
      gap: 0.45rem;
    }
    .data-table tbody tr:hover {
      background: rgba(53, 93, 170, 0.12);
    }
    .btn {
      height: 34px;
      padding: 0 0.85rem;
      border-radius: var(--radius-sm);
      border: 1.5px solid var(--primary);
      background: var(--primary);
      color: #fff;
      font-size: 0.8rem;
      font-weight: 600;
      cursor: pointer;
    }
    .btn.outline {
      background: var(--bg-input);
      color: var(--text-body);
      border-color: var(--border);
    }
    .btn:hover { opacity: 0.9; }
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(18, 28, 44, 0.45);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 40;
      padding: 1rem;
    }
    .modal-card {
      width: 100%;
      max-width: 430px;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 14px;
      box-shadow: 0 18px 36px rgba(18, 28, 44, 0.26);
      padding: 1rem;
    }
    .modal-title {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--text-heading);
      margin-bottom: 0.7rem;
    }
    .modal-input {
      width: 100%;
      height: 38px;
      padding: 0 0.72rem;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      background: var(--bg-input);
      font-size: 0.84rem;
      color: var(--text-body);
      outline: none;
      margin-bottom: 0.6rem;
    }
    .modal-input:focus { border-color: var(--primary); }
    .modal-msg {
      font-size: 0.78rem;
      color: var(--text-muted);
      margin-bottom: 0.8rem;
    }
    .row-actions {
      display: flex;
      justify-content: flex-end;
      gap: 0.45rem;
    }
    @media (min-width: 1024px) {
      .inner-page {
        max-width: 1320px;
      }
      .page-header {
        padding: 1.05rem 1.15rem;
      }
      .summary-panel {
        padding: 0.95rem;
      }
      .card {
        padding: 0;
      }
    }
    @media (max-width: 760px) {
      .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.44rem;
      }
      .stats-grid > .summary-item:last-child:nth-child(odd) {
        grid-column: 1 / -1;
      }
    }
  </style>
</head>
<body class="inner-page tone-categorias">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Seleção de categorias</h1>
      <p>Despesas agrupadas por categoria com total movimentado</p>
    </div>
  </div>

  <div class="summary-panel">
    <div class="stats-grid summary-grid">
      <div class="stat summary-item">
        <div class="stat-label summary-label">Categorias nas despesas</div>
        <div class="stat-value summary-value" id="total-categorias">0</div>
      </div>
      <div class="stat summary-item">
        <div class="stat-label summary-label">Total movimentado</div>
        <div class="stat-value summary-value" id="total-movimentado">R$ 0,00</div>
      </div>
      <div class="stat summary-item">
        <div class="stat-label summary-label">Transações de despesa</div>
        <div class="stat-value summary-value" id="total-transacoes">0</div>
      </div>
    </div>
  </div>

  <div class="actions">
    <button class="btn" onclick="abrirModal('incluir')">+ Incluir categoria</button>
  </div>

  <div class="card panel-card">
    <div class="info panel-head" style="margin:0;">
      Categorias encontradas nas transações de despesa (últimos 90 dias).
    </div>
    <table class="data-table">
      <thead>
        <tr>
          <th>Categoria</th>
          <th class="cat-count">Qtd. transações</th>
          <th style="text-align:right">Total movimentado</th>
        </tr>
      </thead>
      <tbody id="tbody-categorias"></tbody>
    </table>
    <div id="empty" class="empty" style="display:none;">Nenhuma despesa encontrada no período.</div>
  </div>

  <div id="modal" class="modal-overlay">
    <div class="modal-card">
      <div id="modal-title" class="modal-title">Cadastrar categoria</div>
      <input id="modal-input" class="modal-input" type="text" placeholder="Nome da categoria" />
      <div id="modal-msg" class="modal-msg">Categorias criadas aqui ficam disponíveis para todo o sistema.</div>
      <div class="row-actions">
        <button class="btn outline" onclick="fecharModal()">Cancelar</button>
        <button id="modal-save" class="btn" onclick="salvarModal()">Salvar</button>
      </div>
    </div>
  </div>

  <script>
    let categoriasCustom = [];
    let modalModo = 'incluir';
    let categoriaAtual = '';

    function _apiBase() {
      const { origin, pathname } = window.location;
      const root = pathname
        .replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '')
        .replace(/\/[^/]*\.(?:html|php)$/, '');
      return origin + root + '/backend/api';
    }

    function formatBRL(v) {
      return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Math.abs(v || 0));
    }

    async function carregarCategoriasCustom() {
      try {
        const res = await fetch(_apiBase() + '/pierre/categorias.php', { cache: 'no-store' });
        const json = await res.json();
        categoriasCustom = json.success ? (json.data.categorias || []) : [];
      } catch (_) {
        categoriasCustom = [];
      }
    }

    function abrirModal(modo, categoria = '') {
      modalModo = modo;
      categoriaAtual = categoria;

      const title = document.getElementById('modal-title');
      const input = document.getElementById('modal-input');
      const msg = document.getElementById('modal-msg');
      const save = document.getElementById('modal-save');

      if (modo === 'alterar') {
        title.textContent = 'Alterar categoria';
        input.value = categoria;
        msg.textContent = 'Renomeia a categoria e atualiza as transações associadas.';
        save.textContent = 'Alterar';
      } else {
        title.textContent = 'Incluir categoria';
        input.value = '';
        msg.textContent = 'Categorias criadas aqui ficam disponíveis para todo o sistema.';
        save.textContent = 'Salvar';
      }

      document.getElementById('modal').style.display = 'flex';
      setTimeout(() => input.focus(), 0);
    }

    function fecharModal() {
      document.getElementById('modal').style.display = 'none';
    }

    async function salvarModal() {
      const input = document.getElementById('modal-input');
      const nome = (input.value || '').trim();
      if (!nome) return;

      try {
        let payload = { acao: 'criar', categoria: nome };
        if (modalModo === 'alterar') {
          payload = { acao: 'renomear', categoriaAntiga: categoriaAtual, categoriaNova: nome };
        }

        const res = await fetch(_apiBase() + '/pierre/categorias.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });

        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Erro ao salvar categoria');

        fecharModal();
        await carregarCategoriasCustom();
        await carregarResumoCategorias();
      } catch (e) {
        await BFApp.modalAlert('Não foi possível salvar a categoria.', 'Erro');
      }
    }

    async function carregarResumoCategorias() {
      const toDateInputValueLocal = (date) => {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
      };

      const fim = new Date();
      const inicio = new Date();
      inicio.setDate(fim.getDate() - 90);
      const params = new URLSearchParams({
        startDate: toDateInputValueLocal(inicio),
        endDate: toDateInputValueLocal(fim),
      });

      const res = await fetch(_apiBase() + '/pierre/transacoes.php?' + params.toString(), { cache: 'no-store' });
      const json = await res.json();
      const txs = json.success ? (json.data.transacoes || []) : [];
      const despesas = txs.filter((tx) => (tx.type || '').toUpperCase() === 'DEBIT' || Number(tx.amount || 0) < 0);

      const mapa = {};
      let totalMovimentado = 0;

      despesas.forEach((tx) => {
        const categoria = (tx.category || 'Sem categoria').trim() || 'Sem categoria';
        const valor = Math.abs(Number(tx.amount || 0));

        if (!mapa[categoria]) {
          mapa[categoria] = { categoria, total: 0, quantidade: 0 };
        }

        mapa[categoria].total += valor;
        mapa[categoria].quantidade += 1;
        totalMovimentado += valor;
      });

      const categorias = Object.values(mapa).sort((a, b) => b.total - a.total);

      document.getElementById('total-categorias').textContent = String(categorias.length);
      document.getElementById('total-movimentado').textContent = formatBRL(totalMovimentado);
      document.getElementById('total-transacoes').textContent = String(despesas.length);

      const tbody = document.getElementById('tbody-categorias');
      const empty = document.getElementById('empty');
      tbody.innerHTML = '';

      if (categorias.length === 0) {
        empty.style.display = 'block';
        return;
      }

      empty.style.display = 'none';
      categorias.forEach((item) => {
        const tr = document.createElement('tr');
        const podeAlterar = categoriasCustom.some((c) => c.toLowerCase() === item.categoria.toLowerCase());
        tr.innerHTML = `
          <td data-label="Categoria" class="cat-name">
            <span>${item.categoria}</span>
            ${podeAlterar ? `<button class="cat-edit-inline context-chip" onclick="abrirModal('alterar', decodeURIComponent('${encodeURIComponent(item.categoria)}'))">Alterar</button>` : ''}
          </td>
          <td data-label="Qtd. transações" class="cat-count">${item.quantidade}</td>
          <td data-label="Total movimentado" class="cat-total">${formatBRL(item.total)}</td>
        `;
        tbody.appendChild(tr);
      });
    }

    (async function init() {
      await carregarCategoriasCustom();
      await carregarResumoCategorias();
    })();
  </script>

</body>
</html>
