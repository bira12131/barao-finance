<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Agenda</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <script src="../../js/app-runtime.js"></script>
  <style>
    :root {
      --c-compromisso: #4f85ed;
      --c-lembrete:    #e0a030;
      --c-tarefa:      #33b178;
      --c-vencimento:  #e0605f;
    }

    /* ── Barra do mês ── */
    .cal-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.5rem;
      margin-bottom: 0.7rem;
    }
    .cal-title {
      flex: 1;
      text-align: center;
      font-size: 1.02rem;
      font-weight: 800;
      color: var(--text-heading);
      text-transform: capitalize;
    }
    .icon-btn {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      border: 1.5px solid var(--border);
      background: var(--bg-input);
      color: var(--text-body);
      font-size: 1.1rem;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .icon-btn:active { transform: scale(0.96); }
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

    .seg {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.3rem;
      padding: 0.25rem;
      margin-bottom: 0.8rem;
      border-radius: 14px;
      background: var(--bg-input);
      border: 1px solid var(--border);
    }
    .seg button {
      min-height: 40px;
      border: none;
      border-radius: 10px;
      background: transparent;
      color: var(--text-muted);
      font-weight: 700;
      font-size: 0.84rem;
      cursor: pointer;
    }
    .seg button.on { background: var(--primary); color: #fff; }

    /* ── Grade do calendário ── */
    .cal-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-card);
      padding: 0.6rem;
    }
    .cal-week, .cal-grid {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      gap: 0.2rem;
    }
    .cal-week span {
      text-align: center;
      font-size: 0.66rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      color: var(--text-muted);
      padding: 0.2rem 0 0.35rem;
    }
    .day {
      position: relative;
      aspect-ratio: 1 / 1;
      min-height: 44px;
      border-radius: 12px;
      border: 1.5px solid transparent;
      background: transparent;
      color: var(--text-body);
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 3px;
      padding: 0;
    }
    .day.out { opacity: 0.32; }
    .day.today { border-color: var(--primary); color: var(--text-heading); }
    .day.sel { background: var(--primary); color: #fff; border-color: var(--primary); transform: scale(1.12); z-index: 1; box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3); }
    .dots { display: flex; gap: 3px; height: 6px; }
    .dot { width: 6px; height: 6px; border-radius: 50%; }
    .day.sel .dot { box-shadow: 0 0 0 1.5px rgba(255, 255, 255, 0.85); }
    .dot.compromisso { background: var(--c-compromisso); }
    .dot.lembrete    { background: var(--c-lembrete); }
    .dot.tarefa      { background: var(--c-tarefa); }
    .dot.vencimento  { background: var(--c-vencimento); }

    .legend {
      display: flex;
      flex-wrap: wrap;
      gap: 0.3rem 0.85rem;
      margin: 0.65rem 0.2rem 0;
      font-size: 0.72rem;
      color: var(--text-muted);
    }
    .legend i { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 0.3rem; }

    /* ── Lista de itens ── */
    .list-head {
      margin: 1.1rem 0.2rem 0.55rem;
      font-size: 0.78rem;
      font-weight: 800;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: color-mix(in srgb, var(--page-accent) 55%, var(--text-muted));
    }
    .items { display: flex; flex-direction: column; gap: 0.5rem; }
    .item {
      display: flex;
      align-items: center;
      gap: 0.7rem;
      min-height: 56px;
      padding: 0.6rem 0.75rem;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-left: 4px solid var(--cor, var(--primary));
      border-radius: 14px;
      cursor: pointer;
      text-align: left;
      width: 100%;
      color: inherit;
      font: inherit;
    }
    .item.readonly { cursor: default; }
    .item.done .item-title { text-decoration: line-through; opacity: 0.55; }
    .item-time {
      min-width: 3rem;
      font-size: 0.78rem;
      font-weight: 700;
      color: var(--text-muted);
    }
    .item-main { flex: 1; min-width: 0; }
    .item-title {
      font-size: 0.92rem;
      font-weight: 700;
      color: var(--text-heading);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .item-sub { font-size: 0.74rem; color: var(--text-muted); margin-top: 0.1rem; }
    .item-valor { font-size: 0.9rem; font-weight: 800; color: var(--c-vencimento); white-space: nowrap; }
    .check {
      width: 28px;
      height: 28px;
      flex: none;
      border-radius: 50%;
      border: 2px solid var(--c-tarefa);
      background: transparent;
      color: #fff;
      font-size: 0.85rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .check.on { background: var(--c-tarefa); }
    .empty { padding: 1rem 0.3rem; color: var(--text-muted); font-size: 0.86rem; }
    .date-group { margin: 0.9rem 0.2rem 0.4rem; font-size: 0.8rem; font-weight: 800; color: var(--text-heading); text-transform: capitalize; }

    /* ── Botão flutuante ── */
    .fab {
      position: fixed;
      right: 1.1rem;
      bottom: 0.9rem;
      width: 54px;
      height: 54px;
      border-radius: 50%;
      border: none;
      background: var(--primary);
      color: #fff;
      font-size: 1.9rem;
      line-height: 1;
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.45);
      cursor: pointer;
      z-index: 30;
    }
    .fab:active { transform: scale(0.95); }

    /* ── Formulário (bottom sheet no celular) ── */
    .overlay {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(8, 12, 20, 0.7);
      z-index: 50;
      padding: 1rem;
    }
    .overlay.open { display: flex; }
    .sheet {
      width: 100%;
      max-width: 520px;
      max-height: 92vh;
      overflow-y: auto;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 20px;
      box-shadow: 0 22px 44px rgba(0, 0, 0, 0.5);
      padding: 1.1rem;
    }
    .sheet-title { font-size: 1rem; font-weight: 800; color: var(--text-heading); margin-bottom: 0.8rem; }
    .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.7rem; }
    .field { display: flex; flex-direction: column; gap: 0.3rem; }
    .field.full { grid-column: 1 / -1; }
    .label {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-muted);
      font-weight: 700;
    }
    .input {
      min-height: 46px;
      padding: 0 0.75rem;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      background: var(--bg-input);
      color: var(--text-body);
      font: inherit;
      font-size: 16px; /* evita zoom automático no iOS */
      outline: none;
      width: 100%;
    }
    textarea.input { padding: 0.6rem 0.75rem; min-height: 72px; resize: vertical; }
    .input:focus { border-color: var(--primary); }
    .types { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.4rem; }
    .types button {
      min-height: 44px;
      border-radius: 12px;
      border: 1.5px solid var(--border);
      background: var(--bg-input);
      color: var(--text-muted);
      font-weight: 700;
      font-size: 0.8rem;
      cursor: pointer;
    }
    .types button.on { color: #fff; border-color: var(--cor); background: var(--cor); }
    .sheet-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem; }
    .sheet-actions .btn { flex: 1; }
    .sheet-msg { min-height: 1rem; margin-top: 0.5rem; font-size: 0.8rem; color: var(--danger); }

    .sync-bloco { border: 1px solid var(--border); border-radius: 14px; padding: 0.8rem; margin-bottom: 1rem; background: var(--bg-input); }
    .sync-sub { font-size: 0.74rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: color-mix(in srgb, var(--page-accent) 55%, var(--text-muted)); margin-bottom: 0.5rem; }
    .ic-cal { display: flex; align-items: center; gap: 0.6rem; min-height: 44px; font-size: 0.86rem; color: var(--text-body); cursor: pointer; }
    .ic-cal input { width: 22px; height: 22px; accent-color: var(--primary); flex: none; }
    .ic-cor { width: 10px; height: 10px; border-radius: 50%; flex: none; }
    .page-header-actions { display: inline-flex; align-items: center; gap: 0.5rem; }
    .toast {
      position: fixed; left: 50%; transform: translateX(-50%); bottom: 4.8rem; z-index: 60;
      max-width: calc(100% - 2rem); padding: 0.65rem 0.95rem; border-radius: 12px;
      background: var(--bg-card); border: 1.5px solid var(--border); color: var(--text-heading);
      font-size: 0.82rem; line-height: 1.4; box-shadow: 0 10px 24px rgba(0, 0, 0, 0.45); text-align: center;
    }
    .toast.ok { border-color: var(--success); }
    .toast.erro { border-color: var(--danger); }
    .sync-txt { font-size: 0.82rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 0.8rem; }
    .sync-txt b { color: var(--text-heading); }
    .sync-txt.aviso { font-size: 0.76rem; }
    .sync-passos { padding-left: 1.1rem; }
    .sync-btns { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.9rem; }
    .sync-btns .btn { flex: 1; text-decoration: none; text-align: center; }
    @media (max-width: 640px) {
      .inner-page { padding: 1rem 0.85rem; padding-bottom: 5.8rem; }
      .overlay { align-items: flex-end; padding: 0; }
      .sheet {
        max-width: none;
        border-radius: 22px 22px 0 0;
        padding-bottom: 1.2rem; /* libera a barra inferior do app */
      }
      .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (min-width: 900px) {
      .fab { bottom: 1.6rem; right: 2rem; }
      .toast { bottom: 1.8rem; }
      .layout { display: grid; grid-template-columns: minmax(360px, 460px) 1fr; gap: 1.2rem; align-items: start; }
      .list-head:first-child { margin-top: 0; }
    }
  </style>
</head>
<body class="inner-page tone-agenda">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Agenda</h1>
      <p>Compromissos, lembretes, tarefas e vencimentos em um só lugar</p>
    </div>
    <div class="page-header-actions">
      <button class="btn outline" id="btn-iphone" type="button">Sincronizar com iPhone</button>
      <button class="icon-btn" id="btn-iphone-cfg" type="button" aria-label="Ajustes da sincronização com o iPhone" style="display:none"><i data-icone="engrenagem"></i></button>
    </div>
  </div>

  <div class="seg" role="tablist">
    <button type="button" id="tab-mes" class="on" role="tab">Calendário</button>
    <button type="button" id="tab-prox" role="tab">Próximos 30 dias</button>
  </div>

  <div id="view-mes" class="layout">
    <div>
      <div class="cal-bar">
        <button class="icon-btn" id="prev" aria-label="Mês anterior"><i data-icone="seta-esq"></i></button>
        <div class="cal-title" id="cal-title"></div>
        <button class="icon-btn" id="next" aria-label="Próximo mês"><i data-icone="seta-dir"></i></button>
      </div>
      <div class="cal-card">
        <div class="cal-week"><span>Dom</span><span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span></div>
        <div class="cal-grid" id="grid"></div>
      </div>
      <div class="legend">
        <span><i style="background:var(--c-compromisso)"></i>Compromisso</span>
        <span><i style="background:var(--c-lembrete)"></i>Lembrete</span>
        <span><i style="background:var(--c-tarefa)"></i>Tarefa</span>
        <span><i style="background:var(--c-vencimento)"></i>Vencimento</span>
      </div>
    </div>
    <div>
      <div class="list-head" id="day-head"></div>
      <div class="items" id="day-items"></div>
    </div>
  </div>

  <div id="view-prox" style="display:none">
    <div id="prox-list"></div>
  </div>

  <button class="fab" id="fab" aria-label="Novo item">+</button>

  <div class="overlay" id="overlay">
    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sheet-title">
      <div class="sheet-title" id="sheet-title">Novo item</div>
      <div class="grid">
        <div class="field full">
          <label class="label">Tipo</label>
          <div class="types" id="types">
            <button type="button" data-tipo="compromisso" style="--cor:var(--c-compromisso)">Compromisso</button>
            <button type="button" data-tipo="lembrete" style="--cor:var(--c-lembrete)">Lembrete</button>
            <button type="button" data-tipo="tarefa" style="--cor:var(--c-tarefa)">Tarefa</button>
          </div>
        </div>
        <div class="field full">
          <label class="label" for="f-titulo">Título</label>
          <input class="input" id="f-titulo" type="text" maxlength="160" placeholder="Ex.: Visitar apartamento" autocomplete="off" />
        </div>
        <div class="field">
          <label class="label" for="f-data">Data</label>
          <input class="input" id="f-data" type="date" />
        </div>
        <div class="field">
          <label class="label" for="f-hora">Hora (opcional)</label>
          <input class="input" id="f-hora" type="time" />
        </div>
        <div class="field full">
          <label class="label" for="f-rec">Repetir</label>
          <select class="input" id="f-rec">
            <option value="nenhuma">Não repetir</option>
            <option value="semanal">Toda semana</option>
            <option value="mensal">Todo mês</option>
            <option value="anual">Todo ano</option>
          </select>
        </div>
        <div class="field full">
          <label class="label" for="f-desc">Observações (opcional)</label>
          <textarea class="input" id="f-desc" maxlength="1000"></textarea>
        </div>
      </div>
      <div class="sheet-msg" id="sheet-msg"></div>
      <div class="sheet-actions">
        <button class="btn danger" id="btn-del" style="display:none">Excluir</button>
        <button class="btn outline" id="btn-cancel">Cancelar</button>
        <button class="btn" id="btn-save">Salvar</button>
      </div>
    </div>
  </div>

  <div class="toast" id="toast" role="status" aria-live="polite" style="display:none"></div>

  <div class="overlay" id="ov-sync">
    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sync-title">
      <div class="sheet-title" id="sync-title">Agenda no calendário do iPhone</div>

      <div class="sync-bloco" id="ic-bloco">
        <div class="sync-sub">Sincronização completa com o iCloud</div>
        <p class="sync-txt">Nos dois sentidos: o que está no Calendário do iPhone aparece aqui, e o que você cria aqui vai para lá. Seus eventos ficam no calendário <b>Barão Agenda</b>, e os vencimentos (faturas, assinaturas, parcelas de empréstimo) no <b>Barão Vencimentos</b>, só para consulta. Os seus outros calendários só são lidos, nunca alterados.</p>
        <div id="ic-config" style="display:none">
          <ol class="sync-txt sync-passos">
            <li>Em <b>account.apple.com › Login e Segurança › Senhas de app</b>, gere uma senha (ex.: "Barao Finance").</li>
            <li>No servidor, preencha <b>backend/config/icloud.php</b> com o Apple ID e essa senha de app. Nunca a senha do Apple ID.</li>
            <li>Volte aqui e toque em <b>Testar conexão</b>.</li>
          </ol>
        </div>
        <div class="sync-txt" id="ic-status"></div>
        <div id="ic-cals"></div>
        <div class="sync-btns">
          <button class="btn outline" id="ic-testar" type="button">Testar conexão</button>
          <button class="btn" id="ic-ativar" type="button">Ativar e sincronizar</button>
          <button class="btn" id="ic-agora" type="button" style="display:none">Sincronizar agora</button>
          <button class="btn outline" id="ic-desativar" type="button" style="display:none">Desativar</button>
        </div>
      </div>

      <div class="sync-sub">Só do app para o iPhone (link de assinatura)</div>
      <p class="sync-txt">O iPhone assina a sua agenda por um link e passa a mostrá-la no app Calendário: compromissos, lembretes, tarefas e vencimentos (fatura, assinaturas, empréstimos), com alertas. Ele atualiza sozinho de tempos em tempos, não é instantâneo.</p>
      <div class="field">
        <label class="label" for="sync-url">Seu link secreto</label>
        <input class="input" id="sync-url" type="text" readonly value="Carregando…" />
      </div>
      <div class="sync-btns">
        <a class="btn" id="sync-open" href="#" target="_top">Abrir no Calendário do iPhone</a>
        <button class="btn outline" id="sync-copy" type="button">Copiar link</button>
      </div>
      <ol class="sync-txt sync-passos">
        <li>No iPhone, abra este app e toque em <b>Abrir no Calendário</b>, depois em <b>Assinar</b>.</li>
        <li>Ou vá em <b>Ajustes › Calendário › Contas › Adicionar Conta › Outra › Adicionar Calendário Assinado</b> e cole o link.</li>
      </ol>
      <p class="sync-txt aviso">Quem tiver este link vê a sua agenda. Não compartilhe. Se vazar, gere um novo: o antigo para de funcionar.</p>
      <p class="sync-txt aviso">Este link é só leitura no iPhone. Com a sincronização do iCloud ligada ele não é mais necessário (e fica vazio): eventos e vencimentos já chegam pelos calendários Barão Agenda e Barão Vencimentos.</p>
      <div class="sheet-msg" id="sync-msg"></div>
      <div class="sheet-actions">
        <button class="btn danger" id="sync-regen" type="button">Gerar novo link</button>
        <button class="btn outline" id="sync-close" type="button">Fechar</button>
      </div>
    </div>
  </div>

  <script>
    const COR = { compromisso: 'var(--c-compromisso)', lembrete: 'var(--c-lembrete)', tarefa: 'var(--c-tarefa)', vencimento: 'var(--c-vencimento)' };
    const ORIGEM_LABEL = { fatura: 'Fatura do cartão', despesa_prevista: 'Despesa prevista', assinatura: 'Assinatura', emprestimo: 'Parcela de empréstimo', icloud: 'Calendário do iPhone' };
    const REC_LABEL = { semanal: 'toda semana', mensal: 'todo mês', anual: 'todo ano' };

    const $ = (id) => document.getElementById(id);
    const pad = (n) => String(n).padStart(2, '0');
    const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const parseIso = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const brl = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);

    function apiBase() {
      const { origin, pathname } = window.location;
      const root = pathname
        .replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '')
        .replace(/\/[^/]*\.(?:html|php)$/, '');
      return origin + root + '/backend/api';
    }

    async function api(method, body, query) {
      const url = new URL(apiBase() + '/agenda.php');
      if (query) Object.entries(query).forEach(([k, v]) => url.searchParams.set(k, v));
      const opts = { cache: 'no-store', headers: { 'Content-Type': 'application/json' } };
      if (method === 'GET') {
        opts.method = 'GET';
      } else {
        // O servidor só libera GET/POST: PATCH/DELETE vão como POST + _method.
        opts.method = 'POST';
        opts.body = JSON.stringify(method === 'POST' ? body : { ...body, _method: method });
      }
      const res = await fetch(url.toString(), opts);
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'Erro ao falar com o servidor.');
      return json.data;
    }

    const hoje = new Date();
    let mesAtual = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    let selecionado = iso(hoje);
    let itensMes = [];
    let editando = null;
    let tipoSel = 'compromisso';

    function itensDoDia(d) { return itensMes.filter((i) => i.data === d); }

    /* ── Calendário ── */
    async function carregarMes() {
      const mes = `${mesAtual.getFullYear()}-${pad(mesAtual.getMonth() + 1)}`;
      $('cal-title').textContent = mesAtual.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
      try {
        // Carrega também as semanas "vazadas" do mês anterior/seguinte exibidas na grade.
        const ini = new Date(mesAtual.getFullYear(), mesAtual.getMonth(), 1 - mesAtual.getDay());
        const fimMes = new Date(mesAtual.getFullYear(), mesAtual.getMonth() + 1, 0);
        const fim = new Date(fimMes.getFullYear(), fimMes.getMonth(), fimMes.getDate() + (6 - fimMes.getDay()));
        const data = await api('GET', null, { inicio: iso(ini), fim: iso(fim) });
        itensMes = data.itens || [];
      } catch (e) {
        itensMes = [];
        BFApp.modalAlert(e.message, 'Agenda');
      }
      desenharGrade();
      desenharDia();
    }

    function desenharGrade() {
      const grid = $('grid');
      grid.textContent = '';
      const ano = mesAtual.getFullYear(), mes = mesAtual.getMonth();
      const inicio = new Date(ano, mes, 1 - new Date(ano, mes, 1).getDay());
      const ultimo = new Date(ano, mes + 1, 0);
      const total = Math.ceil((new Date(ano, mes, 1).getDay() + ultimo.getDate()) / 7) * 7;

      for (let i = 0; i < total; i++) {
        const d = new Date(inicio.getFullYear(), inicio.getMonth(), inicio.getDate() + i);
        const key = iso(d);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'day' + (d.getMonth() !== mes ? ' out' : '') + (key === iso(hoje) ? ' today' : '') + (key === selecionado ? ' sel' : '');
        btn.textContent = d.getDate();
        const tipos = [...new Set(itensDoDia(key).map((x) => x.tipo))].slice(0, 3);
        const dots = document.createElement('span');
        dots.className = 'dots';
        tipos.forEach((t) => { const s = document.createElement('span'); s.className = 'dot ' + t; dots.appendChild(s); });
        btn.appendChild(dots);
        btn.addEventListener('click', () => { selecionado = key; desenharGrade(); desenharDia(); });
        grid.appendChild(btn);
      }
    }

    function rotuloData(key) {
      const s = parseIso(key).toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' });
      return key === iso(hoje) ? 'Hoje · ' + s : s;
    }

    function criarItem(i) {
      const ro = i.origem !== 'agenda';
      const el = document.createElement(ro ? 'div' : 'button');
      if (!ro) el.type = 'button';
      el.className = 'item' + (ro ? ' readonly' : '') + (i.concluido ? ' done' : '');
      el.style.setProperty('--cor', COR[i.tipo] || COR.compromisso);

      if (i.tipo === 'tarefa' && !ro && i.recorrencia === 'nenhuma') {
        const c = document.createElement('span');
        c.className = 'check' + (i.concluido ? ' on' : '');
        c.setAttribute('role', 'checkbox');
        c.setAttribute('aria-checked', String(!!i.concluido));
        if (i.concluido) c.appendChild(Icone.el('check'));
        c.addEventListener('click', async (ev) => {
          ev.stopPropagation();
          try { await api('PATCH', { id: i.id, concluido: !i.concluido }); await recarregar(); }
          catch (e) { BFApp.modalAlert(e.message, 'Agenda'); }
        });
        el.appendChild(c);
      } else {
        const t = document.createElement('span');
        t.className = 'item-time';
        t.textContent = i.hora || ((ro && i.origem !== 'icloud') ? '' : 'Dia todo');
        el.appendChild(t);
      }

      const main = document.createElement('div');
      main.className = 'item-main';
      const title = document.createElement('div');
      title.className = 'item-title';
      title.textContent = i.titulo;
      main.appendChild(title);
      const sub = [];
      if (ro) sub.push(i.origem === 'icloud' && i.icloud_cal_nome ? 'iPhone · ' + i.icloud_cal_nome : (ORIGEM_LABEL[i.origem] || 'Vencimento'));
      else {
        if (i.tipo === 'tarefa' && i.hora) sub.push(i.hora);
        if (REC_LABEL[i.recorrencia]) sub.push('repete ' + REC_LABEL[i.recorrencia]);
      }
      if (sub.length) {
        const s = document.createElement('div');
        s.className = 'item-sub';
        s.textContent = sub.join(' · ');
        main.appendChild(s);
      }
      el.appendChild(main);

      if (ro && i.valor) {
        const v = document.createElement('span');
        v.className = 'item-valor';
        v.textContent = brl(i.valor);
        el.appendChild(v);
      }
      if (!ro) el.addEventListener('click', () => abrirForm(i));
      return el;
    }

    function desenharDia() {
      $('day-head').textContent = rotuloData(selecionado);
      const box = $('day-items');
      box.textContent = '';
      const lista = itensDoDia(selecionado);
      if (!lista.length) {
        const e = document.createElement('div');
        e.className = 'empty';
        e.textContent = 'Nada marcado para este dia. Toque em + para adicionar.';
        box.appendChild(e);
        return;
      }
      lista.forEach((i) => box.appendChild(criarItem(i)));
    }

    /* ── Próximos 30 dias ── */
    async function carregarProximos() {
      const box = $('prox-list');
      box.textContent = '';
      try {
        const fim = new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate() + 30);
        const data = await api('GET', null, { inicio: iso(hoje), fim: iso(fim) });
        const itens = data.itens || [];
        if (!itens.length) {
          const e = document.createElement('div');
          e.className = 'empty';
          e.textContent = 'Nenhum compromisso ou vencimento nos próximos 30 dias.';
          box.appendChild(e);
          return;
        }
        let atual = '';
        let wrap = null;
        itens.forEach((i) => {
          if (i.data !== atual) {
            atual = i.data;
            const h = document.createElement('div');
            h.className = 'date-group';
            h.textContent = rotuloData(atual);
            box.appendChild(h);
            wrap = document.createElement('div');
            wrap.className = 'items';
            box.appendChild(wrap);
          }
          wrap.appendChild(criarItem(i));
        });
      } catch (e) {
        BFApp.modalAlert(e.message, 'Agenda');
      }
    }

    async function recarregar() {
      if ($('view-mes').style.display === 'none') await carregarProximos();
      else await carregarMes();
    }

    function mostrar(aba) {
      const mes = aba === 'mes';
      $('view-mes').style.display = mes ? '' : 'none';
      $('view-prox').style.display = mes ? 'none' : '';
      $('tab-mes').classList.toggle('on', mes);
      $('tab-prox').classList.toggle('on', !mes);
      recarregar();
    }

    /* ── Formulário ── */
    function marcarTipo(t) {
      tipoSel = t;
      document.querySelectorAll('#types button').forEach((b) => b.classList.toggle('on', b.dataset.tipo === t));
    }

    function abrirForm(item) {
      editando = item ? item : null;
      $('sheet-title').textContent = item ? 'Editar item' : 'Novo item';
      $('btn-del').style.display = item ? '' : 'none';
      $('sheet-msg').textContent = '';
      marcarTipo(item ? item.tipo : 'compromisso');
      $('f-titulo').value = item ? item.titulo : '';
      $('f-data').value = item ? item.data_inicio : selecionado;
      $('f-hora').value = item && item.hora ? item.hora : '';
      $('f-rec').value = item ? item.recorrencia : 'nenhuma';
      $('f-desc').value = item && item.descricao ? item.descricao : '';
      $('overlay').classList.add('open');
      setTimeout(() => $('f-titulo').focus(), 50);
    }

    function fecharForm() {
      $('overlay').classList.remove('open');
      editando = null;
    }

    async function salvar() {
      const corpo = {
        titulo: $('f-titulo').value.trim(),
        data: $('f-data').value,
        hora: $('f-hora').value,
        tipo: tipoSel,
        recorrencia: $('f-rec').value,
        descricao: $('f-desc').value.trim(),
      };
      if (!corpo.titulo || !corpo.data) {
        $('sheet-msg').textContent = 'Informe o título e a data.';
        return;
      }
      $('btn-save').disabled = true;
      try {
        if (editando) await api('PATCH', { ...corpo, id: editando.id });
        else await api('POST', corpo);
        selecionado = corpo.data;
        const d = parseIso(corpo.data);
        mesAtual = new Date(d.getFullYear(), d.getMonth(), 1);
        fecharForm();
        await recarregar();
        if (icEstado && icEstado.habilitado) icSincronizar(true);
      } catch (e) {
        $('sheet-msg').textContent = e.message;
      } finally {
        $('btn-save').disabled = false;
      }
    }

    async function excluir() {
      if (!editando) return;
      const ok = await BFApp.modalConfirm(
        editando.recorrencia !== 'nenhuma' ? 'Excluir este item e todas as repetições?' : 'Excluir este item?',
        'Excluir'
      );
      if (!ok) return;
      try {
        await api('DELETE', { id: editando.id });
        fecharForm();
        await recarregar();
        if (icEstado && icEstado.habilitado) icSincronizar(true);
      } catch (e) {
        $('sheet-msg').textContent = e.message;
      }
    }

    $('prev').addEventListener('click', () => { mesAtual = new Date(mesAtual.getFullYear(), mesAtual.getMonth() - 1, 1); carregarMes(); });
    $('next').addEventListener('click', () => { mesAtual = new Date(mesAtual.getFullYear(), mesAtual.getMonth() + 1, 1); carregarMes(); });
    $('cal-title').addEventListener('click', () => {
      mesAtual = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
      selecionado = iso(hoje);
      carregarMes();
    });
    $('tab-mes').addEventListener('click', () => mostrar('mes'));
    $('tab-prox').addEventListener('click', () => mostrar('prox'));
    $('fab').addEventListener('click', () => abrirForm(null));
    $('btn-cancel').addEventListener('click', fecharForm);
    $('btn-save').addEventListener('click', salvar);
    $('btn-del').addEventListener('click', excluir);
    $('overlay').addEventListener('click', (e) => { if (e.target === $('overlay')) fecharForm(); });
    document.querySelectorAll('#types button').forEach((b) => b.addEventListener('click', () => marcarTipo(b.dataset.tipo)));
    $('f-titulo').addEventListener('keydown', (e) => { if (e.key === 'Enter') salvar(); });

    /* ── Sincronizar com o iPhone ── */
    let linkCal = null;
    async function chamarSync(regenerar) {
      const res = await fetch(apiBase() + '/agenda_sync.php', regenerar
        ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ acao: 'regenerar' }), cache: 'no-store' }
        : { cache: 'no-store' });
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'Erro ao gerar o link.');
      linkCal = json.data;
      $('sync-url').value = linkCal.url;
      $('sync-open').href = linkCal.webcal_url;
      $('sync-msg').textContent = linkCal.seguro ? '' : 'Atenção: este endereço não usa HTTPS. O iPhone pode recusar a assinatura.';
    }
    async function abrirSync() {
      $('ov-sync').classList.add('open');
      $('sync-msg').textContent = '';
      icCarregar(false);
      try { await chamarSync(false); } catch (e) { $('sync-url').value = ''; $('sync-msg').textContent = e.message; }
    }
    async function copiarLink() {
      const url = $('sync-url').value;
      if (!url) return;
      try { await navigator.clipboard.writeText(url); }
      catch (_) { $('sync-url').select(); document.execCommand('copy'); }
      $('sync-msg').style.color = 'var(--success)';
      $('sync-msg').textContent = 'Link copiado.';
      setTimeout(() => { $('sync-msg').textContent = ''; $('sync-msg').style.color = ''; }, 2000);
    }
    async function novoLink() {
      if (!(await BFApp.modalConfirm('Gerar um novo link? O calendário já assinado no iPhone deixa de atualizar até você assinar o novo.', 'Novo link'))) return;
      try { await chamarSync(true); } catch (e) { $('sync-msg').textContent = e.message; }
    }
    /** Mostra um aviso curto sem abrir painel nenhum. */
    let toastTimer = null;
    function aviso(texto, erro) {
      const t = $('toast');
      t.textContent = texto;
      t.className = 'toast ' + (erro ? 'erro' : 'ok');
      t.style.display = '';
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => { t.style.display = 'none'; }, erro ? 7000 : 3500);
    }

    /** Ligado: o botão só sincroniza. Desligado: abre o painel de configuração. */
    function atualizarBotaoIphone() {
      const ligado = !!(icEstado && icEstado.habilitado);
      $('btn-iphone').textContent = icOcupado ? 'Sincronizando…' : (ligado ? 'Sincronizar' : 'Sincronizar com iPhone');
      $('btn-iphone').disabled = icOcupado;
      $('btn-iphone-cfg').style.display = ligado ? '' : 'none';
    }

    async function sincronizarDireto() {
      if (icOcupado) return;
      icOcupado = true;
      atualizarBotaoIphone();
      try {
        const e = await icApi('sincronizar');
        icOcupado = false;
        icDesenhar(e);
        await recarregar();
        const r = e.resultado || {};
        const erros = r.erros || [];
        if (erros.length) aviso('Sincronizado com ressalvas: ' + erros[0], true);
        else aviso('Sincronizado com o iPhone · ' + icResumo(r), false);
      } catch (err) {
        aviso(err.message, true);
      } finally {
        icOcupado = false;
        atualizarBotaoIphone();
      }
    }

    async function cliqueIphone() {
      if (!icEstado) { try { icDesenhar(await icApi()); } catch (_) { /* segue para o painel */ } }
      if (icEstado && icEstado.habilitado) return sincronizarDireto();
      abrirSync();
    }

    $('btn-iphone').addEventListener('click', cliqueIphone);
    $('btn-iphone-cfg').addEventListener('click', abrirSync);
    $('sync-copy').addEventListener('click', copiarLink);
    $('sync-regen').addEventListener('click', novoLink);
    $('sync-close').addEventListener('click', () => $('ov-sync').classList.remove('open'));
    $('ov-sync').addEventListener('click', (e) => { if (e.target === $('ov-sync')) $('ov-sync').classList.remove('open'); });

    /* ── Sincronização completa com o iCloud ── */
    let icEstado = null;
    let icOcupado = false;

    async function icApi(acao, extra) {
      const opts = acao
        ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ acao, ...(extra || {}) }), cache: 'no-store' }
        : { cache: 'no-store' };
      const res = await fetch(apiBase() + '/agenda_icloud.php', opts);
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'Erro na sincronização com o iCloud.');
      return json.data;
    }

    function icQuando(iso) {
      if (!iso) return '—';
      const d = new Date(String(iso).replace(' ', 'T').replace(/([+-]\d\d)$/, '$1:00'));
      return Number.isNaN(d.getTime()) ? '—' : d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    function icResumo(r) {
      if (!r) return '';
      const p = [];
      if (r.enviados) p.push(`${r.enviados} enviado(s) ao iPhone`);
      if (r.atualizados_no_iphone) p.push(`${r.atualizados_no_iphone} atualizado(s) no iPhone`);
      if (r.apagados_no_iphone) p.push(`${r.apagados_no_iphone} apagado(s) no iPhone`);
      if (r.adotados) p.push(`${r.adotados} criado(s) no iPhone trazido(s)`);
      if (r.atualizados_no_app) p.push(`${r.atualizados_no_app} atualizado(s) aqui`);
      if (r.removidos_no_app) p.push(`${r.removidos_no_app} removido(s) aqui`);
      const venc = (r.vencimentos_enviados || 0) + (r.vencimentos_atualizados || 0);
      if (venc) p.push(`${venc} vencimento(s) enviado(s)`);
      if (r.vencimentos_removidos) p.push(`${r.vencimentos_removidos} vencimento(s) removido(s)`);
      if (r.vencimentos_pendentes) p.push(`faltam ${r.vencimentos_pendentes} vencimento(s) (seguem na próxima sincronização)`);
      if (r.importados) p.push(`${r.importados} evento(s) lido(s) dos seus calendários`);
      return p.length ? p.join(' · ') : 'Tudo em dia.';
    }

    function icDesenhar(e) {
      icEstado = e;
      $('ic-config').style.display = e.configurado ? 'none' : '';
      const st = $('ic-status');
      st.textContent = '';
      if (e.configurado) {
        st.append(document.createTextNode(`Conta: ${e.apple_id || '—'} · `));
        st.append(document.createTextNode(e.habilitado ? `ligada · última sincronização ${icQuando(e.ultima_sync)}` : 'desligada'));
        if (e.habilitado && e.ultimo_resultado) {
          const r = document.createElement('div');
          r.textContent = icResumo(e.ultimo_resultado);
          st.appendChild(r);
          (e.ultimo_resultado.erros || []).forEach((m) => {
            const x = document.createElement('div');
            x.style.color = 'var(--warning)';
            x.textContent = m;
            st.appendChild(x);
          });
        }
      }

      const box = $('ic-cals');
      box.textContent = '';
      if (e.calendarios.length && (e.habilitado || e.configurado)) {
        const h = document.createElement('div');
        h.className = 'label';
        h.textContent = 'Calendários do iPhone para mostrar aqui (somente leitura)';
        box.appendChild(h);
        e.calendarios.forEach((c) => {
          const l = document.createElement('label');
          l.className = 'ic-cal';
          const cb = document.createElement('input');
          cb.type = 'checkbox';
          cb.checked = !!c.selecionado;
          cb.addEventListener('change', async () => {
            const marcados = [...box.querySelectorAll('input:checked')].map((i) => i.dataset.href);
            try { icDesenhar(await icApi('selecionar', { calendarios: marcados })); icSincronizar(true); }
            catch (err) { $('sync-msg').textContent = err.message; }
          });
          cb.dataset.href = c.href;
          const cor = document.createElement('span');
          cor.className = 'ic-cor';
          cor.style.background = (c.cor || '#7a6cf0').slice(0, 7);
          l.append(cb, cor, document.createTextNode(c.nome));
          box.appendChild(l);
        });
      }
      $('ic-testar').style.display = e.habilitado ? 'none' : '';
      $('ic-ativar').style.display = e.habilitado ? 'none' : '';
      $('ic-agora').style.display = e.habilitado ? '' : 'none';
      $('ic-desativar').style.display = e.habilitado ? '' : 'none';
      atualizarBotaoIphone();
    }

    async function icCarregar(silencioso) {
      try {
        const e = await icApi();
        icDesenhar(e);
        
      } catch (err) {
        if (!silencioso) $('sync-msg').textContent = err.message;
      }
    }

    /** Sincroniza; em modo silencioso (segundo plano) só recarrega a agenda ao terminar. */
    async function icSincronizar(silencioso) {
      if (icOcupado) return;
      icOcupado = true;
      const msg = $('sync-msg');
      if (!silencioso) { msg.style.color = ''; msg.textContent = 'Sincronizando com o iCloud…'; }
      try {
        const e = await icApi('sincronizar');
        icDesenhar(e);
        if (!silencioso) { msg.style.color = 'var(--success)'; msg.textContent = 'Sincronizado.'; }
        await recarregar();
      } catch (err) {
        if (!silencioso) { msg.style.color = ''; msg.textContent = err.message; }
      } finally { icOcupado = false; }
    }

    async function icTestar() {
      const msg = $('sync-msg');
      msg.style.color = ''; msg.textContent = 'Conectando ao iCloud…';
      try {
        const e = await icApi('testar');
        icDesenhar(e);
        msg.style.color = 'var(--success)';
        msg.textContent = `Conexão ok. ${e.calendarios.length} calendário(s) encontrado(s). Escolha quais mostrar aqui e toque em "Ativar e sincronizar".`;
      } catch (err) { msg.textContent = err.message; }
    }

    async function icAtivar() {
      const msg = $('sync-msg');
      msg.style.color = ''; msg.textContent = 'Ativando e sincronizando…';
      try {
        icOcupado = true;
        const e = await icApi('ativar');
        icDesenhar(e);
        msg.style.color = 'var(--success)'; msg.textContent = 'Sincronização ativada.';
        await recarregar();
      } catch (err) { msg.textContent = err.message; }
      finally { icOcupado = false; }
    }

    async function icDesativar() {
      if (!(await BFApp.modalConfirm('Desativar a sincronização com o iCloud? Os eventos já criados continuam nos dois lados, mas deixam de se atualizar.', 'Desativar'))) return;
      try { icDesenhar(await icApi('desativar')); $('sync-msg').textContent = ''; } catch (err) { $('sync-msg').textContent = err.message; }
    }

    $('ic-testar').addEventListener('click', icTestar);
    $('ic-ativar').addEventListener('click', icAtivar);
    $('ic-agora').addEventListener('click', () => icSincronizar(false));
    $('ic-desativar').addEventListener('click', icDesativar);

    carregarMes();
    icCarregar(true);   // só lê o estado; a sincronização acontece apenas ao tocar em "Sincronizar"
  </script>

</body>
</html>
