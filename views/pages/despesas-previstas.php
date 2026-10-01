<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Despesas Previstas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <script src="../../js/app-runtime.js"></script>
  <style>
    .actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.7rem;
      margin-bottom: 0.8rem;
      flex-wrap: wrap;
    }
    .filter-wrap {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
    }
    .filter-wrap .label {
      margin: 0;
      font-size: 0.68rem;
      white-space: nowrap;
    }
    .month-input {
      height: 36px;
      min-width: 156px;
      padding: 0 0.62rem;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      background: var(--bg-input);
      color: var(--text-body);
      font-size: 0.8rem;
      outline: none;
    }
    .month-input:focus { border-color: var(--primary); }
    .btn {
      height: 36px;
      padding: 0 0.9rem;
      border-radius: var(--radius-sm);
      border: 1.5px solid var(--primary);
      background: var(--primary);
      color: #fff;
      font-size: 0.82rem;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
    }
    .btn.small {
      height: 30px;
      padding: 0 0.68rem;
      font-size: 0.74rem;
      border-radius: 8px;
    }
    .btn.danger {
      background: #fee2e2;
      border-color: #fecaca;
      color: #b42323;
    }
    .actions-cell {
      display: flex;
      gap: 0.4rem;
      justify-content: flex-end;
    }
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
      color: color-mix(in srgb, var(--page-accent) 48%, var(--text-muted));
      font-weight: 700;
      margin-bottom: 0.24rem;
    }
    .stat-value {
      font-size: 1.05rem;
      color: var(--text-heading);
      font-weight: 700;
    }
    .empty {
      padding: 1rem;
      color: var(--text-muted);
      font-size: 0.85rem;
    }
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(20, 32, 50, 0.45);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 40;
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
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.7rem; }
    .field { display: flex; flex-direction: column; gap: 0.28rem; }
    .field.full { grid-column: 1 / -1; }
    .label {
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-muted);
      font-weight: 700;
    }
    .input {
      height: 38px;
      padding: 0 0.72rem;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      background: var(--bg-input);
      color: var(--text-body);
      font-size: 0.83rem;
      outline: none;
    }
    .input:focus { border-color: var(--primary); }
    .modal-actions { display: flex; justify-content: flex-end; gap: 0.45rem; margin-top: 0.8rem; }
    .btn.outline { background: var(--bg-input); color: var(--text-body); border-color: var(--border); }
    .modal-msg { margin-top: 0.55rem; min-height: 1rem; font-size: 0.8rem; color: var(--text-muted); }
    @media (max-width: 760px) {
      .grid { grid-template-columns: 1fr; }
      .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.44rem;
      }
      .stats > .summary-item:last-child:nth-child(odd) {
        grid-column: 1 / -1;
      }
    }
  </style>
</head>
<body class="inner-page tone-despesas">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Despesas previstas</h1>
      <p>Planejamento de contas com início e data de encerramento</p>
    </div>
  </div>

  <div class="actions">
    <div class="filter-wrap">
      <label class="label" for="filtro-mes">Mês de referência</label>
      <input class="month-input" id="filtro-mes" type="month" />
    </div>
    <button class="btn" onclick="abrirModal()">+ Cadastrar despesa prevista</button>
  </div>

  <div class="summary-panel">
    <div class="stats summary-grid">
      <div class="stat summary-item">
        <div class="stat-label summary-label">Despesas ativas</div>
        <div class="stat-value summary-value" id="sum-total">0</div>
      </div>
      <div class="stat summary-item">
        <div class="stat-label summary-label" id="sum-mes-label">Total previsto no mês</div>
        <div class="stat-value summary-value" id="sum-mes-atual">R$ 0,00</div>
      </div>
      <div class="stat summary-item">
        <div class="stat-label summary-label">Encerram este ano</div>
        <div class="stat-value summary-value" id="sum-ano">0</div>
      </div>
    </div>
  </div>

  <div class="card panel-card">
    <table class="data-table">
      <thead>
        <tr>
          <th>Descrição</th>
          <th>Primeira cobrança</th>
          <th>Parcela</th>
          <th>Duração</th>
          <th>Mês de encerramento</th>
          <th>Meses restantes</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody id="tbody"></tbody>
    </table>
    <div id="empty" class="empty" style="display:none">Nenhuma despesa prevista cadastrada.</div>
  </div>

  <div id="modal" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-title" id="modal-title">Cadastrar despesa prevista</div>

      <div class="grid">
        <div class="field full">
          <label class="label" for="m-descricao">Descrição</label>
          <input class="input" id="m-descricao" type="text" placeholder="Ex.: GitHub, Netflix, Aluguel" />
        </div>

        <div class="field">
          <label class="label" for="m-primeira">Mês da primeira cobrança</label>
          <input class="input" id="m-primeira" type="month" />
        </div>

        <div class="field">
          <label class="label" for="m-duracao">Encerrar em quantos meses</label>
          <input class="input" id="m-duracao" type="number" min="1" step="1" placeholder="Ex.: 12" />
        </div>

        <div class="field full">
          <label class="label" for="m-valor">Valor da parcela</label>
          <input class="input" id="m-valor" type="text" inputmode="numeric" placeholder="R$ 0,00" />
        </div>

      </div>

      <div class="modal-actions">
        <button class="btn outline" onclick="fecharModal()">Cancelar</button>
        <button class="btn" id="modal-save-btn" onclick="salvarModal()">Salvar</button>
      </div>

      <div id="modal-msg" class="modal-msg"></div>
    </div>
  </div>

  <script>
    let editingId = null;

    function _apiBase() {
      const { origin, pathname } = window.location;
      const root = pathname
        .replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '')
        .replace(/\/[^/]*\.(?:html|php)$/, '');
      return origin + root + '/backend/api';
    }

    function formatDate(d) {
      if (!d) return '—';
      const raw = String(d).slice(0, 10);
      const parts = raw.split('-');
      if (parts.length !== 3) return '—';
      const year = Number(parts[0]);
      const month = Number(parts[1]);
      const day = Number(parts[2]);
      const dt = new Date(year, month - 1, day);
      if (Number.isNaN(dt.getTime())) return '—';
      return dt.toLocaleDateString('pt-BR', { month: '2-digit', year: 'numeric' });
    }

    function abrirModal() {
      editingId = null;
      document.getElementById('modal-title').textContent = 'Cadastrar despesa prevista';
      document.getElementById('modal-save-btn').textContent = 'Salvar';
      document.getElementById('modal').style.display = 'flex';
      document.getElementById('modal-msg').textContent = '';
      document.getElementById('m-descricao').value = '';
      document.getElementById('m-primeira').value = new Date().toISOString().slice(0, 7);
      document.getElementById('m-duracao').value = '';
      setCurrencyInputValue('m-valor', 0);
      setTimeout(() => document.getElementById('m-descricao').focus(), 0);
    }

    function abrirModalEdicao(id, descricao, primeiraCobranca, duracao, valorParcela) {
      editingId = id;
      document.getElementById('modal-title').textContent = 'Editar despesa prevista';
      document.getElementById('modal-save-btn').textContent = 'Atualizar';
      document.getElementById('modal').style.display = 'flex';
      document.getElementById('modal-msg').textContent = '';
      document.getElementById('m-descricao').value = descricao || '';
      document.getElementById('m-primeira').value = String(primeiraCobranca || '').slice(0, 7);
      document.getElementById('m-duracao').value = Number(duracao || 0) > 0 ? Number(duracao) : '';
      setCurrencyInputValue('m-valor', Number(valorParcela || 0));
      setTimeout(() => document.getElementById('m-descricao').focus(), 0);
    }

    function normalizarMes(valor) {
      const v = String(valor || '').trim();
      if (!v) return '';

      if (/^\d{4}-\d{2}$/.test(v)) return v;
      if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return v.slice(0, 7);

      const mmYyyy = v.match(/^(\d{2})\/(\d{4})$/);
      if (mmYyyy) return `${mmYyyy[2]}-${mmYyyy[1]}`;

      return v;
    }

    function fecharModal() {
      editingId = null;
      document.getElementById('modal').style.display = 'none';
    }

    function formatBRL(v) {
      return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
    }

    function getMesAtualReferencia() {
      const hoje = new Date();
      return `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}`;
    }

    function formatMesLabel(mesReferencia) {
      const match = String(mesReferencia || '').match(/^(\d{4})-(\d{2})$/);
      if (!match) return 'mês selecionado';

      const data = new Date(Number(match[1]), Number(match[2]) - 1, 1);
      if (Number.isNaN(data.getTime())) return 'mês selecionado';

      const mesAno = data.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
      return mesAno.charAt(0).toUpperCase() + mesAno.slice(1);
    }

    function calcularMesesRestantes(mesReferencia, encerramentoPrevisto) {
      const ref = String(mesReferencia || '').match(/^(\d{4})-(\d{2})$/);
      const enc = String(encerramentoPrevisto || '').slice(0, 10).match(/^(\d{4})-(\d{2})-(\d{2})$/);
      if (!ref || !enc) return '—';

      const anoRef = Number(ref[1]);
      const mesRef = Number(ref[2]);
      const anoEnc = Number(enc[1]);
      const mesEnc = Number(enc[2]);

      const diff = (anoEnc - anoRef) * 12 + (mesEnc - mesRef);
      if (!Number.isFinite(diff)) return '—';

      const mesesRestantes = Math.max(0, diff);
      return `${mesesRestantes} mês(es)`;
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

    function setCurrencyInputValue(id, amount) {
      const el = document.getElementById(id);
      if (!el) return;

      const v = Number(amount || 0);
      if (!Number.isFinite(v) || v <= 0) {
        el.value = '';
        el.dataset.value = '';
        return;
      }

      el.dataset.value = v.toFixed(2);
      el.value = v.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
      });
    }

    async function salvarModal() {
      const descricao = document.getElementById('m-descricao').value.trim();
      const primeira = normalizarMes(document.getElementById('m-primeira').value);
      const duracao = Number(document.getElementById('m-duracao').value || 0);
      const valorParcela = Number(document.getElementById('m-valor').dataset.value || 0);
      const msg = document.getElementById('modal-msg');

      if (!descricao || !primeira || duracao <= 0 || valorParcela <= 0) {
        msg.textContent = 'Preencha descrição, mês da primeira cobrança, duração e valor da parcela.';
        msg.style.color = 'var(--danger)';
        return;
      }

      try {
        const payload = {
          descricao,
          primeira_cobranca_mes: primeira,
          duracao_meses: duracao,
          valor_parcela: valorParcela,
        };

        const method = editingId ? 'PATCH' : 'POST';
        if (editingId) payload.id = editingId;

        const res = await fetch(_apiBase() + '/pierre/despesas_previstas.php', {
          method,
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });

        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Erro');

        msg.textContent = editingId
          ? 'Despesa prevista atualizada com sucesso.'
          : 'Despesa prevista cadastrada com sucesso.';
        msg.style.color = '#16a34a';

        document.getElementById('m-descricao').value = '';
        document.getElementById('m-primeira').value = '';
        document.getElementById('m-duracao').value = '';
        document.getElementById('m-valor').value = '';
        document.getElementById('m-valor').dataset.value = '';
        editingId = null;

        await carregar();
        setTimeout(fecharModal, 450);
      } catch (_) {
        msg.textContent = _.message || 'Não foi possível cadastrar. Verifique os campos e tente novamente.';
        msg.style.color = 'var(--danger)';
      }
    }

    async function excluirDespesa(id) {
      const confirmou = await BFApp.modalConfirm(
        'Deseja excluir esta despesa prevista?',
        'Confirmar exclusão'
      );
      if (!confirmou) {
        return;
      }

      try {
        const res = await fetch(_apiBase() + '/pierre/despesas_previstas.php', {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id }),
        });

        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Erro ao excluir');

        await carregar();
      } catch (e) {
        await BFApp.modalAlert(e.message || 'Não foi possível excluir a despesa prevista.', 'Erro');
      }
    }

    async function carregar() {
      const filtroMesEl = document.getElementById('filtro-mes');
      const mesReferencia = normalizarMes(filtroMesEl ? filtroMesEl.value : '') || getMesAtualReferencia();
      if (filtroMesEl && filtroMesEl.value !== mesReferencia) {
        filtroMesEl.value = mesReferencia;
      }

      const url = new URL(_apiBase() + '/pierre/despesas_previstas.php');
      url.searchParams.set('mes_referencia', mesReferencia);
      const res = await fetch(url.toString(), { cache: 'no-store' });
      const json = await res.json();
      const despesas = json.success ? (json.data.despesas_previstas || []) : [];

      const tbody = document.getElementById('tbody');
      tbody.innerHTML = '';

      let encerramAno = 0;
      const anoAtual = new Date().getFullYear();
      let totalMesAtual = 0;

      despesas.forEach((d) => {
        const encerramento = d.encerramento_previsto ? new Date(d.encerramento_previsto) : null;
        if (encerramento && !Number.isNaN(encerramento.getTime()) && encerramento.getFullYear() === anoAtual) {
          encerramAno += 1;
        }

        totalMesAtual += Number(d.valor_parcela || 0);

        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td></td>
          <td></td>
          <td></td>
          <td></td>
          <td></td>
          <td></td>
          <td><div class="actions-cell"></div></td>
        `;

        tr.children[0].textContent = d.descricao || '—';
        tr.children[0].setAttribute('data-label', 'Descrição');
        tr.children[1].textContent = formatDate(d.primeira_cobranca);
        tr.children[1].setAttribute('data-label', 'Primeira cobrança');
        tr.children[2].textContent = formatBRL(Number(d.valor_parcela || 0));
        tr.children[2].setAttribute('data-label', 'Parcela');
        tr.children[3].textContent = `${Number(d.duracao_meses || 0)} mês(es)`;
        tr.children[3].setAttribute('data-label', 'Duração');
        tr.children[4].textContent = formatDate(d.encerramento_previsto);
        tr.children[4].setAttribute('data-label', 'Mês de encerramento');
        tr.children[5].textContent = calcularMesesRestantes(mesReferencia, d.encerramento_previsto);
        tr.children[5].setAttribute('data-label', 'Meses restantes');
        tr.children[6].setAttribute('data-label', 'Ações');

        const actionsWrap = tr.children[6].querySelector('.actions-cell');
        const btnEditar = document.createElement('button');
        btnEditar.className = 'btn small outline';
        btnEditar.textContent = 'Editar';
        btnEditar.addEventListener('click', () => {
          abrirModalEdicao(
            String(d.id || ''),
            String(d.descricao || ''),
            String(d.primeira_cobranca || ''),
            Number(d.duracao_meses || 0),
            Number(d.valor_parcela || 0)
          );
        });

        const btnExcluir = document.createElement('button');
        btnExcluir.className = 'btn small danger';
        btnExcluir.textContent = 'Excluir';
        btnExcluir.addEventListener('click', () => excluirDespesa(String(d.id || '')));

        actionsWrap.appendChild(btnEditar);
        actionsWrap.appendChild(btnExcluir);
        tbody.appendChild(tr);
      });

      document.getElementById('sum-total').textContent = String(despesas.length);
      document.getElementById('sum-mes-label').textContent = `Total previsto em ${formatMesLabel(mesReferencia)}`;
      document.getElementById('sum-mes-atual').textContent = formatBRL(totalMesAtual);
      document.getElementById('sum-ano').textContent = String(encerramAno);

      document.getElementById('empty').style.display = despesas.length ? 'none' : 'block';
    }

    bindCurrencyInput('m-valor');
    const filtroMes = document.getElementById('filtro-mes');
    if (filtroMes) {
      filtroMes.value = getMesAtualReferencia();
      filtroMes.addEventListener('change', carregar);
    }
    carregar();
  </script>

</body>
</html>
