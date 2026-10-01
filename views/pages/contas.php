<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Contas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <script src="../../js/app-runtime.js"></script>
  <script src="../../js/pierre-sync.js"></script>
  <style>
    .contas-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 1rem;
    }
    .conta-card {
      background: var(--bg-card);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 1.25rem;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }
    .conta-card-header {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .conta-icon {
      width: 40px; height: 40px;
      border-radius: 10px;
      background: var(--primary-light);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary);
      flex-shrink: 0;
    }
    .conta-icon svg { width: 20px; height: 20px; }
    .institution-logo {
      width: 24px;
      height: 24px;
      object-fit: contain;
      border-radius: 6px;
      background: #fff;
      padding: 1px;
      border: 1px solid rgba(255,255,255,0.45);
    }
    .conta-banco { font-size: 0.9rem; font-weight: 700; color: var(--text-heading); }
    .conta-tipo  { font-size: 0.75rem; color: var(--text-muted); }
    .conta-saldo-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
    .conta-saldo-valor { font-size: 1.35rem; font-weight: 700; color: var(--text-heading); }
    .conta-numero { font-size: 0.78rem; color: var(--text-muted); font-family: monospace; }
    .empty-contas {
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      padding: 3.5rem 1rem; gap: 0.625rem;
      color: var(--text-muted); text-align: center;
    }
    .empty-contas svg { width: 52px; height: 52px; opacity: 0.3; margin-bottom: 0.25rem; }
    .empty-contas p { font-size: 0.875rem; margin: 0; max-width: 300px; }
    .empty-contas strong { color: var(--text-heading); font-size: 0.95rem; }
    .loading-state { display: flex; align-items: center; gap: 8px; color: var(--text-muted); font-size: 0.875rem; padding: 2rem; }
    .spinner { width: 18px; height: 18px; border: 2px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.7s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body class="inner-page">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Contas</h1>
      <p>Suas contas bancárias conectadas</p>
    </div>
    <div class="page-header-actions">
      <button class="btn-sync secondary" id="btn-sync" onclick="PierreSync.atualizar(carregarContas, this)">↻ Atualizar</button>
    </div>
  </div>

  <div id="loading" class="card loading-state">
    <div class="spinner"></div>
    Carregando contas...
  </div>

  <div class="contas-grid" id="contas-grid" style="display:none"></div>

  <div class="card empty-contas" id="contas-empty" style="display:none">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <rect x="2" y="5" width="20" height="14" rx="2"/>
      <line x1="2" y1="10" x2="22" y2="10"/>
    </svg>
    <strong>Nenhuma conta bancária encontrada</strong>
    <p>Conecte suas contas bancárias via <strong>Pierre Finance</strong> para visualizar saldos.</p>
  </div>

  <div class="card empty-contas" id="contas-erro" style="display:none">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <strong>Erro ao carregar contas</strong>
    <p id="contas-erro-msg">Não foi possível conectar ao Pierre Finance.</p>
  </div>

  <script>
    const SUBTIPOS = { CHECKING_ACCOUNT: 'Conta Corrente', SAVINGS_ACCOUNT: 'Conta Poupança', PAYMENT_ACCOUNT: 'Conta Pagamento' };

    function formatBRL(v) {
      return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);
    }

    function _apiBase() {
      const { origin, pathname } = window.location;
      const root = pathname
        .replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '')
        .replace(/\/[^/]*\.(?:html|php)$/, '');
      return origin + root + '/backend/api';
    }

    function logoInstituicao(c) {
      return c.connectorImageUrl || c.institution?.imageUrl || '';
    }

    async function carregarContas() {
      document.getElementById('loading').style.display = 'flex';
      document.getElementById('contas-grid').style.display = 'none';
      document.getElementById('contas-empty').style.display = 'none';
      document.getElementById('contas-erro').style.display = 'none';

      try {
        const res  = await fetch(_apiBase() + '/pierre/contas.php?tipo=BANK', { cache: 'no-store' });
        const json = await res.json();
        document.getElementById('loading').style.display = 'none';

        if (!json.success) {
          document.getElementById('contas-erro-msg').textContent = json.message || 'Erro desconhecido.';
          document.getElementById('contas-erro').style.display = 'flex';
          return;
        }

        const contas = json.data.contas || [];
        if (contas.length === 0) {
          document.getElementById('contas-empty').style.display = 'flex';
          return;
        }

        const grid = document.getElementById('contas-grid');
        grid.innerHTML = '';
        contas.forEach(c => {
          const subtipo = SUBTIPOS[c.accountSubtype] || c.accountSubtype || '';
          const saldo   = formatBRL(c.accountBalance ?? 0);
          const numero  = c.bankData?.transferNumber ? `Ag. ${c.bankData.transferNumber}` : '';
          const logo    = logoInstituicao(c);
          const div = document.createElement('div');
          div.className = 'conta-card';
          div.innerHTML = `
            <div class="conta-card-header">
              <div class="conta-icon">
                ${logo
                  ? `<img src="${logo}" alt="Logo da instituição" class="institution-logo" loading="lazy" />`
                  : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
                    </svg>`}
              </div>
              <div>
                <div class="conta-banco">${c.providerCode || c.accountName}</div>
                <div class="conta-tipo">${subtipo}</div>
              </div>
            </div>
            ${numero ? `<div class="conta-numero">${numero}</div>` : ''}
            <div>
              <div class="conta-saldo-label">Saldo disponível</div>
              <div class="conta-saldo-valor">${saldo}</div>
            </div>
          `;
          grid.appendChild(div);
        });
        grid.style.display = 'grid';
      } catch (e) {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('contas-erro-msg').textContent = 'Erro de conexão. Tente novamente.';
        document.getElementById('contas-erro').style.display = 'flex';
      }
    }

    carregarContas();
  </script>

</body>
</html>
