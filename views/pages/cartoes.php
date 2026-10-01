<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Cartões</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <script src="../../js/app-runtime.js"></script>
  <script src="../../js/pierre-sync.js"></script>
  <style>
    .cartoes-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.25rem;
    }
    .cartao-visual {
      border-radius: 16px;
      padding: 1.5rem;
      min-height: 175px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
      background: linear-gradient(135deg, var(--card-start, #1b2a45) 0%, var(--card-end, #3b6fd4) 100%);
      box-shadow: 0 8px 24px rgba(59,111,212,0.25);
      color: #fff;
    }
    .cartao-visual::before {
      content: '';
      position: absolute;
      top: -40px; right: -40px;
      width: 160px; height: 160px;
      border-radius: 50%;
      background: rgba(255,255,255,0.06);
    }
    .cartao-visual::after {
      content: '';
      position: absolute;
      bottom: -30px; left: -30px;
      width: 120px; height: 120px;
      border-radius: 50%;
      background: rgba(255,255,255,0.04);
    }
    .cartao-header { display: flex; justify-content: space-between; align-items: flex-start; }
    .cartao-bandeira { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.7; }
    .cartao-logo {
      width: 22px;
      height: 22px;
      object-fit: contain;
      border-radius: 6px;
      padding: 1px;
      background: rgba(255,255,255,0.88);
      border: 1px solid rgba(255,255,255,0.5);
      margin-left: auto;
      margin-right: 0.45rem;
    }
    .cartao-nivel { font-size: 0.68rem; font-weight: 600; opacity: 0.65; }
    .cartao-numero { font-size: 1rem; letter-spacing: 0.18em; font-family: 'Courier New', monospace; opacity: 0.9; }
    .cartao-footer { display: flex; justify-content: space-between; align-items: flex-end; }
    .cartao-label  { font-size: 0.62rem; opacity: 0.6; text-transform: uppercase; letter-spacing: 0.07em; }
    .cartao-value  { font-size: 0.88rem; font-weight: 600; }
    .cartao-info   { margin-top: 0.875rem; }
    .cartao-info-row {
      display: flex;
      justify-content: space-between;
      font-size: 0.83rem;
      padding: 0.35rem 0;
      border-bottom: 1px solid var(--border);
    }
    .cartao-info-row:last-child { border-bottom: none; }
    .cartao-info-label { color: var(--text-muted); }
    .cartao-info-val   { font-weight: 600; color: var(--text-heading); }
    .cartao-info-val.danger { color: var(--danger); }
    .empty-cartoes {
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      padding: 3.5rem 1rem; gap: 0.625rem;
      color: var(--text-muted); text-align: center;
    }
    .empty-cartoes svg { width: 52px; height: 52px; opacity: 0.3; margin-bottom: 0.25rem; }
    .empty-cartoes p { font-size: 0.875rem; margin: 0; max-width: 300px; }
    .empty-cartoes strong { color: var(--text-heading); font-size: 0.95rem; }
    .loading-state { display: flex; align-items: center; gap: 8px; color: var(--text-muted); font-size: 0.875rem; padding: 2rem; }
    .spinner { width: 18px; height: 18px; border: 2px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.7s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body class="inner-page">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Cartões</h1>
      <p>Seus cartões de crédito</p>
    </div>
    <div class="page-header-actions">
      <button class="btn-sync secondary" id="btn-sync-cartoes" onclick="PierreSync.atualizar(carregarCartoes, this)">↻ Atualizar</button>
    </div>
  </div>

  <div id="loading" class="card loading-state">
    <div class="spinner"></div>
    Carregando cartões...
  </div>

  <div class="cartoes-grid" id="cartoes-grid" style="display:none"></div>

  <div class="card empty-cartoes" id="cartoes-empty" style="display:none">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
      <line x1="1" y1="10" x2="23" y2="10"/>
    </svg>
    <strong>Nenhum cartão encontrado</strong>
    <p>Seus cartões aparecerão aqui após serem conectados via <strong>Pierre Finance</strong>.</p>
  </div>

  <div class="card empty-cartoes" id="cartoes-erro" style="display:none">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <strong>Erro ao carregar cartões</strong>
    <p id="cartoes-erro-msg"></p>
  </div>

  <script>
    function formatBRL(v) {
      return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);
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

    function getInstitutionTheme(name) {
      const key = String(name || '').toLowerCase();
      if (key.includes('nubank') || key.includes('nu pagamentos')) return { start: '#641d9f', end: '#8a2be2' };
      if (key.includes('inter')) return { start: '#f36d00', end: '#ff8f1f' };
      if (key.includes('santander')) return { start: '#c5122a', end: '#e53935' };
      if (key.includes('itau') || key.includes('itaú')) return { start: '#ec7000', end: '#ff9b1f' };
      if (key.includes('bradesco')) return { start: '#b0003a', end: '#d81b60' };
      if (key.includes('caixa')) return { start: '#005ca9', end: '#0077d9' };
      if (key.includes('mercado pago')) return { start: '#009ee3', end: '#2bb6f6' };
      if (key.includes('c6')) return { start: '#1a1a1a', end: '#454545' };
      if (key.includes('picpay')) return { start: '#14c45c', end: '#21de72' };
      return { start: '#1b2a45', end: '#3b6fd4' };
    }

    function logoInstituicao(c) {
      return c.connectorImageUrl || c.institution?.imageUrl || '';
    }

    async function carregarCartoes() {
      document.getElementById('loading').style.display      = 'flex';
      document.getElementById('cartoes-grid').style.display  = 'none';
      document.getElementById('cartoes-empty').style.display = 'none';
      document.getElementById('cartoes-erro').style.display  = 'none';

      try {
        const res  = await fetch(_apiBase() + '/pierre/contas.php?tipo=CREDIT', { cache: 'no-store' });
        const json = await res.json();
        document.getElementById('loading').style.display = 'none';

        if (!json.success) {
          document.getElementById('cartoes-erro-msg').textContent = json.message || 'Erro desconhecido.';
          document.getElementById('cartoes-erro').style.display = 'flex';
          return;
        }

        const cartoes = json.data.contas || [];
        if (cartoes.length === 0) {
          document.getElementById('cartoes-empty').style.display = 'flex';
          return;
        }

        const grid = document.getElementById('cartoes-grid');
        grid.innerHTML = '';
        cartoes.forEach(c => {
          const cd      = c.creditData || {};
          const bandeira = cd.brand   || c.providerCode || 'Cartão';
          const nivel    = cd.level   || '';
          const limite   = cd.creditLimit         ?? 0;
          const fatura   = Math.abs(c.accountBalance ?? 0);
          const disponivel = cd.availableCreditLimit ?? (limite - fatura);
          const vencimento = formatData(cd.balanceDueDate);
          const fechamento = formatData(cd.balanceCloseDate);
          const provider = c.providerCode || c.accountName || '';
          const theme = getInstitutionTheme(provider);
          const logo = logoInstituicao(c);

          const wrapper = document.createElement('div');
          wrapper.innerHTML = `
            <div class="cartao-visual" style="--card-start:${theme.start}; --card-end:${theme.end};">
              <div class="cartao-header">
                <div class="cartao-bandeira">${bandeira}</div>
                ${logo ? `<img src="${logo}" alt="Logo da instituição" class="cartao-logo" loading="lazy" />` : ''}
                ${nivel ? `<div class="cartao-nivel">${nivel}</div>` : ''}
              </div>
              <div class="cartao-numero">•••• •••• •••• ••••</div>
              <div class="cartao-footer">
                <div>
                  <div class="cartao-label">Titular</div>
                  <div class="cartao-value">${c.accountMarketingName || c.accountName}</div>
                </div>
                <div style="text-align:right">
                  <div class="cartao-label">Vencimento</div>
                  <div class="cartao-value">${vencimento}</div>
                </div>
              </div>
            </div>
            <div class="cartao-info">
              <div class="cartao-info-row">
                <span class="cartao-info-label">Limite total</span>
                <span class="cartao-info-val">${formatBRL(limite)}</span>
              </div>
              <div class="cartao-info-row">
                <span class="cartao-info-label">Fatura atual</span>
                <span class="cartao-info-val danger">${formatBRL(fatura)}</span>
              </div>
              <div class="cartao-info-row">
                <span class="cartao-info-label">Limite disponível</span>
                <span class="cartao-info-val">${formatBRL(disponivel)}</span>
              </div>
              <div class="cartao-info-row">
                <span class="cartao-info-label">Fechamento</span>
                <span class="cartao-info-val">${fechamento}</span>
              </div>
            </div>
          `;
          grid.appendChild(wrapper);
        });
        grid.style.display = 'grid';
      } catch (e) {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('cartoes-erro-msg').textContent = 'Erro de conexão. Tente novamente.';
        document.getElementById('cartoes-erro').style.display = 'flex';
      }
    }

    carregarCartoes();
  </script>

</body>
</html>
