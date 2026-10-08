<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Investimentos</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <script src="../../js/app-runtime.js"></script>
  <script src="../../js/pierre-sync.js"></script>
  <style>
    .panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-card); padding: 0.95rem; }
    .btn { min-height: 44px; padding: 0 1rem; border-radius: var(--radius-sm); border: 1.5px solid var(--primary); background: var(--primary); color: #fff; font-size: 0.86rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
    .btn.outline { background: var(--bg-input); color: var(--text-body); border-color: var(--border); }
    .btn.small { min-height: 38px; padding: 0 0.8rem; font-size: 0.8rem; }
    .total { margin-bottom: 0.9rem; }
    .total small { display: block; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-muted); }
    .total .big { font-size: 1.7rem; font-weight: 800; color: var(--text-heading); }
    .aviso { font-size: 0.78rem; color: var(--text-muted); line-height: 1.45; margin-top: 0.5rem; }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 0.9rem; }
    .conta { display: flex; flex-direction: column; gap: 0.7rem; }
    .conta-top { display: flex; align-items: center; gap: 0.6rem; }
    .conta-top img { width: 28px; height: 28px; border-radius: 8px; background: #fff; padding: 2px; }
    .conta-nome { font-weight: 800; color: var(--text-heading); font-size: 0.98rem; }
    .conta-sub { font-size: 0.74rem; color: var(--text-muted); }
    .destaque { background: var(--bg-input); border: 1px solid var(--border); border-radius: 14px; padding: 0.7rem 0.8rem; }
    .destaque small { display: block; font-size: 0.66rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-muted); }
    .destaque .big { font-size: 1.35rem; font-weight: 800; color: var(--text-heading); }
    .destaque .fonte { font-size: 0.72rem; color: var(--text-muted); margin-top: 0.15rem; }
    .linhas { font-size: 0.82rem; }
    .linha { display: flex; justify-content: space-between; padding: 0.3rem 0; border-bottom: 1px solid var(--border); color: var(--text-muted); }
    .linha:last-child { border-bottom: none; }
    .linha b { color: var(--text-heading); }
    .linha b.pos { color: var(--success); }
    .alerta { font-size: 0.8rem; color: #ffb4b4; background: var(--danger-light); border: 1px solid rgba(217,79,79,0.5); border-radius: 12px; padding: 0.55rem 0.7rem; }
    .empty { padding: 1.2rem 0.3rem; color: var(--text-muted); font-size: 0.88rem; }
    .overlay { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(8,12,20,0.7); z-index: 50; padding: 1rem; }
    .overlay.open { display: flex; }
    .sheet { width: 100%; max-width: 480px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 20px; padding: 1.1rem; box-shadow: 0 22px 44px rgba(0,0,0,0.5); }
    .sheet-title { font-size: 1rem; font-weight: 800; color: var(--text-heading); margin-bottom: 0.8rem; }
    .field { display: flex; flex-direction: column; gap: 0.3rem; margin-bottom: 0.75rem; }
    .label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); font-weight: 700; }
    .input { min-height: 46px; padding: 0 0.75rem; width: 100%; border: 1.5px solid var(--border); border-radius: var(--radius-sm); background: var(--bg-input); color: var(--text-body); font: inherit; font-size: 16px; outline: none; }
    .hint { font-size: 0.74rem; color: var(--text-muted); }
    .sheet-actions { display: flex; gap: 0.5rem; margin-top: 0.6rem; }
    .sheet-actions .btn { flex: 1; }
    .sheet-msg { min-height: 1rem; margin-top: 0.4rem; font-size: 0.8rem; color: var(--danger); }
    @media (max-width: 640px) {
      .inner-page { padding: 1rem 0.85rem; padding-bottom: 2rem; }
      .grid { grid-template-columns: 1fr; }
      .overlay { align-items: flex-end; padding: 0; }
      .sheet { max-width: none; border-radius: 22px 22px 0 0; padding-bottom: 1.2rem; }
    }
  </style>
</head>
<body class="inner-page tone-metas">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Investimentos</h1>
      <p>O que você tem investido em cada conta</p>
    </div>
    <div class="page-header-actions">
      <button class="btn-sync secondary" id="btn-sync" onclick="PierreSync.atualizar(carregar, this, { escopo: 'contas' })">↻ Atualizar</button>
    </div>
  </div>

  <div id="topo"></div>
  <div class="grid" id="contas"></div>

  <div class="overlay" id="ov">
    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="ov-title">
      <div class="sheet-title" id="ov-title">Saldo investido</div>
      <div class="field">
        <label class="label" for="ov-valor">Quanto você tem investido nesta conta hoje</label>
        <input class="input" id="ov-valor" type="number" inputmode="decimal" step="0.01" min="0" placeholder="0,00" />
        <span class="hint">Veja o valor no app do banco. Deixe em branco para voltar a usar só o que a Pierre informa.</span>
      </div>
      <div class="sheet-msg" id="ov-msg"></div>
      <div class="sheet-actions">
        <button class="btn outline" id="ov-cancel">Cancelar</button>
        <button class="btn" id="ov-save">Salvar</button>
      </div>
    </div>
  </div>

  <script>
    const $ = (id) => document.getElementById(id);
    const brl = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
    function apiBase() {
      const { origin, pathname } = window.location;
      const root = pathname.replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '').replace(/\/[^/]*\.(?:html|php)$/, '');
      return origin + root + '/backend/api';
    }
    async function api(body) {
      const opts = body
        ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), cache: 'no-store' }
        : { method: 'GET', cache: 'no-store' };
      const res = await fetch(apiBase() + '/pierre/investimentos.php', opts);
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'Erro ao falar com o servidor.');
      return json.data;
    }
    function el(tag, cls, text) { const e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }
    function linha(rotulo, valor, pos) {
      const l = el('div', 'linha'); const b = el('b', pos ? 'pos' : '', valor);
      l.append(el('span', null, rotulo), b); return l;
    }

    let contas = [];
    let editando = null;

    async function carregar() {
      let dados;
      try { dados = await api(); } catch (e) { BFApp.modalAlert(e.message, 'Investimentos'); return; }
      contas = dados.contas;

      const topo = $('topo'); topo.textContent = '';
      const t = el('div', 'panel total');
      t.append(el('small', null, 'Total investido'), el('div', 'big', brl(dados.total)));
      t.appendChild(el('div', 'aviso', 'A Pierre não informa o saldo dos seus investimentos: ela só mostra o dinheiro que rende na própria conta e as movimentações. Para o total ficar certo, toque em "Informar saldo" em cada conta e digite o valor do app do banco.'));
      topo.appendChild(t);

      const box = $('contas'); box.textContent = '';
      if (!contas.length) { box.appendChild(el('div', 'empty', 'Nenhuma conta bancária sincronizada ainda. Toque em "Atualizar".')); return; }
      contas.forEach((c) => box.appendChild(cartao(c)));
    }

    function cartao(c) {
      const card = el('div', 'panel conta');
      const top = el('div', 'conta-top');
      if (c.icone_url) { const i = document.createElement('img'); i.src = c.icone_url; i.alt = ''; i.loading = 'lazy'; top.appendChild(i); }
      const nomes = el('div'); nomes.append(el('div', 'conta-nome', c.banco), el('div', 'conta-sub', c.conta)); top.appendChild(nomes);
      card.appendChild(top);

      const usaManual = c.saldo_informado !== null;
      const valor = usaManual ? c.saldo_informado : c.saldo_api;
      const d = el('div', 'destaque');
      d.append(el('small', null, 'Investido'), el('div', 'big', brl(valor)),
        el('div', 'fonte', usaManual ? 'Informado por você' + (c.informado_em ? ' em ' + new Date(c.informado_em).toLocaleDateString('pt-BR') : '') : 'Informado pela Pierre (saldo que rende na conta)'));
      card.appendChild(d);

      if (c.cheque_especial_usado > 0) card.appendChild(el('div', 'alerta', `Cheque especial em uso: ${brl(c.cheque_especial_usado)}. Isso é dívida, não investimento.`));

      const linhas = el('div', 'linhas');
      c.reservas.filter((r) => r.valor > 0).forEach((r) => linhas.appendChild(linha('Reserva · ' + r.nome, brl(r.valor))));
      if (c.aplicado_180d || c.resgatado_180d) {
        linhas.appendChild(linha('Aplicado (180 dias)', brl(c.aplicado_180d)));
        linhas.appendChild(linha('Resgatado (180 dias)', brl(c.resgatado_180d)));
      }
      if (c.rendimentos_180d) linhas.appendChild(linha('Rendimentos creditados (180 dias)', brl(c.rendimentos_180d), true));
      if (linhas.children.length) card.appendChild(linhas);

      const b = el('button', 'btn outline small', 'Informar saldo'); b.type = 'button';
      b.addEventListener('click', () => abrir(c));
      card.appendChild(b);
      return card;
    }

    function abrir(c) {
      editando = c;
      $('ov-title').textContent = 'Saldo investido · ' + c.banco;
      $('ov-valor').value = c.saldo_informado !== null ? c.saldo_informado : '';
      $('ov-msg').textContent = '';
      $('ov').classList.add('open');
      setTimeout(() => $('ov-valor').focus(), 50);
    }
    async function salvar() {
      const raw = $('ov-valor').value.trim();
      try {
        await api({ acao: 'salvar', conta_id: editando.conta_id, valor: raw === '' ? null : parseFloat(raw.replace(',', '.')) });
        $('ov').classList.remove('open');
        await carregar();
      } catch (e) { $('ov-msg').textContent = e.message; }
    }
    $('ov-cancel').addEventListener('click', () => $('ov').classList.remove('open'));
    $('ov-save').addEventListener('click', salvar);
    $('ov').addEventListener('click', (e) => { if (e.target === $('ov')) $('ov').classList.remove('open'); });

    carregar();
  </script>
</body>
</html>
