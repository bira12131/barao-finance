<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <script src="../../js/app-runtime.js"></script>
  <script src="../../js/pierre-sync.js"></script>
  <style>
    .nb-summary {
      border-radius: 22px;
      margin-bottom: 0.95rem;
      position: relative;
      overflow: hidden;
    }
    .nb-summary::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(130deg, rgba(79, 133, 237, 0.24), transparent 58%);
      pointer-events: none;
    }
    .nb-summary-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.55rem;
      position: relative;
    }
    .nb-summary-title {
      font-size: 0.82rem;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.06em;
      font-weight: 700;
    }
    .nb-summary-value {
      font-size: 1.7rem;
      font-weight: 800;
      color: var(--text-heading);
      letter-spacing: -0.04em;
      margin-bottom: 0.7rem;
      position: relative;
    }
    .nb-metrics {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 0.55rem;
      position: relative;
    }
    .nb-metric {
      background: linear-gradient(160deg, rgba(18, 29, 46, 0.95), rgba(14, 23, 38, 0.92));
      border: 1px solid rgba(63, 82, 112, 0.92);
      border-radius: 16px;
      padding: 0.62rem;
    }
    .nb-metric-label {
      font-size: 0.66rem;
      font-weight: 700;
      color: var(--text-muted);
      letter-spacing: 0.05em;
      text-transform: uppercase;
      margin-bottom: 0.24rem;
    }
    .nb-metric-value {
      font-size: 0.9rem;
      color: var(--text-heading);
      font-weight: 700;
      line-height: 1.2;
    }
    .nb-actions-card {
      margin-bottom: 0.95rem;
      padding: 0.72rem;
    }
    .nb-actions {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0.52rem;
    }
    .nb-action {
      border: 1px solid rgba(62, 80, 109, 0.94);
      background: linear-gradient(160deg, rgba(17, 28, 44, 0.92), rgba(13, 21, 35, 0.9));
      border-radius: 16px;
      min-height: 78px;
      padding: 0.55rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      gap: 0.4rem;
      color: var(--text-body);
      font-weight: 600;
      font-size: 0.74rem;
      cursor: pointer;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .nb-action:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 20px rgba(20, 39, 71, 0.12);
    }
    .nb-action svg {
      width: 17px;
      height: 17px;
      color: var(--primary);
    }
    .nb-feed-card {
      border-radius: 22px;
      padding: 0.7rem;
    }
    .nb-feed-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.2rem 0.2rem 0.6rem;
    }
    .nb-feed-title {
      font-size: 0.92rem;
      font-weight: 700;
      color: var(--text-heading);
    }
    .nb-feed-sub {
      font-size: 0.73rem;
      color: var(--text-muted);
    }
    .nb-feed {
      display: flex;
      flex-direction: column;
      gap: 0.44rem;
    }
    .nb-feed-item {
      background: linear-gradient(160deg, rgba(19, 30, 47, 0.94), rgba(14, 23, 37, 0.9));
      border: 1px solid rgba(64, 83, 112, 0.95);
      border-radius: 16px;
      padding: 0.64rem 0.72rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.7rem;
    }
    .nb-feed-left {
      min-width: 0;
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }
    .nb-feed-icon {
      width: 32px;
      height: 32px;
      border-radius: 10px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      background: rgba(47, 102, 213, 0.24);
      color: var(--primary);
      font-size: 0.85rem;
      font-weight: 700;
    }
    .nb-feed-icon.debit {
      background: rgba(217, 79, 79, 0.24);
      color: var(--danger);
    }
    .nb-feed-text {
      min-width: 0;
    }
    .nb-feed-desc {
      font-size: 0.82rem;
      color: var(--text-heading);
      font-weight: 700;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 390px;
    }
    .nb-feed-meta {
      font-size: 0.72rem;
      color: var(--text-muted);
      margin-top: 0.12rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .nb-feed-value {
      font-size: 0.84rem;
      font-weight: 700;
      white-space: nowrap;
    }
    .nb-feed-value.credit { color: #16a34a; }
    .nb-feed-value.debit { color: var(--danger); }
    @media (max-width: 760px) {
      .nb-summary-value {
        font-size: 1.45rem;
      }
      .nb-metrics {
        grid-template-columns: 1fr;
      }
      .nb-actions {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .nb-feed-desc {
        max-width: 200px;
      }
    }
  </style>
</head>
<body class="inner-page">

  <!-- ── Cabeçalho da página ── -->
  <div class="page-header">
    <div class="page-header-left">
      <h1>Dashboard</h1>
      <p id="saudacao">Bem-vindo de volta</p>
    </div>
    <div class="page-header-actions">
      <span id="data-hoje" style="font-size:0.82rem; color:var(--text-muted);"></span>
      <button class="btn-sync secondary" id="btn-sync-dash"
        onclick="PierreSync.atualizar(recarregarTudo, this)">↻ Atualizar</button>
    </div>
  </div>

  <div class="card nb-summary">
    <div class="nb-summary-top">
      <span class="nb-summary-title">Resumo do mês atual</span>
      <span id="periodo-mes" class="nb-feed-sub">Este mês</span>
    </div>
    <div class="nb-summary-value" id="mov-mes">R$ —</div>
    <div class="nb-metrics">
      <div class="nb-metric">
        <div class="nb-metric-label">Receitas</div>
        <div class="nb-metric-value" id="receitas-mes">R$ —</div>
      </div>
      <div class="nb-metric">
        <div class="nb-metric-label">Despesas</div>
        <div class="nb-metric-value" id="despesas-mes">R$ —</div>
      </div>
      <div class="nb-metric">
        <div class="nb-metric-label">Saldo líquido</div>
        <div class="nb-metric-value" id="saldo-liquido">R$ —</div>
      </div>
    </div>
  </div>

  <div class="card nb-actions-card">
    <div class="nb-actions">
      <button class="nb-action" onclick="abrirPagina('pages/transacoes.php')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="1" x2="12" y2="23"/>
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
        </svg>
        Transações
      </button>
      <button class="nb-action" onclick="abrirPagina('pages/contas.php')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
        </svg>
        Contas
      </button>
      <button class="nb-action" onclick="abrirPagina('pages/cartoes.php')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
        </svg>
        Cartões
      </button>
      <button class="nb-action" onclick="abrirPagina('pages/assinaturas.php')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 6L9 17l-5-5"/><path d="M3 4h18"/>
        </svg>
        Assinaturas
      </button>
    </div>
  </div>

  <div class="card nb-feed-card" id="dash-feed-card">
    <div class="nb-feed-header">
      <div class="nb-feed-title">Últimas transações</div>
      <div class="nb-feed-sub">Atualizado em tempo real</div>
    </div>
    <div id="dash-feed" class="nb-feed">
      <div class="empty-state" style="padding:1.4rem 1rem;">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="1" x2="12" y2="23"/>
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
        </svg>
        <p>Nenhuma transação registrada ainda.</p>
      </div>
    </div>
  </div>

  <script>
    // Saudação com horário
    const hora = new Date().getHours();
    const saudacoes = [
      [0,  'Boa madrugada'],
      [5,  'Bom dia'],
      [12, 'Boa tarde'],
      [18, 'Boa noite'],
    ];
    const saudacao = saudacoes.reduce((acc, [h, s]) => hora >= h ? s : acc, 'Olá');
    const session = JSON.parse(sessionStorage.getItem('baraofinance_session') || 'null');
    const nome    = session?.name?.split(' ')[0] || '';
    document.getElementById('saudacao').textContent = nome
      ? `${saudacao}, ${nome}!`
      : `${saudacao}!`;

    // Data atual
    const hoje = new Date();
    document.getElementById('data-hoje').textContent = hoje.toLocaleDateString('pt-BR', {
      weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    });

    // Mês de referência nos cards
    const mesPt = hoje.toLocaleDateString('pt-BR', { month: 'long' });
    document.getElementById('periodo-mes').textContent =
      mesPt.charAt(0).toUpperCase() + mesPt.slice(1) + ' ' + hoje.getFullYear();

    // ── Helpers ──────────────────────────────────────────────────
    function formatBRL(v) {
      return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);
    }
    function formatData(d) {
      if (!d) return '—';
      const raw = String(d).trim();
      const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
      if (iso) {
        return `${iso[3]}/${iso[2]}/${iso[1]}`;
      }

      const dt = new Date(raw);
      if (!Number.isNaN(dt.getTime())) {
        return dt.toLocaleDateString('pt-BR');
      }

      return '—';
    }
    function abreviarTexto(texto, limite = 28) {
      const t = String(texto || '—');
      return t.length > limite ? t.slice(0, limite - 1) + '…' : t;
    }
    function _apiBase() {
      const { origin, pathname } = window.location;
      const root = pathname
        .replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '')
        .replace(/\/[^/]*\.(?:html|php)$/, '');
      return origin + root + '/backend/api';
    }

    function abrirPagina(page) {
      try {
        if (window.parent && window.parent !== window) {
          const frame = window.parent.document.getElementById('page-frame');
          if (frame) {
            frame.src = page;
            window.parent.localStorage.setItem('bf_page', page);
          }

          const navItems = window.parent.document.querySelectorAll('[data-page]');
          navItems.forEach((item) => {
            item.classList.toggle('active', item.dataset.page === page);
          });
          return;
        }
      } catch (_) {}

      window.location.href = page;
    }

    // ── Transações do mês ─────────────────────────────────────────
    async function carregarTransacoes() {
      const agora  = new Date();
      const inicio = new Date(agora.getFullYear(), agora.getMonth(), 1).toISOString().slice(0, 10);
      const fim    = agora.toISOString().slice(0, 10);

      try {
        const res  = await fetch(_apiBase() + `/pierre/transacoes.php?startDate=${inicio}&endDate=${fim}`, { cache: 'no-store' });
        const json = await res.json();
        if (!json.success) return;

        const txs      = json.data.transacoes || [];
        let receitas   = 0;
        let despesas   = 0;

        txs.forEach(tx => {
          const tipo = tx.type || (tx.amount >= 0 ? 'CREDIT' : 'DEBIT');
          if (tipo === 'CREDIT') receitas += Math.abs(tx.amount);
          else                   despesas += Math.abs(tx.amount);
        });

        document.getElementById('receitas-mes').textContent = formatBRL(receitas);
        document.getElementById('despesas-mes').textContent = formatBRL(despesas);
        document.getElementById('mov-mes').textContent = formatBRL(receitas + despesas);
        const liquido = receitas - despesas;
        const el = document.getElementById('saldo-liquido');
        el.textContent = formatBRL(Math.abs(liquido));
        el.className   = 'stat-value ' + (liquido >= 0 ? 'positivo' : 'negativo');

        // ── Feed de últimas transações ─────────────────────────────
        const ultimas = txs.slice(0, 8);
        const feed = document.getElementById('dash-feed');

        if (feed) {
          feed.innerHTML = '';

          if (ultimas.length === 0) {
            feed.innerHTML = `
              <div class="empty-state" style="padding:1.4rem 1rem;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="12" y1="1" x2="12" y2="23"/>
                  <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
                <p>Nenhuma transação registrada ainda.</p>
              </div>
            `;
            return;
          }

          ultimas.forEach((tx) => {
            const tipo  = tx.type || (tx.amount >= 0 ? 'CREDIT' : 'DEBIT');
            const sinal = tipo === 'CREDIT' ? '+' : '-';
            const isCredit = tipo === 'CREDIT';
            const item = document.createElement('div');
            item.className = 'nb-feed-item';
            item.innerHTML = `
              <div class="nb-feed-left">
                <span class="nb-feed-icon ${isCredit ? '' : 'debit'}">${isCredit ? '+' : '-'}</span>
                <div class="nb-feed-text">
                  <div class="nb-feed-desc">${abreviarTexto(tx.description || 'Transação')}</div>
                  <div class="nb-feed-meta">${formatData(tx.date)} · ${tx.category || 'Sem categoria'} · ${tx.account_name || 'Conta'}</div>
                </div>
              </div>
              <div class="nb-feed-value ${isCredit ? 'credit' : 'debit'}">${sinal} ${formatBRL(Math.abs(tx.amount))}</div>
            `;
            feed.appendChild(item);
          });
        }
      } catch (_) {}
    }

    function recarregarTudo() {
      return Promise.all([carregarTransacoes()]);
    }

    recarregarTudo();
  </script>

</body>
</html>
