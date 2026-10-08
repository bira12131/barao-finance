<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Metas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <script src="../../js/app-runtime.js"></script>
  <style>
    .panel {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-card);
      padding: 0.95rem;
      margin-bottom: 0.9rem;
    }
    .panel-title {
      font-size: 0.74rem;
      font-weight: 800;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: color-mix(in srgb, var(--page-accent) 55%, var(--text-muted));
      margin-bottom: 0.7rem;
    }
    .btn {
      min-height: 44px;
      padding: 0 1rem;
      border-radius: var(--radius-sm);
      border: 1.5px solid var(--primary);
      background: var(--primary);
      color: #fff;
      font-size: 0.86rem;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .btn.outline { background: var(--bg-input); color: var(--text-body); border-color: var(--border); }
    .btn.danger  { background: var(--danger-light); color: #ff9c9c; border-color: rgba(217, 79, 79, 0.5); }
    .btn.small   { min-height: 38px; padding: 0 0.8rem; font-size: 0.8rem; }

    /* ── Fôlego mensal ── */
    .kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem; }
    .kpi {
      background: var(--bg-input);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 0.6rem 0.65rem;
      text-align: left;
      color: inherit;
      font: inherit;
    }
    button.kpi { cursor: pointer; }
    .kpi small {
      display: block;
      font-size: 0.6rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      font-weight: 700;
      color: var(--text-muted);
      margin-bottom: 0.2rem;
    }
    .kpi b { font-size: 0.95rem; color: var(--text-heading); }
    .kpi b.pos { color: var(--success); }
    .kpi b.neg { color: var(--danger); }
    .switch { display: flex; align-items: center; gap: 0.7rem; min-height: 44px; margin-top: 0.7rem; font-size: 0.86rem; color: var(--text-body); cursor: pointer; }
    .switch input { width: 22px; height: 22px; accent-color: var(--primary); flex: none; }
    .switch b { color: var(--text-heading); }
    .transf { margin-top: 0.8rem; border-top: 1px solid var(--border); padding-top: 0.6rem; }
    .transf-h { font-size: 0.68rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-muted); margin: 0.5rem 0 0.25rem; }
    .transf-row { display: flex; justify-content: space-between; gap: 0.8rem; font-size: 0.8rem; color: var(--text-body); padding: 0.28rem 0; border-bottom: 1px solid var(--border); }
    .transf-row span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .transf-row b { color: var(--text-heading); white-space: nowrap; }
    .transf-btn { flex: none; min-height: 32px; padding: 0 0.6rem; border-radius: 8px; border: 1.5px solid var(--border); background: var(--bg-input); color: var(--text-muted); font-size: 0.7rem; font-weight: 700; cursor: pointer; }
    .chip-x { border: none; background: transparent; color: var(--text-muted); font-size: 0.8rem; cursor: pointer; padding: 0 0.1rem; min-width: 24px; min-height: 24px; }
    .note { font-size: 0.76rem; color: var(--text-muted); margin-top: 0.6rem; line-height: 1.45; }

    .banner {
      border-radius: 14px;
      padding: 0.8rem 0.9rem;
      margin-bottom: 0.9rem;
      font-size: 0.86rem;
      line-height: 1.45;
      border: 1px solid;
    }
    .banner b { color: var(--text-heading); }
    .banner.viavel   { background: var(--success-light); border-color: rgba(42, 165, 102, 0.5); color: #9fe0bf; }
    .banner.apertado { background: var(--warning-light); border-color: rgba(217, 119, 6, 0.5); color: #f0c98a; }
    .banner.inviavel { background: var(--danger-light);  border-color: rgba(217, 79, 79, 0.5);  color: #ffb4b4; }
    .banner.info     { background: var(--bg-card); border-color: var(--border); color: var(--text-body); }

    .cat-row { margin-bottom: 0.6rem; }
    .cat-top { display: flex; justify-content: space-between; font-size: 0.84rem; margin-bottom: 0.25rem; color: var(--text-body); }
    .cat-top b { color: var(--text-heading); }
    .bar { height: 8px; border-radius: 99px; background: var(--bg-input); overflow: hidden; }
    .bar > i { display: block; height: 100%; border-radius: 99px; background: var(--page-accent); }

    /* ── Metas ── */
    .metas { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 0.9rem; }
    .meta { margin-bottom: 0; display: flex; flex-direction: column; gap: 0.7rem; }
    .meta-top { display: flex; justify-content: space-between; gap: 0.6rem; align-items: flex-start; }
    .meta-nome { font-size: 1.02rem; font-weight: 800; color: var(--text-heading); }
    .meta-prazo { font-size: 0.76rem; color: var(--text-muted); margin-top: 0.1rem; }
    .badge {
      font-size: 0.66rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase;
      padding: 0.25rem 0.55rem; border-radius: 99px; white-space: nowrap; flex: none;
    }
    .badge.viavel   { background: var(--success-light); color: #9fe0bf; }
    .badge.apertado { background: var(--warning-light); color: #f0c98a; }
    .badge.inviavel { background: var(--danger-light);  color: #ffb4b4; }
    .badge.ok       { background: var(--success-light); color: #9fe0bf; }
    .prog-line { display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-muted); margin-top: 0.3rem; }
    .prog-line b { color: var(--text-heading); }
    .chips { display: flex; flex-wrap: wrap; gap: 0.35rem; }
    .chip {
      font-size: 0.74rem; padding: 0.25rem 0.6rem; border-radius: 99px;
      background: var(--bg-input); border: 1px solid var(--border); color: var(--text-body);
    }
    .chip b { color: var(--text-heading); }
    .destaque {
      background: var(--bg-input); border: 1px solid var(--border); border-radius: 14px;
      padding: 0.7rem 0.8rem;
    }
    .destaque small { display: block; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-muted); }
    .destaque .big { font-size: 1.4rem; font-weight: 800; color: var(--text-heading); line-height: 1.2; }
    .destaque .big span { font-size: 0.82rem; font-weight: 600; color: var(--text-muted); }
    .dica { font-size: 0.8rem; color: var(--text-body); line-height: 1.45; margin-top: 0.4rem; }
    .dica b { color: var(--text-heading); }
    .cenarios { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.4rem; margin-top: 0.5rem; }
    .cenarios div { background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 0.45rem 0.6rem; font-size: 0.78rem; color: var(--text-muted); }
    .cenarios b { display: block; font-size: 0.88rem; color: var(--text-heading); }
    .meta-actions { display: flex; gap: 0.5rem; }
    .meta-actions .btn { flex: 1; }
    .empty { padding: 1.2rem 0.3rem; color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; }

    /* ── FAB + sheets ── */
    .fab {
      position: fixed; right: 1.1rem; bottom: 0.9rem;
      width: 54px; height: 54px; border-radius: 50%; border: none;
      background: var(--primary); color: #fff; font-size: 1.9rem; line-height: 1;
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.45); cursor: pointer; z-index: 30;
    }
    .overlay {
      position: fixed; inset: 0; display: none; align-items: center; justify-content: center;
      background: rgba(8, 12, 20, 0.7); z-index: 50; padding: 1rem;
    }
    .overlay.open { display: flex; }
    .sheet {
      width: 100%; max-width: 520px; max-height: 92vh; overflow-y: auto;
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: 20px; box-shadow: 0 22px 44px rgba(0, 0, 0, 0.5); padding: 1.1rem;
    }
    .sheet-title { font-size: 1rem; font-weight: 800; color: var(--text-heading); margin-bottom: 0.8rem; }
    .field { display: flex; flex-direction: column; gap: 0.3rem; margin-bottom: 0.75rem; }
    .label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); font-weight: 700; }
    .input {
      min-height: 46px; padding: 0 0.75rem; width: 100%;
      border: 1.5px solid var(--border); border-radius: var(--radius-sm);
      background: var(--bg-input); color: var(--text-body);
      font: inherit; font-size: 16px; outline: none;
    }
    .input:focus { border-color: var(--primary); }
    .hint { font-size: 0.74rem; color: var(--text-muted); }
    .comp-row { display: grid; grid-template-columns: minmax(0, 1fr) 110px 44px; gap: 0.4rem; margin-bottom: 0.4rem; }
    .comp-row .rm { min-height: 46px; border-radius: var(--radius-sm); border: 1.5px solid var(--border); background: var(--bg-input); color: var(--text-muted); font-size: 1rem; cursor: pointer; }
    .total-line { display: flex; justify-content: space-between; font-size: 0.9rem; margin: 0.4rem 0 0.8rem; color: var(--text-muted); }
    .total-line b { color: var(--text-heading); }
    .tpl { margin-bottom: 0.8rem; }
    .sheet-actions { display: flex; gap: 0.5rem; margin-top: 0.6rem; }
    .sheet-actions .btn { flex: 1; }
    .sheet-msg { min-height: 1rem; margin-top: 0.5rem; font-size: 0.8rem; color: var(--danger); }

    @media (max-width: 640px) {
      .inner-page { padding: 1rem 0.85rem; padding-bottom: 5.8rem; }
      .metas { grid-template-columns: 1fr; }
      .overlay { align-items: flex-end; padding: 0; }
      .sheet {
        max-width: none; border-radius: 22px 22px 0 0;
        padding-bottom: 1.2rem;
      }
      .comp-row { grid-template-columns: minmax(0, 1fr) 96px 44px; }
    }
    @media (min-width: 900px) {
      .fab { bottom: 1.6rem; right: 2rem; }
    }
  </style>
</head>
<body class="inner-page tone-metas">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Metas</h1>
      <p>Defina o que quer conquistar e veja quanto guardar por mês</p>
    </div>
  </div>

  <div id="resumo"></div>
  <div id="situacao"></div>
  <div id="categorias"></div>
  <div class="metas" id="metas"></div>

  <button class="fab" id="fab" aria-label="Nova meta">+</button>

  <!-- Meta -->
  <div class="overlay" id="ov-meta">
    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="meta-title">
      <div class="sheet-title" id="meta-title">Nova meta</div>

      <div class="tpl" id="tpl-wrap">
        <button type="button" class="btn outline small" id="tpl-casa">Usar modelo: Comprar casa</button>
      </div>

      <div class="field">
        <label class="label" for="m-nome">O que você quer conquistar?</label>
        <input class="input" id="m-nome" type="text" maxlength="120" placeholder="Ex.: Comprar casa" autocomplete="off" />
      </div>

      <div class="field">
        <span class="label">Quanto custa (por item)</span>
        <div id="comps"></div>
        <button type="button" class="btn outline small" id="add-comp" style="align-self:flex-start">+ Adicionar item</button>
      </div>
      <div class="total-line"><span>Total da meta</span><b id="m-total">R$ 0,00</b></div>

      <div class="field">
        <label class="label" for="m-inicial">Quanto já guardou</label>
        <input class="input" id="m-inicial" type="number" inputmode="decimal" step="0.01" min="0" placeholder="0,00" />
      </div>

      <div class="field">
        <label class="label" for="m-prazo">Quer conquistar até quando? (opcional)</label>
        <input class="input" id="m-prazo" type="month" />
        <span class="hint">Sem prazo, mostro cenários de 1 a 5 anos e quando você chega com a sua sobra atual.</span>
      </div>

      <div class="sheet-msg" id="meta-msg"></div>
      <div class="sheet-actions">
        <button class="btn danger" id="meta-del" style="display:none">Excluir</button>
        <button class="btn outline" id="meta-cancel">Cancelar</button>
        <button class="btn" id="meta-save">Salvar</button>
      </div>
    </div>
  </div>

  <!-- Aporte -->
  <div class="overlay" id="ov-aporte">
    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="ap-title">
      <div class="sheet-title" id="ap-title">Registrar aporte</div>
      <div class="field">
        <label class="label" for="ap-valor">Valor guardado agora</label>
        <input class="input" id="ap-valor" type="number" inputmode="decimal" step="0.01" placeholder="0,00" />
        <span class="hint">Use valor negativo para registrar uma retirada.</span>
      </div>
      <div class="sheet-msg" id="ap-msg"></div>
      <div class="sheet-actions">
        <button class="btn outline" id="ap-cancel">Cancelar</button>
        <button class="btn" id="ap-save">Registrar</button>
      </div>
    </div>
  </div>

  <!-- Renda -->
  <div class="overlay" id="ov-renda">
    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="rd-title">
      <div class="sheet-title" id="rd-title">Sua renda mensal</div>
      <div class="field">
        <label class="label" for="rd-valor">Quanto entra por mês (líquido)</label>
        <input class="input" id="rd-valor" type="number" inputmode="decimal" step="0.01" min="0" placeholder="0,00" />
        <span class="hint">Usada para calcular quanto sobra para suas metas.</span>
      </div>
      <div class="sheet-msg" id="rd-msg"></div>
      <div class="sheet-actions">
        <button class="btn outline" id="rd-cancel">Cancelar</button>
        <button class="btn" id="rd-save">Salvar</button>
      </div>
    </div>
  </div>

  <script>
    const $ = (id) => document.getElementById(id);
    const brl = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
    const num = (v) => { const n = parseFloat(String(v).replace(',', '.')); return Number.isFinite(n) ? n : 0; };

    function apiBase() {
      const { origin, pathname } = window.location;
      const root = pathname
        .replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '')
        .replace(/\/[^/]*\.(?:html|php)$/, '');
      return origin + root + '/backend/api';
    }

    async function api(body) {
      const opts = body
        ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), cache: 'no-store' }
        : { method: 'GET', cache: 'no-store' };
      const res = await fetch(apiBase() + '/metas.php', opts);
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'Erro ao falar com o servidor.');
      return json.data;
    }

    function mesAno(iso) {
      const [y, m] = String(iso).slice(0, 7).split('-').map(Number);
      const s = new Date(y, m - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
      return s;
    }

    function dataBR(iso) { const [y, m, d] = String(iso).slice(0, 10).split('-'); return `${d}/${m}`; }
    function mesCurto(ym) {
      const [y, m] = String(ym).split('-').map(Number);
      return new Date(y, m - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
    }

    function el(tag, cls, text) {
      const e = document.createElement(tag);
      if (cls) e.className = cls;
      if (text !== undefined) e.textContent = text;
      return e;
    }
    function strong(text) { return el('b', null, text); }
    function frase(parent, ...partes) {
      partes.forEach((p) => parent.append(typeof p === 'string' ? document.createTextNode(p) : p));
      return parent;
    }

    let dados = null;

    async function carregar() {
      try {
        dados = await api();
      } catch (e) {
        BFApp.modalAlert(e.message, 'Metas');
        return;
      }
      desenharResumo();
      desenharSituacao();
      desenharCategorias();
      desenharMetas();
    }

    /* ── Fôlego mensal ── */
    function desenharResumo() {
      const { perfil, analise } = dados;
      const box = $('resumo');
      box.textContent = '';
      const panel = el('div', 'panel');
      panel.appendChild(el('div', 'panel-title', 'Seu fôlego mensal'));

      const kpis = el('div', 'kpis');
      const renda = el('button', 'kpi');
      renda.type = 'button';
      renda.append(el('small', null, 'Renda'), strong(perfil.renda_mensal !== null ? brl(perfil.renda_mensal) : 'Informar'));
      renda.addEventListener('click', () => abrirRenda());

      const gasto = el('div', 'kpi');
      gasto.append(el('small', null, 'Gasto médio'), strong(analise.meses_considerados ? brl(analise.gasto_medio_mensal) : '—'));

      const sobra = el('div', 'kpi');
      const cap = analise.capacidade_mensal;
      const b = strong(cap !== null && analise.meses_considerados ? brl(cap) : '—');
      if (cap !== null && analise.meses_considerados) b.className = cap >= 0 ? 'pos' : 'neg';
      sobra.append(el('small', null, 'Sobra por mês'), b);

      kpis.append(renda, gasto, sobra);
      panel.appendChild(kpis);

      const desde = new Date(analise.analise_desde + 'T12:00:00').toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
      const emp = analise.emprestimos || { contratos: 0, parcela_mensal: 0 };
      let nota;
      if (perfil.renda_mensal === null) nota = 'Informe sua renda mensal para eu calcular quanto sobra para as metas.';
      else if (!analise.meses_considerados) nota = 'Ainda não há gastos sincronizados para calcular a média. Atualize seus dados na tela de Contas.';
      else if (analise.base_gasto === 'mes_atual_parcial') nota = 'Média baseada no mês atual (ainda incompleto); fica mais precisa conforme os meses fecham.';
      else nota = `Gasto médio dos meses completos desde ${desde} (${analise.meses_considerados} mês(es)), sem contar pagamento de fatura (para não duplicar) nem transferências entre suas contas.`;
      if (emp.contratos > 0) nota += ` Inclui ${emp.contratos} empréstimo(s): ${brl(emp.parcela_mensal)} por mês` + (emp.termina_em ? `, até ${mesAno(emp.termina_em)}.` : '.');
      if (analise.investido_total > 0) nota += ` Seus investimentos (${brl(analise.investido_total)}) já contam como guardado nas metas.`;
      panel.appendChild(el('div', 'note', nota));

      // Transferências/PIX para terceiros: ficam FORA do gasto por padrão; aqui você vê o que são e escolhe.
      if (analise.transferencias_mensal > 0) {
        const lbl = el('label', 'switch');
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.checked = !!perfil.contar_transferencias;
        cb.addEventListener('change', async () => {
          try { await api({ acao: 'config', contar_transferencias: cb.checked }); await carregar(); }
          catch (e) { BFApp.modalAlert(e.message, 'Metas'); cb.checked = !cb.checked; }
        });
        const txt = el('span');
        frase(txt, 'Contar transferências e PIX para terceiros como gasto ', strong('(' + brl(analise.transferencias_mensal) + ' por mês)'));
        lbl.append(cb, txt);
        panel.appendChild(lbl);
        panel.appendChild(el('div', 'note', perfil.contar_transferencias
          ? 'Hoje elas estão incluídas no gasto médio. Se parte for repasse de dinheiro que entrou (PIX recebido, empréstimo), desmarque.'
          : 'Hoje elas estão fora do cálculo: não são compras nem contas e variam muito de um mês para o outro. A lista abaixo mostra o que são.'));

        const det = analise.transferencias_detalhe || [];
        if (det.length) {
          const box2 = el('div', 'transf');
          box2.appendChild(el('div', 'transf-h', 'Transferências e PIX no período'));
          (analise.transferencias_por_mes || []).forEach((m) => {
            const r = el('div', 'transf-row');
            r.append(el('span', null, mesCurto(m.mes)), strong(brl(m.total)));
            box2.appendChild(r);
          });
          box2.appendChild(el('div', 'transf-h', 'As maiores'));
          det.forEach((d) => {
            const r = el('div', 'transf-row');
            r.append(el('span', null, `${dataBR(d.data)} · ${d.destino}`), strong(brl(d.valor)));
            const b = el('button', 'transf-btn', 'Não é gasto'); b.type = 'button';
            b.title = 'Dinheiro seu (ex.: outra conta sua) ou repasse: não conta como gasto';
            b.addEventListener('click', async () => {
              if (!(await BFApp.modalConfirm(`Tratar "${d.destino}" como NÃO gasto? Transferências com esse nome deixam de entrar no gasto médio e nos totais do Dashboard.`, 'Não é gasto'))) return;
              try { await api({ acao: 'ignorar_destino', destino: d.destino }); await carregar(); }
              catch (e) { BFApp.modalAlert(e.message, 'Metas'); }
            });
            r.appendChild(b);
            box2.appendChild(r);
          });
          panel.appendChild(box2);
        }
      }
      const ign = analise.destinos_ignorados || [];
      if (ign.length) {
        const bx = el('div', 'transf');
        bx.appendChild(el('div', 'transf-h', 'Marcados como "não é gasto"'));
        const chips = el('div', 'chips');
        ign.forEach((nome) => {
          const c = el('span', 'chip', nome + ' ');
          const x = el('button', 'chip-x'); x.appendChild(Icone.el('x')); x.type = 'button'; x.setAttribute('aria-label', 'Voltar a contar como gasto');
          x.addEventListener('click', async () => {
            try { await api({ acao: 'desfazer_destino', destino: nome }); await carregar(); }
            catch (e) { BFApp.modalAlert(e.message, 'Metas'); }
          });
          c.appendChild(x); chips.appendChild(c);
        });
        bx.appendChild(chips);
        panel.appendChild(bx);
      }
      box.appendChild(panel);
    }

    function desenharSituacao() {
      const { analise } = dados;
      const box = $('situacao');
      box.textContent = '';
      if (!analise.situacao) return;

      const ban = el('div', 'banner ' + analise.situacao);
      const n = analise.necessario_mes_total;
      const cap = analise.capacidade_mensal;
      if (analise.situacao === 'viavel') {
        frase(ban, 'Suas metas com prazo pedem ', strong(brl(n) + '/mês'), ' e sua sobra é de ', strong(brl(cap)), '. Dá para cumprir com folga.');
      } else if (analise.situacao === 'apertado') {
        frase(ban, 'Suas metas com prazo pedem ', strong(brl(n) + '/mês'), ' de uma sobra de ', strong(brl(cap)), '. Dá, mas sem margem para imprevistos.');
      } else {
        frase(ban, 'Suas metas com prazo pedem ', strong(brl(n) + '/mês'), ', mas sua sobra é de ', strong(brl(Math.max(cap, 0))),
          '. Faltam ', strong(brl(analise.deficit_mensal) + '/mês'), ': reduza gastos nessa medida ou estenda os prazos.');
      }
      box.appendChild(ban);
    }

    function desenharCategorias() {
      const { analise } = dados;
      const box = $('categorias');
      box.textContent = '';
      const cats = analise.top_categorias || [];
      if (!cats.length) return;

      const panel = el('div', 'panel');
      panel.appendChild(el('div', 'panel-title', 'Onde seu dinheiro mais vai (média por mês)'));
      const max = cats[0].media_mensal || 1;
      cats.forEach((c) => {
        const row = el('div', 'cat-row');
        const top = el('div', 'cat-top');
        top.append(el('span', null, c.categoria), strong(brl(c.media_mensal)));
        const bar = el('div', 'bar');
        const fill = document.createElement('i');
        fill.style.width = Math.max(4, (c.media_mensal / max) * 100) + '%';
        bar.appendChild(fill);
        row.append(top, bar);
        panel.appendChild(row);
      });
      panel.appendChild(el('div', 'note', 'Esses são os primeiros lugares para procurar o que cortar e chegar mais rápido nas metas.'));
      box.appendChild(panel);
    }

    /* ── Metas ── */
    function desenharMetas() {
      const box = $('metas');
      box.textContent = '';
      const metas = dados.metas;
      if (!metas.length) {
        const e = el('div', 'empty', 'Você ainda não tem metas. Toque em + para criar a primeira (ex.: comprar uma casa) e eu calculo quanto guardar por mês.');
        e.style.gridColumn = '1 / -1';
        box.appendChild(e);
        return;
      }
      metas.forEach((m) => box.appendChild(cartaoMeta(m)));
    }

    function cartaoMeta(m) {
      const card = el('div', 'panel meta');

      const top = el('div', 'meta-top');
      const left = el('div');
      left.appendChild(el('div', 'meta-nome', m.nome));
      left.appendChild(el('div', 'meta-prazo', m.data_alvo ? `Até ${mesAno(m.data_alvo)} · ${m.meses_restantes} mês(es)` : 'Sem prazo definido'));
      top.appendChild(left);
      if (m.concluida) top.appendChild(el('span', 'badge ok', 'Concluída'));
      else if (m.viabilidade) top.appendChild(el('span', 'badge ' + m.viabilidade, { viavel: 'Viável', apertado: 'Apertado', inviavel: 'Fora do alcance' }[m.viabilidade]));
      card.appendChild(top);

      const prog = el('div');
      const bar = el('div', 'bar');
      const fill = document.createElement('i');
      fill.style.width = m.progresso + '%';
      bar.appendChild(fill);
      const line = el('div', 'prog-line');
      line.append(frase(el('span'), strong(brl(m.valor_guardado)), ' de ' + brl(m.valor_alvo)), el('span', null, m.progresso + '%'));
      prog.append(bar, line);
      if (m.investimento_alocado > 0) prog.appendChild(el('div', 'note', `Inclui ${brl(m.investimento_alocado)} dos seus investimentos.`));
      card.appendChild(prog);

      if (m.componentes.length) {
        const chips = el('div', 'chips');
        m.componentes.forEach((c) => chips.appendChild(frase(el('span', 'chip'), c.nome + ' ', strong(brl(c.valor)))));
        card.appendChild(chips);
      }

      if (!m.concluida) card.appendChild(blocoCalculo(m));

      const actions = el('div', 'meta-actions');
      const ap = el('button', 'btn', 'Guardei'); ap.type = 'button';
      ap.addEventListener('click', () => abrirAporte(m));
      const ed = el('button', 'btn outline', 'Editar'); ed.type = 'button';
      ed.addEventListener('click', () => abrirMeta(m));
      actions.append(ap, ed);
      card.appendChild(actions);
      return card;
    }

    function blocoCalculo(m) {
      const cap = dados.analise.capacidade_mensal;
      const temBase = cap !== null && dados.analise.meses_considerados > 0;
      const wrap = el('div', 'destaque');

      if (m.por_mes !== null) {
        wrap.appendChild(el('small', null, `Para chegar em ${mesAno(m.data_alvo)}`));
        const big = el('div', 'big');
        big.append(brl(m.por_mes), el('span', null, ' por mês'));
        wrap.appendChild(big);
        wrap.appendChild(el('div', 'dica', `Faltam ${brl(m.restante)} em ${m.meses_restantes} mês(es).`));

        if (temBase && m.viabilidade === 'inviavel') {
          const d = el('div', 'dica');
          frase(d, 'Sua sobra de ', strong(brl(Math.max(cap, 0))), ' não cobre. Faltam ', strong(brl(m.deficit_mensal) + '/mês'));
          if (m.meses_estimados) frase(d, ' — ou, com a sobra atual, você chega em ', strong(mesAno(m.data_estimada)), ` (${m.meses_estimados} meses).`);
          else frase(d, ' — reduza gastos para abrir espaço.');
          wrap.appendChild(d);
        } else if (temBase && m.viabilidade === 'apertado') {
          wrap.appendChild(el('div', 'dica', 'Cabe na sua sobra, mas com pouca margem.'));
        }
      } else {
        wrap.appendChild(el('small', null, 'Quanto guardar por mês, conforme o prazo'));
        const grid = el('div', 'cenarios');
        m.cenarios.slice(0, 4).forEach((c) => {
          const d = el('div');
          d.append(strong(brl(c.por_mes)), document.createTextNode(`em ${c.meses / 12} ano(s)`));
          grid.appendChild(d);
        });
        wrap.appendChild(grid);
        if (temBase && m.meses_estimados) {
          const d = el('div', 'dica');
          frase(d, 'Guardando toda a sua sobra de ', strong(brl(cap)), ', você chega em ', strong(mesAno(m.data_estimada)), ` (${m.meses_estimados} meses).`);
          wrap.appendChild(d);
        } else if (temBase) {
          wrap.appendChild(el('div', 'dica', 'Hoje seus gastos consomem toda a renda: é preciso reduzir despesas para começar a guardar.'));
        } else {
          wrap.appendChild(el('div', 'dica', 'Informe sua renda para eu estimar quando você chega lá.'));
        }
      }
      return wrap;
    }

    /* ── Formulário de meta ── */
    let editando = null;

    function addComp(nome = '', valor = '') {
      const row = el('div', 'comp-row');
      const n = el('input', 'input'); n.type = 'text'; n.placeholder = 'Ex.: Entrada'; n.maxLength = 80; n.value = nome;
      const v = el('input', 'input'); v.type = 'number'; v.inputMode = 'decimal'; v.step = '0.01'; v.min = '0'; v.placeholder = '0,00'; v.value = valor;
      const rm = el('button', 'rm'); rm.appendChild(Icone.el('x')); rm.type = 'button'; rm.setAttribute('aria-label', 'Remover item');
      rm.addEventListener('click', () => {
        if ($('comps').children.length > 1) { row.remove(); somar(); }
      });
      v.addEventListener('input', somar);
      row.append(n, v, rm);
      $('comps').appendChild(row);
    }

    function somar() {
      let t = 0;
      $('comps').querySelectorAll('.comp-row').forEach((r) => { t += num(r.children[1].value); });
      $('m-total').textContent = brl(t);
    }

    function lerComps() {
      return [...$('comps').querySelectorAll('.comp-row')]
        .map((r) => ({ nome: r.children[0].value.trim(), valor: num(r.children[1].value) }))
        .filter((c) => c.nome && c.valor > 0);
    }

    function abrirMeta(m) {
      editando = m || null;
      $('meta-title').textContent = m ? 'Editar meta' : 'Nova meta';
      $('meta-del').style.display = m ? '' : 'none';
      $('tpl-wrap').style.display = m ? 'none' : '';
      $('meta-msg').textContent = '';
      $('m-nome').value = m ? m.nome : '';
      $('comps').textContent = '';
      if (m && m.componentes.length) m.componentes.forEach((c) => addComp(c.nome, c.valor));
      else if (m) addComp('Valor total', m.valor_alvo);
      else addComp();
      $('m-inicial').value = m && m.valor_inicial ? m.valor_inicial : '';
      $('m-prazo').value = m && m.data_alvo ? m.data_alvo.slice(0, 7) : '';
      somar();
      $('ov-meta').classList.add('open');
      setTimeout(() => $('m-nome').focus(), 50);
    }

    function fechar(id) { $(id).classList.remove('open'); }

    async function salvarMeta() {
      const componentes = lerComps();
      if (!$('m-nome').value.trim()) { $('meta-msg').textContent = 'Dê um nome para a meta.'; return; }
      if (!componentes.length) { $('meta-msg').textContent = 'Informe pelo menos um item com nome e valor.'; return; }
      $('meta-save').disabled = true;
      try {
        await api({
          acao: editando ? 'editar' : 'criar',
          id: editando ? editando.id : undefined,
          nome: $('m-nome').value.trim(),
          componentes,
          valor_inicial: num($('m-inicial').value),
          data_alvo: $('m-prazo').value,
        });
        fechar('ov-meta');
        await carregar();
      } catch (e) {
        $('meta-msg').textContent = e.message;
      } finally {
        $('meta-save').disabled = false;
      }
    }

    async function excluirMeta() {
      if (!editando) return;
      if (!(await BFApp.modalConfirm(`Excluir a meta "${editando.nome}" e todo o histórico de aportes?`, 'Excluir meta'))) return;
      try {
        await api({ acao: 'excluir', id: editando.id });
        fechar('ov-meta');
        await carregar();
      } catch (e) {
        $('meta-msg').textContent = e.message;
      }
    }

    /* ── Aporte ── */
    let metaAporte = null;
    function abrirAporte(m) {
      metaAporte = m;
      $('ap-title').textContent = 'Guardei para: ' + m.nome;
      $('ap-valor').value = '';
      $('ap-msg').textContent = '';
      $('ov-aporte').classList.add('open');
      setTimeout(() => $('ap-valor').focus(), 50);
    }
    async function salvarAporte() {
      const valor = num($('ap-valor').value);
      if (!valor) { $('ap-msg').textContent = 'Informe um valor.'; return; }
      try {
        await api({ acao: 'aporte', id: metaAporte.id, valor });
        fechar('ov-aporte');
        await carregar();
      } catch (e) {
        $('ap-msg').textContent = e.message;
      }
    }

    /* ── Renda ── */
    function abrirRenda() {
      $('rd-valor').value = dados.perfil.renda_mensal !== null ? dados.perfil.renda_mensal : '';
      $('rd-msg').textContent = '';
      $('ov-renda').classList.add('open');
      setTimeout(() => $('rd-valor').focus(), 50);
    }
    async function salvarRenda() {
      const raw = $('rd-valor').value.trim();
      try {
        await api({ acao: 'renda', renda_mensal: raw === '' ? null : num(raw) });
        fechar('ov-renda');
        await carregar();
      } catch (e) {
        $('rd-msg').textContent = e.message;
      }
    }

    $('fab').addEventListener('click', () => abrirMeta(null));
    $('add-comp').addEventListener('click', () => addComp());
    $('tpl-casa').addEventListener('click', () => {
      $('m-nome').value = 'Comprar casa';
      $('comps').textContent = '';
      addComp('Entrada', 45000);
      addComp('Documentação', 30000);
      somar();
    });
    $('meta-cancel').addEventListener('click', () => fechar('ov-meta'));
    $('meta-save').addEventListener('click', salvarMeta);
    $('meta-del').addEventListener('click', excluirMeta);
    $('ap-cancel').addEventListener('click', () => fechar('ov-aporte'));
    $('ap-save').addEventListener('click', salvarAporte);
    $('rd-cancel').addEventListener('click', () => fechar('ov-renda'));
    $('rd-save').addEventListener('click', salvarRenda);
    ['ov-meta', 'ov-aporte', 'ov-renda'].forEach((id) => $(id).addEventListener('click', (e) => { if (e.target === $(id)) fechar(id); }));

    carregar();
  </script>

</body>
</html>
