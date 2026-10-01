<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Assinaturas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <script src="../../js/app-runtime.js"></script>
  <script src="../../js/pierre-sync.js"></script>
  <style>
    .btn-inline {
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.35rem;
      min-height: 36px;
      padding: 0 0.9rem;
      border-radius: var(--radius-sm);
      font-size: 0.82rem;
      font-weight: 600;
      border: 1.5px solid var(--primary);
      background: var(--primary);
      color: #fff;
      cursor: pointer;
    }
    .assinaturas-resumo { margin-bottom: 0; }
    .mini-card {
      background: transparent;
      border: none;
      box-shadow: none;
      padding: 0.72rem 0.82rem;
    }
    .mini-label {
      font-size: 0.66rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-muted);
      font-weight: 700;
      margin-bottom: 0.22rem;
    }
    .mini-value {
      font-size: 0.98rem;
      font-weight: 800;
      color: var(--text-heading);
      line-height: 1.2;
    }
    .assinaturas-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 0.9rem;
    }
    .assinatura-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-card);
      padding: 1rem;
      display: flex;
      flex-direction: column;
      gap: 0.6rem;
      transition: transform 0.16s ease, box-shadow 0.16s ease;
    }
    .assinatura-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 24px rgba(26, 48, 86, 0.14);
    }
    .assinatura-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 0.7rem;
    }
    .assinatura-titulo {
      font-weight: 700;
      color: var(--text-heading);
      font-size: 0.94rem;
      line-height: 1.2;
    }
    .assinatura-conta {
      font-size: 0.78rem;
      color: var(--text-muted);
      margin-top: 0.18rem;
    }
    .assinatura-valor {
      font-size: 1.05rem;
      color: var(--danger);
      font-weight: 700;
      white-space: nowrap;
    }
    .assinatura-meta {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.45rem;
      font-size: 0.76rem;
      color: var(--text-muted);
    }
    .assinatura-meta strong {
      color: var(--text-body);
      display: block;
      font-size: 0.8rem;
      margin-top: 0.1rem;
    }
    .periodo-badge {
      display: inline-flex;
      align-items: center;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      width: fit-content;
    }
    .assinatura-actions {
      display: flex;
      justify-content: flex-end;
      margin-top: 0.25rem;
    }
    .btn-indevida {
      height: 30px;
      padding: 0 0.7rem;
      border-radius: 8px;
      border: 1px solid #f2b5b5;
      background: #fff3f3;
      color: #b42323;
      font-size: 0.75rem;
      font-weight: 700;
      cursor: pointer;
    }
    .btn-indevida:hover { opacity: 0.9; }

    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(20, 32, 50, 0.48);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 45;
      padding: 1rem;
    }
    .modal-card {
      width: 100%;
      max-width: 560px;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 14px;
      box-shadow: 0 18px 36px rgba(18, 28, 44, 0.25);
      padding: 1rem;
    }
    .modal-title {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--text-heading);
      margin-bottom: 0.7rem;
    }
    .modal-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.7rem;
    }
    .modal-field {
      display: flex;
      flex-direction: column;
      gap: 0.28rem;
    }
    .modal-field.full { grid-column: 1 / -1; }
    .modal-label {
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-muted);
      font-weight: 700;
    }
    .modal-input {
      height: 38px;
      padding: 0 0.72rem;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      background: var(--bg-input);
      color: var(--text-body);
      font-size: 0.83rem;
      outline: none;
    }
    .modal-input:focus { border-color: var(--primary); }
    .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 0.45rem;
      margin-top: 0.8rem;
    }
    .btn-outline {
      background: var(--bg-input);
      color: var(--text-body);
      border-color: var(--border);
    }
    .modal-msg {
      margin-top: 0.55rem;
      min-height: 1rem;
      font-size: 0.8rem;
      color: var(--text-muted);
    }

    .loading-state { display: flex; align-items: center; gap: 8px; color: var(--text-muted); font-size: 0.875rem; padding: 2rem; }
    .spinner { width: 18px; height: 18px; border: 2px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.7s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .empty-box {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: var(--text-muted);
      padding: 2rem 1rem;
      gap: 0.4rem;
    }
    .empty-box strong { color: var(--text-heading); }

    @media (max-width: 960px) {
      .assinaturas-grid {
        grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
      }
    }

    @media (max-width: 760px) {
      .page-header {
        flex-direction: column;
      }
      .btn-inline,
      .btn-primary,
      .btn-sync {
        width: 100%;
      }
      .assinaturas-grid {
        grid-template-columns: 1fr;
      }
      .assinatura-meta {
        grid-template-columns: 1fr;
      }
      .modal-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body class="inner-page tone-assinaturas">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Assinaturas</h1>
      <p>Recorrências detectadas e assinaturas manuais sem data de encerramento</p>
    </div>
    <div class="page-header-actions">
      <button class="btn-inline" onclick="abrirModalAssinatura()">+ Adicionar assinatura</button>
      <button class="btn-sync secondary" onclick="PierreSync.atualizar(carregarAssinaturas, this)">↻ Atualizar</button>
    </div>
  </div>

  <div class="summary-panel">
    <div class="assinaturas-resumo summary-grid">
      <div class="mini-card summary-item">
        <div class="mini-label summary-label">Assinaturas ativas</div>
        <div class="mini-value summary-value" id="sum-total">—</div>
      </div>
      <div class="mini-card summary-item">
        <div class="mini-label summary-label">Total mensal estimado</div>
        <div class="mini-value summary-value" id="sum-mensal">R$ —</div>
      </div>
      <div class="mini-card summary-item">
        <div class="mini-label summary-label">Próxima cobrança</div>
        <div class="mini-value summary-value" id="sum-proxima">—</div>
      </div>
    </div>
  </div>

  <div id="loading" class="card panel-card loading-state">
    <div class="spinner"></div>
    Carregando assinaturas...
  </div>

  <div id="assinaturas-grid" class="assinaturas-grid" style="display:none"></div>

  <div id="empty" class="card panel-card empty-box" style="display:none">
    <strong>Nenhuma assinatura detectada</strong>
    <p>Sincronize suas transações para identificar cobranças recorrentes.</p>
  </div>

  <div id="modal-assinatura" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-title">Adicionar assinatura manual</div>

      <div class="modal-grid">
        <div class="modal-field full">
          <label class="modal-label" for="a-descricao">Descrição</label>
          <input class="modal-input" id="a-descricao" type="text" placeholder="Ex.: GitHub, Netflix, YouTube Premium" />
        </div>

        <div class="modal-field">
          <label class="modal-label" for="a-primeira">Mês da primeira cobrança</label>
          <input class="modal-input" id="a-primeira" type="month" />
        </div>

        <div class="modal-field">
          <label class="modal-label" for="a-valor">Valor mensal</label>
          <input class="modal-input" id="a-valor" type="text" inputmode="numeric" placeholder="R$ 0,00" />
        </div>

      </div>

      <div class="modal-actions">
        <button class="btn-inline btn-outline" onclick="fecharModalAssinatura()">Cancelar</button>
        <button class="btn-inline" onclick="salvarAssinaturaManual()">Salvar</button>
      </div>

      <div id="modal-assinatura-msg" class="modal-msg"></div>
    </div>
  </div>

  <script>
    function formatBRL(v) {
      return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
    }

    function formatDate(d) {
      if (!d) return '—';
      const dt = new Date(d);
      if (Number.isNaN(dt.getTime())) return '—';
      return dt.toLocaleDateString('pt-BR');
    }

    function _apiBase() {
      const { origin, pathname } = window.location;
      const root = pathname
        .replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '')
        .replace(/\/[^/]*\.(?:html|php)$/, '');
      return origin + root + '/backend/api';
    }

    function abrirModalAssinatura() {
      document.getElementById('modal-assinatura').style.display = 'flex';
      document.getElementById('modal-assinatura-msg').textContent = '';
      document.getElementById('a-primeira').value = new Date().toISOString().slice(0, 7);
      setTimeout(() => document.getElementById('a-descricao').focus(), 0);
    }

    function fecharModalAssinatura() {
      document.getElementById('modal-assinatura').style.display = 'none';
    }

    function normalizarMesAssinatura(valor) {
      const v = String(valor || '').trim();
      if (!v) return '';
      if (/^\d{4}-\d{2}$/.test(v)) return v;
      if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return v.slice(0, 7);
      const mmYyyy = v.match(/^(\d{2})\/(\d{4})$/);
      if (mmYyyy) return `${mmYyyy[2]}-${mmYyyy[1]}`;
      return v;
    }

    function bindCurrencyInput(id) {
      const el = document.getElementById(id);
      if (!el) return;

      el.dataset.value = '';
      el.addEventListener('input', () => {
        const digits = el.value.replace(/\D/g, '');
        if (!digits) {
          el.value = '';
          el.dataset.value = '';
          return;
        }

        const amount = Number(digits) / 100;
        el.dataset.value = amount.toFixed(2);
        el.value = amount.toLocaleString('pt-BR', {
          style: 'currency',
          currency: 'BRL',
        });
      });
    }

    async function salvarAssinaturaManual() {
      const descricao = document.getElementById('a-descricao').value.trim();
      const primeira = normalizarMesAssinatura(document.getElementById('a-primeira').value);
      const valor = Number(document.getElementById('a-valor').dataset.value || 0);
      const msg = document.getElementById('modal-assinatura-msg');

      if (!descricao || !primeira || valor <= 0) {
        msg.textContent = 'Preencha descrição, mês da primeira cobrança e valor mensal.';
        msg.style.color = 'var(--danger)';
        return;
      }

      try {
        const res = await fetch(_apiBase() + '/pierre/assinaturas.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            acao: 'cadastrar_manual',
            descricao,
            primeira_cobranca_mes: primeira,
            valor,
          }),
        });

        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Erro ao cadastrar');

        msg.textContent = 'Assinatura manual cadastrada com sucesso.';
        msg.style.color = '#16a34a';

        document.getElementById('a-descricao').value = '';
        document.getElementById('a-valor').value = '';
        document.getElementById('a-valor').dataset.value = '';

        await carregarAssinaturas();
        setTimeout(fecharModalAssinatura, 450);
      } catch (e) {
        msg.textContent = e.message || 'Não foi possível cadastrar a assinatura.';
        msg.style.color = 'var(--danger)';
      }
    }

    async function alterarStatusManual(id, ativa) {
      try {
        const res = await fetch(_apiBase() + '/pierre/assinaturas.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ acao: 'status_manual', id, ativa }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Erro');

        await carregarAssinaturas();
      } catch (_) {
        await BFApp.modalAlert('Não foi possível atualizar o status da assinatura.', 'Erro');
      }
    }

    async function marcarIndevida(descricao, valor) {
      const confirmou = await BFApp.modalConfirm(
        'Marcar esta assinatura como indevida? Ela não aparecerá novamente após atualizar.',
        'Confirmar ação'
      );
      if (!confirmou) {
        return;
      }

      try {
        const res = await fetch(_apiBase() + '/pierre/assinaturas.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ acao: 'indevida', descricao, valor }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Erro');

        await carregarAssinaturas();
      } catch (_) {
        await BFApp.modalAlert('Não foi possível marcar como indevida.', 'Erro');
      }
    }

    async function carregarAssinaturas() {
      document.getElementById('loading').style.display = 'flex';
      document.getElementById('assinaturas-grid').style.display = 'none';
      document.getElementById('empty').style.display = 'none';

      try {
        const res = await fetch(_apiBase() + '/pierre/assinaturas.php', { cache: 'no-store' });
        const json = await res.json();
        document.getElementById('loading').style.display = 'none';

        if (!json.success) {
          document.getElementById('empty').style.display = 'flex';
          return;
        }

        const data = json.data || {};
        const assinaturas = data.assinaturas || [];

        document.getElementById('sum-total').textContent = String(data.total || assinaturas.length || 0);
        document.getElementById('sum-mensal').textContent = formatBRL(data.total_mensal || 0);

        let proxima = null;
        assinaturas.forEach((a) => {
          if (!a.proxima_cobranca) return;
          const dt = new Date(a.proxima_cobranca);
          if (Number.isNaN(dt.getTime())) return;
          if (!proxima || dt < proxima) proxima = dt;
        });
        document.getElementById('sum-proxima').textContent = proxima ? proxima.toLocaleDateString('pt-BR') : '—';

        if (assinaturas.length === 0) {
          document.getElementById('empty').style.display = 'flex';
          return;
        }

        const grid = document.getElementById('assinaturas-grid');
        grid.innerHTML = '';

        assinaturas.forEach((a) => {
          const card = document.createElement('div');
          card.className = 'assinatura-card';
          const encodedDesc = encodeURIComponent(a.descricao || '');
          const isManual = String(a.origem || '') === 'manual';
          card.innerHTML = `
            <div class="assinatura-top">
              <div>
                <div class="assinatura-titulo">${a.descricao || 'Assinatura'}</div>
                <div class="assinatura-conta">${a.conta_nome || (isManual ? 'Assinatura manual' : 'Conta não identificada')}</div>
              </div>
              <div class="assinatura-valor">${formatBRL(a.valor)}</div>
            </div>
            <span class="periodo-badge context-chip">${isManual ? 'Manual' : (a.periodicidade_label || a.periodicidade || 'Recorrente')}</span>
            <div class="assinatura-meta">
              <div>
                Última cobrança
                <strong>${formatDate(a.ultima_cobranca)}</strong>
              </div>
              <div>
                Próxima cobrança
                <strong>${formatDate(a.proxima_cobranca)}</strong>
              </div>
              <div>
                Categoria
                <strong>${a.categoria || '—'}</strong>
              </div>
              <div>
                Tipo de conta
                <strong>${a.conta_tipo || '—'}</strong>
              </div>
            </div>
            <div class="assinatura-actions">
              ${isManual
                ? `<button class="btn-indevida" onclick="alterarStatusManual('${a.id}', false)">Desativar</button>`
                : `<button class="btn-indevida" onclick="marcarIndevida(decodeURIComponent('${encodedDesc}'), ${Number(a.valor || 0)})">Marcar indevida</button>`}
            </div>
          `;
          grid.appendChild(card);
        });

        grid.style.display = 'grid';
      } catch (_) {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('empty').style.display = 'flex';
      }
    }

    bindCurrencyInput('a-valor');
    carregarAssinaturas();
  </script>

</body>
</html>
