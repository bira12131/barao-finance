<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Empréstimos</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <script src="../../js/app-runtime.js"></script>
  <style>
    .panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-card); padding: 0.95rem; }
    .btn { min-height: 44px; padding: 0 1rem; border-radius: var(--radius-sm); border: 1.5px solid var(--primary); background: var(--primary); color: #fff; font-size: 0.86rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
    .btn.outline { background: var(--bg-input); color: var(--text-body); border-color: var(--border); }
    .btn.danger { background: var(--danger-light); color: #ff9c9c; border-color: rgba(217,79,79,0.5); }
    .btn.small { min-height: 38px; padding: 0 0.8rem; font-size: 0.8rem; }
    .kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem; margin-bottom: 0.9rem; }
    .kpi { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 0.6rem 0.65rem; }
    .kpi small { display: block; font-size: 0.6rem; letter-spacing: 0.06em; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 0.2rem; }
    .kpi b { font-size: 0.95rem; color: var(--text-heading); }
    .h { margin: 1.1rem 0.2rem 0.55rem; font-size: 0.78rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: color-mix(in srgb, var(--page-accent) 55%, var(--text-muted)); }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 0.9rem; }
    .contrato { display: flex; flex-direction: column; gap: 0.7rem; }
    .c-top { display: flex; justify-content: space-between; gap: 0.6rem; align-items: flex-start; }
    .c-nome { font-size: 1.02rem; font-weight: 800; color: var(--text-heading); }
    .c-sub { font-size: 0.76rem; color: var(--text-muted); margin-top: 0.1rem; }
    .c-parcela { font-size: 1.1rem; font-weight: 800; color: var(--text-heading); white-space: nowrap; text-align: right; }
    .c-parcela small { display: block; font-size: 0.66rem; font-weight: 600; color: var(--text-muted); }
    .bar { height: 8px; border-radius: 99px; background: var(--bg-input); overflow: hidden; }
    .bar > i { display: block; height: 100%; border-radius: 99px; background: var(--page-accent); }
    .prog { display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-muted); margin-top: 0.3rem; }
    .prog b { color: var(--text-heading); }
    .datas { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
    .datas div { background: var(--bg-input); border: 1px solid var(--border); border-radius: 12px; padding: 0.5rem 0.65rem; font-size: 0.72rem; color: var(--text-muted); }
    .datas b { display: block; font-size: 0.86rem; color: var(--text-heading); margin-top: 0.1rem; }
    .adiant { border-top: 1px solid var(--border); padding-top: 0.6rem; }
    .adiant-h { font-size: 0.68rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.3rem; }
    .adiant-i { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: var(--text-body); padding: 0.25rem 0; }
    .adiant-i span { flex: 1; }
    .adiant-i b { color: var(--text-heading); }
    .adiant-i button { width: 36px; height: 36px; border-radius: 10px; border: 1.5px solid var(--border); background: var(--bg-input); color: var(--text-muted); cursor: pointer; }
    .economia { color: var(--success); font-weight: 700; }
    .acoes { display: flex; gap: 0.5rem; }
    .acoes .btn { flex: 1; }
    .nota { font-size: 0.74rem; color: var(--text-muted); line-height: 1.45; }
    .sug { display: flex; flex-direction: column; gap: 0.5rem; }
    .sug-t { font-weight: 800; color: var(--text-heading); }
    .sug-i { font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; }
    .empty { padding: 1rem 0.3rem; color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; }
    .fab { position: fixed; right: 1.1rem; bottom: 0.9rem; width: 54px; height: 54px; border-radius: 50%; border: none; background: var(--primary); color: #fff; font-size: 1.9rem; line-height: 1; box-shadow: 0 10px 24px rgba(0,0,0,0.45); cursor: pointer; z-index: 30; }
    .overlay { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(8,12,20,0.7); z-index: 50; padding: 1rem; }
    .overlay.open { display: flex; }
    .sheet { width: 100%; max-width: 520px; max-height: 92vh; overflow-y: auto; background: var(--bg-card); border: 1px solid var(--border); border-radius: 20px; padding: 1.1rem; box-shadow: 0 22px 44px rgba(0,0,0,0.5); }
    .sheet-title { font-size: 1rem; font-weight: 800; color: var(--text-heading); margin-bottom: 0.8rem; }
    .row2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.7rem; }
    .field { display: flex; flex-direction: column; gap: 0.3rem; margin-bottom: 0.75rem; }
    .label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); font-weight: 700; }
    .input { min-height: 46px; padding: 0 0.75rem; width: 100%; border: 1.5px solid var(--border); border-radius: var(--radius-sm); background: var(--bg-input); color: var(--text-body); font: inherit; font-size: 16px; outline: none; }
    .hint { font-size: 0.74rem; color: var(--text-muted); }
    .sheet-actions { display: flex; gap: 0.5rem; margin-top: 0.6rem; }
    .sheet-actions .btn { flex: 1; }
    .sheet-msg { min-height: 1rem; margin-top: 0.4rem; font-size: 0.8rem; color: var(--danger); }
    @media (max-width: 640px) {
      .inner-page { padding: 1rem 0.85rem; padding-bottom: 5.8rem; }
      .grid { grid-template-columns: 1fr; }
      .overlay { align-items: flex-end; padding: 0; }
      .sheet { max-width: none; border-radius: 22px 22px 0 0; padding-bottom: 1.2rem; }
    }
    @media (min-width: 900px) { .fab { bottom: 1.6rem; right: 2rem; } }
  </style>
</head>
<body class="inner-page tone-despesas">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Empréstimos</h1>
      <p>Contratos, parcelas e quando terminam. Entram sozinhos nas despesas previstas.</p>
    </div>
  </div>

  <div class="kpis" id="kpis"></div>
  <div class="h">Seus contratos</div>
  <div class="grid" id="contratos"></div>
  <div id="sugestoes-wrap" style="display:none">
    <div class="h">Encontrados nos seus pagamentos</div>
    <div class="grid" id="sugestoes"></div>
  </div>

  <button class="fab" id="fab" aria-label="Novo contrato">+</button>

  <div class="overlay" id="ov">
    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="ov-title">
      <div class="sheet-title" id="ov-title">Novo contrato</div>
      <div class="field">
        <label class="label" for="f-nome">Nome do contrato</label>
        <input class="input" id="f-nome" type="text" maxlength="120" placeholder="Ex.: Financiamento do carro" autocomplete="off" />
      </div>
      <div class="field">
        <label class="label" for="f-credor">Banco / credor (opcional)</label>
        <input class="input" id="f-credor" type="text" maxlength="120" placeholder="Ex.: Santander" autocomplete="off" />
      </div>
      <div class="row2">
        <div class="field">
          <label class="label" for="f-valor">Valor da parcela</label>
          <input class="input" id="f-valor" type="number" inputmode="decimal" step="0.01" min="0" placeholder="0,00" />
        </div>
        <div class="field">
          <label class="label" for="f-total">Total de parcelas</label>
          <input class="input" id="f-total" type="number" inputmode="numeric" step="1" min="1" max="480" placeholder="Ex.: 48" />
        </div>
      </div>
      <div class="field">
        <label class="label" for="f-inicio">Data da 1ª parcela</label>
        <input class="input" id="f-inicio" type="date" />
        <span class="hint">O dia dessa data vira o vencimento mensal. O término é calculado a partir dela.</span>
      </div>
      <div class="field">
        <label class="label" for="f-padrao">Como o pagamento aparece no extrato</label>
        <input class="input" id="f-padrao" type="text" maxlength="120" placeholder="Ex.: santander sociedade de credito" autocomplete="off" />
        <span class="hint">Usado para contar quantas parcelas você já pagou. Em branco, conto pelo calendário.</span>
      </div>
      <div class="sheet-msg" id="f-msg"></div>
      <div class="sheet-actions">
        <button class="btn danger" id="f-del" style="display:none">Excluir</button>
        <button class="btn outline" id="f-cancel">Cancelar</button>
        <button class="btn" id="f-save">Salvar</button>
      </div>
    </div>
  </div>

  <div class="overlay" id="ov-ad">
    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="ad-title">
      <div class="sheet-title" id="ad-title">Adiantar parcelas</div>
      <div class="row2">
        <div class="field">
          <label class="label" for="ad-parcelas">Parcelas adiantadas</label>
          <input class="input" id="ad-parcelas" type="number" inputmode="numeric" step="1" min="0" placeholder="Ex.: 2" />
        </div>
        <div class="field">
          <label class="label" for="ad-valor">Valor pago</label>
          <input class="input" id="ad-valor" type="number" inputmode="decimal" step="0.01" min="0" placeholder="0,00" />
        </div>
      </div>
      <div class="field">
        <label class="label" for="ad-data">Data do pagamento</label>
        <input class="input" id="ad-data" type="date" />
        <span class="hint">As parcelas adiantadas quitam o contrato de trás para frente, e o término chega mais cedo. Se o banco deu desconto, o valor pago pode ser menor que parcelas × parcela. Se pagou um valor avulso e não sabe quantas parcelas foram, deixe as parcelas em branco: o valor é abatido do saldo.</span>
      </div>
      <div class="sheet-msg" id="ad-msg"></div>
      <div class="sheet-actions">
        <button class="btn outline" id="ad-cancel">Cancelar</button>
        <button class="btn" id="ad-save">Registrar</button>
      </div>
    </div>
  </div>

  <script>
    const $ = (id) => document.getElementById(id);
    const brl = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
    const num = (v) => { const n = parseFloat(String(v).replace(',', '.')); return Number.isFinite(n) ? n : 0; };
    const dataBR = (iso) => { if (!iso) return '—'; const [y, m, d] = String(iso).slice(0, 10).split('-'); return `${d}/${m}/${y}`; };
    function apiBase() {
      const { origin, pathname } = window.location;
      const root = pathname.replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '').replace(/\/[^/]*\.(?:html|php)$/, '');
      return origin + root + '/backend/api';
    }
    async function api(body) {
      const opts = body
        ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), cache: 'no-store' }
        : { method: 'GET', cache: 'no-store' };
      const res = await fetch(apiBase() + '/emprestimos.php', opts);
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'Erro ao falar com o servidor.');
      return json.data;
    }
    function el(tag, cls, text) { const e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }
    function kpi(rotulo, valor) { const k = el('div', 'kpi'); k.append(el('small', null, rotulo), el('b', null, valor)); return k; }

    let editando = null;

    async function carregar() {
      let d;
      try { d = await api(); } catch (e) { BFApp.modalAlert(e.message, 'Empréstimos'); return; }

      $('kpis').textContent = '';
      $('kpis').append(kpi('Parcelas por mês', brl(d.resumo.parcela_mensal_total)), kpi('Ainda a pagar', brl(d.resumo.valor_restante_total)), kpi('Contratos ativos', String(d.resumo.contratos_ativos)));

      const box = $('contratos'); box.textContent = '';
      if (!d.contratos.length) {
        const e = el('div', 'empty', 'Nenhum contrato cadastrado. A Pierre não informa os dados do contrato (parcelas e início), então confirme abaixo os pagamentos que encontrei ou toque em + para cadastrar.');
        e.style.gridColumn = '1 / -1'; box.appendChild(e);
      }
      d.contratos.forEach((c) => box.appendChild(cartao(c)));

      const sw = $('sugestoes-wrap'); const sb = $('sugestoes'); sb.textContent = '';
      sw.style.display = d.sugestoes.length ? '' : 'none';
      d.sugestoes.forEach((s) => sb.appendChild(sugestao(s)));
    }

    function cartao(c) {
      const card = el('div', 'panel contrato');
      const top = el('div', 'c-top');
      const l = el('div'); l.append(el('div', 'c-nome', c.nome), el('div', 'c-sub', c.credor || 'Credor não informado'));
      const p = el('div', 'c-parcela', brl(c.valor_parcela)); p.appendChild(el('small', null, 'por mês'));
      top.append(l, p); card.appendChild(top);

      const prog = el('div'); const bar = el('div', 'bar'); const f = document.createElement('i'); f.style.width = c.progresso + '%'; bar.appendChild(f);
      const line = el('div', 'prog');
      const esq = el('span');
      esq.append(document.createTextNode('Pagas '), el('b', null, `${c.parcelas_pagas}`));
      if (c.parcelas_adiantadas > 0) esq.append(document.createTextNode(' + '), el('b', null, `${c.parcelas_adiantadas} adiantada(s)`));
      esq.append(document.createTextNode(` de ${c.total_parcelas}`));
      line.append(esq, el('span', null, c.quitado ? 'Quitado' : `Faltam ${c.parcelas_restantes}`));
      prog.append(bar, line); card.appendChild(prog);

      const datas = el('div', 'datas');
      [['Começou em', dataBR(c.primeira_parcela)], ['Termina em', dataBR(c.ultima_parcela)],
       ['Próxima parcela', c.proxima_parcela ? dataBR(c.proxima_parcela) : '—'], ['Falta pagar', brl(c.valor_restante)]]
        .forEach(([r, v]) => { const d = el('div'); d.append(document.createTextNode(r), el('b', null, v)); datas.appendChild(d); });
      card.appendChild(datas);

      if (c.parcela_final !== null) card.appendChild(el('div', 'nota', `A última parcela fica em ${brl(c.parcela_final)} por causa dos adiantamentos.`));
      if (c.ultima_parcela !== c.termino_original) card.appendChild(el('div', 'nota', `Término original: ${dataBR(c.termino_original)}. Com os adiantamentos, termina em ${dataBR(c.ultima_parcela)}.`));

      if (c.adiantamentos.length) {
        const box = el('div', 'adiant');
        const h = el('div', 'adiant-h', 'Adiantamentos');
        if (c.economia > 0) { h.append(document.createTextNode(' · economia '), el('span', 'economia', brl(c.economia))); }
        box.appendChild(h);
        c.adiantamentos.forEach((a) => {
          const row = el('div', 'adiant-i');
          const t = el('span');
          t.append(document.createTextNode(dataBR(a.data) + ' · '), el('b', null, brl(a.valor_pago)),
            document.createTextNode(a.parcelas_quitadas > 0 ? ` · ${a.parcelas_quitadas} parcela(s)` : ' · abatido do saldo'));
          const x = el('button', null); x.appendChild(Icone.el('x')); x.type = 'button'; x.setAttribute('aria-label', 'Remover adiantamento');
          x.addEventListener('click', () => removerAdiantamento(a.id));
          row.append(t, x); box.appendChild(row);
        });
        card.appendChild(box);
      }

      card.appendChild(el('div', 'nota', c.origem_pagas === 'extrato'
        ? `Parcelas pagas: ${c.parcelas_pagas}, contadas pelo seu extrato (o calendário indicaria ${c.pagas_calendario}).`
        : `Parcelas pagas: ${c.parcelas_pagas}, contadas pelos vencimentos que já passaram` + (c.pagas_extrato ? ` (o extrato mostra ${c.pagas_extrato} pagamento(s) recentes).` : '. Se pagou fora de dia, ajuste a data da 1ª parcela.')));

      const acoes = el('div', 'acoes');
      if (!c.quitado) {
        const ad = el('button', 'btn small', 'Adiantar parcelas'); ad.type = 'button';
        ad.addEventListener('click', () => abrirAdiantamento(c)); acoes.appendChild(ad);
      }
      const b = el('button', 'btn outline small', 'Editar'); b.type = 'button';
      b.addEventListener('click', () => abrir(c)); acoes.appendChild(b);
      card.appendChild(acoes);
      return card;
    }

    function sugestao(s) {
      const card = el('div', 'panel sug');
      card.appendChild(el('div', 'sug-t', s.titulo));
      const faixa = s.valor_min === s.valor_max ? brl(s.valor_ultimo) : `${brl(s.valor_min)} a ${brl(s.valor_max)}`;
      card.appendChild(el('div', 'sug-i', `${s.meses} meses com pagamento · ${faixa} · último em ${dataBR(s.ultima_observada)}` + (s.conta_nome ? ` · ${s.conta_nome}` : '') + `. Visto desde ${dataBR(s.primeira_observada)} (o contrato pode ser anterior).`));
      const b = el('button', 'btn small', 'Cadastrar contrato'); b.type = 'button';
      b.addEventListener('click', () => abrir(null, {
        nome: s.titulo, valor_parcela: s.valor_ultimo, primeira_parcela: s.primeira_observada, padrao_busca: s.padrao_busca,
      }));
      card.appendChild(b);
      return card;
    }

    function abrir(c, pre) {
      editando = c || null;
      const v = c || pre || {};
      $('ov-title').textContent = c ? 'Editar contrato' : 'Novo contrato';
      $('f-del').style.display = c ? '' : 'none';
      $('f-nome').value = v.nome || '';
      $('f-credor').value = v.credor || '';
      $('f-valor').value = v.valor_parcela || '';
      $('f-total').value = c ? c.total_parcelas : '';
      $('f-inicio').value = v.primeira_parcela ? String(v.primeira_parcela).slice(0, 10) : '';
      $('f-padrao').value = v.padrao_busca || '';
      $('f-msg').textContent = '';
      $('ov').classList.add('open');
      setTimeout(() => $(pre && !c ? 'f-total' : 'f-nome').focus(), 50);
    }
    const fechar = () => { $('ov').classList.remove('open'); editando = null; };

    async function salvar() {
      const corpo = {
        acao: editando ? 'editar' : 'criar', id: editando ? editando.id : undefined,
        nome: $('f-nome').value.trim(), credor: $('f-credor').value.trim(),
        valor_parcela: num($('f-valor').value), total_parcelas: parseInt($('f-total').value, 10) || 0,
        primeira_parcela: $('f-inicio').value, padrao_busca: $('f-padrao').value.trim(),
      };
      if (!corpo.nome || corpo.valor_parcela <= 0 || corpo.total_parcelas < 1 || !corpo.primeira_parcela) {
        $('f-msg').textContent = 'Preencha nome, valor da parcela, total de parcelas e data da 1ª parcela.';
        return;
      }
      $('f-save').disabled = true;
      try { await api(corpo); fechar(); await carregar(); }
      catch (e) { $('f-msg').textContent = e.message; }
      finally { $('f-save').disabled = false; }
    }

    async function excluir() {
      if (!editando) return;
      if (!(await BFApp.modalConfirm(`Excluir o contrato "${editando.nome}"? Ele deixa de aparecer nas despesas previstas.`, 'Excluir contrato'))) return;
      try { await api({ acao: 'excluir', id: editando.id }); fechar(); await carregar(); }
      catch (e) { $('f-msg').textContent = e.message; }
    }

    /* ── Adiantamento ── */
    let contratoAd = null;
    function abrirAdiantamento(c) {
      contratoAd = c;
      $('ad-title').textContent = 'Adiantar parcelas · ' + c.nome;
      $('ad-parcelas').value = '';
      $('ad-valor').value = '';
      $('ad-data').value = new Date().toISOString().slice(0, 10);
      $('ad-msg').textContent = '';
      $('ov-ad').classList.add('open');
      setTimeout(() => $('ad-parcelas').focus(), 50);
    }
    async function salvarAdiantamento() {
      const parcelas = parseInt($('ad-parcelas').value, 10) || 0;
      const valor = num($('ad-valor').value);
      if (parcelas <= 0 && valor <= 0) { $('ad-msg').textContent = 'Informe as parcelas adiantadas e/ou o valor pago.'; return; }
      $('ad-save').disabled = true;
      try {
        await api({ acao: 'adiantar', id: contratoAd.id, data: $('ad-data').value, parcelas_quitadas: parcelas, valor_pago: valor > 0 ? valor : null });
        $('ov-ad').classList.remove('open');
        await carregar();
      } catch (e) { $('ad-msg').textContent = e.message; }
      finally { $('ad-save').disabled = false; }
    }
    async function removerAdiantamento(id) {
      if (!(await BFApp.modalConfirm('Remover este adiantamento? O saldo e o término do contrato voltam como antes.', 'Remover adiantamento'))) return;
      try { await api({ acao: 'remover_adiantamento', adiantamento_id: id }); await carregar(); }
      catch (e) { BFApp.modalAlert(e.message, 'Empréstimos'); }
    }
    $('ad-cancel').addEventListener('click', () => $('ov-ad').classList.remove('open'));
    $('ad-save').addEventListener('click', salvarAdiantamento);
    $('ov-ad').addEventListener('click', (e) => { if (e.target === $('ov-ad')) $('ov-ad').classList.remove('open'); });

    $('fab').addEventListener('click', () => abrir(null));
    $('f-cancel').addEventListener('click', fechar);
    $('f-save').addEventListener('click', salvar);
    $('f-del').addEventListener('click', excluir);
    $('ov').addEventListener('click', (e) => { if (e.target === $('ov')) fechar(); });

    carregar();
  </script>
</body>
</html>
