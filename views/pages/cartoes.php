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
    .btn-fatura {
      margin-top: 0.7rem;
      width: 100%;
      min-height: 44px;
      border-radius: var(--radius-sm);
      border: 1.5px solid var(--primary);
      background: transparent;
      color: var(--primary);
      font-weight: 700;
      font-size: 0.86rem;
      cursor: pointer;
    }
    .fx-overlay {
      position: fixed; inset: 0; z-index: 50; display: none;
      align-items: center; justify-content: center;
      background: rgba(8, 12, 20, 0.7); padding: 1rem;
    }
    .fx-overlay.open { display: flex; }
    .fx-sheet {
      width: 100%; max-width: 620px; max-height: 92vh; overflow-y: auto;
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: 20px; box-shadow: 0 22px 44px rgba(0,0,0,0.5); padding: 1.1rem;
    }
    .fx-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.6rem; margin-bottom: 0.7rem; }
    .fx-title { font-size: 1rem; font-weight: 800; color: var(--text-heading); }
    .fx-sub { font-size: 0.76rem; color: var(--text-muted); margin-top: 0.15rem; }
    .fx-close { width: 40px; height: 40px; border-radius: 12px; border: 1.5px solid var(--border); background: var(--bg-input); color: var(--text-body); font-size: 1.1rem; cursor: pointer; flex: none; }
    .fx-seg { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.3rem; padding: 0.25rem; margin-bottom: 0.8rem; border-radius: 14px; background: var(--bg-input); border: 1px solid var(--border); }
    .fx-seg button { min-height: 40px; border: none; border-radius: 10px; background: transparent; color: var(--text-muted); font-weight: 700; font-size: 0.84rem; cursor: pointer; }
    .fx-seg button.on { background: var(--primary); color: #fff; }
    .fx-sum { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-bottom: 0.9rem; }
    .fx-sum div { background: var(--bg-input); border: 1px solid var(--border); border-radius: 12px; padding: 0.55rem 0.65rem; }
    .fx-sum small { display: block; font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); font-weight: 700; }
    .fx-sum b { font-size: 0.92rem; color: var(--text-heading); }
    .fx-h { margin: 0.9rem 0 0.4rem; font-size: 0.74rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-muted); }
    .fx-cat { display: flex; justify-content: space-between; font-size: 0.84rem; padding: 0.3rem 0; border-bottom: 1px solid var(--border); color: var(--text-body); }
    .fx-cat b { color: var(--text-heading); }
    .fx-item { display: flex; align-items: center; gap: 0.7rem; padding: 0.6rem 0; border-bottom: 1px solid var(--border); }
    .fx-item-data { min-width: 2.6rem; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); }
    .fx-item-main { flex: 1; min-width: 0; }
    .fx-item-desc { font-size: 0.88rem; font-weight: 600; color: var(--text-heading); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fx-item-cat { font-size: 0.72rem; color: var(--text-muted); }
    .fx-item-val { font-size: 0.88rem; font-weight: 800; color: var(--text-heading); white-space: nowrap; }
    .fx-item-val.credito { color: var(--success); }
    .fx-empty { padding: 1rem 0.2rem; color: var(--text-muted); font-size: 0.86rem; }
    @media (max-width: 640px) {
      .fx-overlay { align-items: flex-end; padding: 0; }
      .fx-sheet { max-width: none; border-radius: 22px 22px 0 0; padding-bottom: 1.2rem; }
      .fx-sum { grid-template-columns: 1fr 1fr 1fr; }
    }
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

  <div class="fx-overlay" id="fx-overlay">
    <div class="fx-sheet" role="dialog" aria-modal="true" aria-labelledby="fx-title">
      <div class="fx-head">
        <div>
          <div class="fx-title" id="fx-title">Fatura</div>
          <div class="fx-sub" id="fx-sub"></div>
        </div>
        <button type="button" class="fx-close" id="fx-close" aria-label="Fechar"><i data-icone="x"></i></button>
      </div>
      <div class="fx-seg">
        <button type="button" id="fx-atual" class="on">Atual</button>
        <button type="button" id="fx-proxima">Próxima</button>
        <button type="button" id="fx-anterior">Anterior</button>
      </div>
      <div id="fx-body"></div>
    </div>
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
                  <div class="cartao-label">Cartão</div>
                  <div class="cartao-value">${[c.providerCode, c.accountMarketingName || c.accountName].filter(Boolean).join(' ')}</div>
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
          const btnFatura = document.createElement('button');
          btnFatura.type = 'button';
          btnFatura.className = 'btn-fatura';
          btnFatura.textContent = 'Ver itens da fatura';
          btnFatura.addEventListener('click', () => abrirFatura(c.id || c.accountId, c.accountMarketingName || c.accountName));
          wrapper.querySelector('.cartao-info').appendChild(btnFatura);
          grid.appendChild(wrapper);
        });
        grid.style.display = 'grid';
      } catch (e) {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('cartoes-erro-msg').textContent = 'Erro de conexão. Tente novamente.';
        document.getElementById('cartoes-erro').style.display = 'flex';
      }
    }

    // ── Itens da fatura ──────────────────────────────────────────
    let fxContaId = null;
    let fxPeriodo = 'atual';
    const fxEl = (id) => document.getElementById(id);

    function fxData(d) {
      const [y, m, dia] = String(d).slice(0, 10).split('-');
      return `${dia}/${m}`;
    }

    function fxLinha(rotulo, valor) {
      const row = document.createElement('div');
      row.className = 'fx-cat';
      const a = document.createElement('span');
      a.textContent = rotulo;
      const b = document.createElement('b');
      b.textContent = valor;
      row.append(a, b);
      return row;
    }

    async function carregarFatura() {
      const body = fxEl('fx-body');
      body.textContent = 'Carregando…';
      ['atual', 'proxima', 'anterior'].forEach((p) => fxEl('fx-' + p).classList.toggle('on', fxPeriodo === p));

      try {
        const url = new URL(_apiBase() + '/pierre/fatura.php');
        url.searchParams.set('conta_id', fxContaId);
        url.searchParams.set('periodo', fxPeriodo);
        const res = await fetch(url.toString(), { cache: 'no-store' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Erro ao carregar a fatura.');
        const f = json.data.fatura;

        if (f.modo === 'ciclo') {
          const rot = { atual: 'Fatura atual', proxima: 'Próxima fatura', anterior: 'Fatura anterior' }[f.periodo];
          fxEl('fx-sub').textContent = `${rot} · ${f.fechada ? 'fechou' : 'fecha'} em ${formatData(f.fechamento)} · vence em ${formatData(f.vencimento)}`;
        } else {
          const [fy, fm] = f.mes_fatura.split('-');
          const mesNome = new Date(Number(fy), Number(fm) - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
          fxEl('fx-sub').textContent = 'Fatura de ' + mesNome + (f.vencimento ? ' · vence em ' + formatData(f.vencimento) : '');
        }
        body.textContent = '';

        const nota = (texto, aviso) => {
          const n = document.createElement('div');
          n.className = 'fx-empty';
          n.textContent = texto;
          if (aviso) n.style.color = 'var(--warning)';
          body.appendChild(n);
        };

        if (f.modo === 'ciclo') {
          const r = f.resumo_cartao;
          nota(`Total usado no cartão: ${formatBRL(r.saldo)} = fatura ${r.fechada ? 'fechada' : 'atual'} ${formatBRL(r.atual)}` + (r.proxima > 0 ? ` + próxima fatura ${formatBRL(r.proxima)}.` : '.'));
          nota(`Fecha todo dia ${f.dia_fechamento}. Compra feita no dia do fechamento ou depois já entra na fatura seguinte.`);
        }

        const sum = document.createElement('div');
        sum.className = 'fx-sum';
        [['Compras', f.total_compras], ['Créditos', f.total_creditos], ['Líquido', f.total_liquido]].forEach(([l, v]) => {
          const d = document.createElement('div');
          const sm = document.createElement('small');
          sm.textContent = l;
          const b = document.createElement('b');
          b.textContent = formatBRL(v);
          d.append(sm, b);
          sum.appendChild(d);
        });
        body.appendChild(sum);

        const totalOficial = f.modo === 'ciclo' ? f.total_fatura : f.fatura_atual;
        if (totalOficial !== null && f.nao_detalhado > 0.5) {
          nota(`Total desta fatura: ${formatBRL(totalOficial)}. Itens detalhados: ${formatBRL(f.total_liquido)}. Faltam ${formatBRL(f.nao_detalhado)} que o banco não lista item a item (em geral juros, saldo de fatura anterior ou compras que ele ainda não liberou).`, true);
        }

        if (!f.itens.length) {
          const e = document.createElement('div');
          e.className = 'fx-empty';
          e.textContent = 'Nenhum lançamento nesta fatura. Toque em "Atualizar" no topo da tela para sincronizar.';
          body.appendChild(e);
          return;
        }

        if (f.por_categoria.length) {
          const h = document.createElement('div');
          h.className = 'fx-h';
          h.textContent = 'Onde foi o dinheiro';
          body.appendChild(h);
          f.por_categoria.slice(0, 6).forEach((c) => body.appendChild(fxLinha(c.categoria, formatBRL(c.valor))));
        }

        const h2 = document.createElement('div');
        h2.className = 'fx-h';
        h2.textContent = `Lançamentos (${f.itens.length})`;
        body.appendChild(h2);

        f.itens.forEach((it) => {
          const row = document.createElement('div');
          row.className = 'fx-item';
          const data = document.createElement('span');
          data.className = 'fx-item-data';
          data.textContent = fxData(it.data);
          if (it.hora_ts) {
            const h = new Date(it.hora_ts).toLocaleTimeString('pt-BR', { timeZone: 'America/Sao_Paulo', hour: '2-digit', minute: '2-digit' });
            if (h !== '00:00') data.title = 'Registrada às ' + h;
          }
          const main = document.createElement('div');
          main.className = 'fx-item-main';
          const desc = document.createElement('div');
          desc.className = 'fx-item-desc';
          desc.textContent = it.descricao;
          const cat = document.createElement('div');
          cat.className = 'fx-item-cat';
          cat.textContent = it.categoria || 'Sem categoria';
          main.append(desc, cat);
          const val = document.createElement('span');
          const credito = it.tipo !== 'DEBIT';
          val.className = 'fx-item-val' + (credito ? ' credito' : '');
          val.textContent = (credito ? '+ ' : '') + formatBRL(Math.abs(it.valor));
          if (it.parcela && it.total_parcelas) cat.textContent += ` · parcela ${it.parcela}/${it.total_parcelas}`;
          if (it.de_mes_anterior) cat.textContent += ' · encargo do mês anterior';
          if (it.fonte === 'data') cat.textContent += ' · fatura definida pela data (o banco ainda não confirmou)';
          row.append(data, main, val);
          body.appendChild(row);
        });
      } catch (e) {
        body.textContent = e.message || 'Erro ao carregar a fatura.';
      }
    }

    function abrirFatura(contaId, nome) {
      fxContaId = contaId;
      fxPeriodo = 'atual';
      fxEl('fx-title').textContent = 'Fatura · ' + (nome || 'Cartão');
      fxEl('fx-overlay').classList.add('open');
      carregarFatura();
    }

    fxEl('fx-close').addEventListener('click', () => fxEl('fx-overlay').classList.remove('open'));
    fxEl('fx-overlay').addEventListener('click', (e) => { if (e.target === fxEl('fx-overlay')) fxEl('fx-overlay').classList.remove('open'); });
    ['atual', 'proxima', 'anterior'].forEach((p) => fxEl('fx-' + p).addEventListener('click', () => { fxPeriodo = p; carregarFatura(); }));

    carregarCartoes();
  </script>

</body>
</html>
