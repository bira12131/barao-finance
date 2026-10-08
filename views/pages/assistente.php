<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Assistente</title>
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
      margin-bottom: 0.6rem;
    }

    /* ── Quanto posso gastar hoje ── */
    .hoje-valor { font-size: 2rem; font-weight: 800; color: var(--text-heading); line-height: 1.1; }
    .hoje-valor.ruim { color: #ff9c9c; }
    .hoje-valor.bom { color: #6fdca2; }
    .hoje-sub { margin-top: 0.3rem; font-size: 0.84rem; color: var(--text-muted); }
    .barra { height: 8px; background: var(--bg-input); border-radius: 99px; overflow: hidden; margin: 0.75rem 0 0.35rem; border: 1px solid var(--border); }
    .barra > i { display: block; height: 100%; background: var(--primary); border-radius: 99px; }
    .barra > i.estourou { background: var(--danger); }
    .kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem; margin-top: 0.7rem; }
    .kpi { background: var(--bg-input); border: 1px solid var(--border); border-radius: 12px; padding: 0.55rem 0.65rem; }
    .kpi small { display: block; font-size: 0.68rem; color: var(--text-muted); }
    .kpi b { font-size: 0.92rem; color: var(--text-heading); }
    .estrategia { margin-top: 0.75rem; padding: 0.65rem 0.75rem; background: var(--bg-input); border: 1px solid var(--border);
      border-radius: 12px; font-size: 0.82rem; color: var(--text-body); }
    .estrategia h4 { margin: 0 0 0.45rem; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-muted); }
    .estrategia ol { margin: 0; padding-left: 1.25rem; display: flex; flex-direction: column; gap: 0.4rem; }
    .estrategia li { line-height: 1.4; }
    .acao { display: flex; gap: 0.5rem; padding: 0.45rem 0; border-top: 1px solid var(--border); font-size: 0.8rem; color: var(--text-body); }
    .acao:first-child { border-top: none; }
    .acao small { display: block; color: var(--text-muted); font-size: 0.7rem; }
    .aviso { padding: 0.7rem 0.8rem; border-radius: 12px; font-size: 0.84rem; background: var(--warning-light); color: #f5c77e; margin-bottom: 0.9rem; }

    /* ── Chat ── */
    .chat { display: flex; flex-direction: column; gap: 0.55rem; max-height: 46vh; min-height: 140px; overflow-y: auto; padding: 0.15rem 0.1rem 0.4rem; }
    .msg { max-width: 88%; padding: 0.6rem 0.8rem; border-radius: 14px; font-size: 0.88rem; line-height: 1.45; white-space: pre-wrap; word-wrap: break-word; }
    .msg.user { align-self: flex-end; background: var(--primary); color: #fff; border-bottom-right-radius: 4px; }
    .msg.assistant { align-self: flex-start; background: var(--bg-input); border: 1px solid var(--border); color: var(--text-body); border-bottom-left-radius: 4px; }
    .msg.erro { align-self: center; background: var(--danger-light); color: #ff9c9c; font-size: 0.82rem; }
    .msg.digitando { color: var(--text-muted); font-style: italic; }
    .sugestoes { display: flex; flex-wrap: wrap; gap: 0.4rem; margin: 0.6rem 0 0.2rem; }
    .chip { border: 1px solid var(--border); background: var(--bg-input); color: var(--text-body); border-radius: 99px;
      padding: 0.4rem 0.75rem; font-size: 0.78rem; cursor: pointer; font-family: inherit; }
    .envio { display: flex; gap: 0.5rem; margin-top: 0.6rem; }
    .envio textarea { flex: 1; min-height: 44px; max-height: 120px; resize: none; border-radius: var(--radius-sm); border: 1.5px solid var(--border);
      background: var(--bg-input); color: var(--text-heading); padding: 0.65rem 0.75rem; font: inherit; font-size: 0.9rem; }
    .btn { min-height: 44px; padding: 0 1rem; border-radius: var(--radius-sm); border: 1.5px solid var(--primary); background: var(--primary);
      color: #fff; font-size: 0.86rem; font-weight: 700; cursor: pointer; font-family: inherit; }
    .btn:disabled { opacity: 0.6; cursor: default; }
    .btn.link { background: none; border: none; color: var(--text-muted); font-size: 0.76rem; font-weight: 500; min-height: 0; padding: 0; text-decoration: underline; }
  </style>
</head>
<body class="inner-page tone-metas">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Assistente</h1>
      <p>Converse com a IA para se controlar e sair das dívidas</p>
    </div>
  </div>

  <div id="aviso"></div>

  <div class="panel" id="hoje"></div>

  <div class="panel">
    <div class="panel-title">Conversa</div>
    <div class="chat" id="chat" aria-live="polite"></div>
    <div class="sugestoes" id="sugestoes"></div>
    <form class="envio" id="form">
      <textarea id="texto" rows="1" maxlength="2000" placeholder="Ex.: quero guardar R$ 1.500 por mês, é possível?"></textarea>
      <button class="btn" id="enviar" type="submit">Enviar</button>
    </form>
    <div style="margin-top:0.6rem"><button class="btn link" id="limpar" type="button">Apagar conversa</button></div>
  </div>

  <div class="panel">
    <div class="panel-title">Últimas ações executadas</div>
    <div id="acoes"></div>
  </div>

  <script>
    const $ = (id) => document.getElementById(id);
    const brl = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);

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
      const res = await fetch(apiBase() + '/assistente.php', opts);
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'Erro ao falar com o servidor.');
      return json.data;
    }

    function el(tag, cls, text) {
      const e = document.createElement(tag);
      if (cls) e.className = cls;
      if (text !== undefined) e.textContent = text;
      return e;
    }

    // **negrito** simples, sem innerHTML (o texto vem da IA e pode conter dados de transações).
    function textoComNegrito(parent, texto) {
      String(texto).split(/(\*\*[^*]+\*\*)/g).forEach((parte) => {
        if (/^\*\*[^*]+\*\*$/.test(parte)) parent.append(el('b', null, parte.slice(2, -2)));
        else if (parte) parent.append(document.createTextNode(parte));
      });
    }

    // Um passo por linha. Planos antigos vinham num parágrafo só ("1. ... 2. ..."): separa pela numeração.
    function passosDoPlano(texto) {
      let partes = String(texto).split(/\r?\n+/);
      if (partes.length === 1) partes = partes[0].split(/\s+(?=\d{1,2}\.\s)/);
      return partes.map((p) => p.replace(/^\s*(?:\d{1,2}[.)]|[-•*])\s*/, '').trim()).filter(Boolean);
    }

    function renderHoje(o, plano) {
      const box = $('hoje');
      box.replaceChildren(el('div', 'panel-title', 'Quanto posso gastar hoje'));

      if (!o.renda_definida) {
        box.append(el('div', 'hoje-sub', 'Informe sua renda mensal na tela Metas para eu calcular seu limite diário.'));
        return;
      }

      const sobra = o.sobra_hoje;
      box.append(el('div', 'hoje-valor ' + (sobra <= 0 ? 'ruim' : 'bom'), brl(Math.max(0, sobra))));
      const sub = sobra > 0
        ? `Limite de ${brl(o.limite_por_dia)} por dia · você já gastou ${brl(o.gasto_hoje)} hoje`
        : (o.estourou_mes
            ? 'Você passou do teto do mês. Hoje o ideal é não gastar.'
            : `Seu limite de hoje (${brl(o.limite_por_dia)}) já foi usado.`);
      box.append(el('div', 'hoje-sub', sub));

      const pct = o.teto_mes > 0 ? Math.min(100, Math.max(0, (o.gasto_mes / o.teto_mes) * 100)) : 100;
      const barra = el('div', 'barra');
      const fill = el('i', o.estourou_mes ? 'estourou' : '');
      fill.style.width = pct + '%';
      barra.append(fill);
      box.append(barra);
      box.append(el('div', 'hoje-sub', `${brl(o.gasto_mes)} de ${brl(o.teto_mes)} no mês · ${o.dias_restantes} dia(s) restantes`));

      const kpis = el('div', 'kpis');
      [['Renda', brl(o.renda_mensal)], ['Separar/mês', brl(o.guardar_mensal)], ['Restante do mês', brl(o.restante_mes)]].forEach(([t, v]) => {
        const k = el('div', 'kpi');
        k.append(el('small', null, t), el('b', null, v));
        kpis.append(k);
      });
      box.append(kpis);

      if (o.guardar_mensal <= 0) {
        box.append(el('div', 'hoje-sub', 'Ainda sem plano: converse com a IA abaixo para definir quanto separar por mês.'));
      }
      if (plano && plano.estrategia) {
        const passos = passosDoPlano(plano.estrategia);
        const caixa = el('div', 'estrategia');
        caixa.append(el('h4', null, 'Plano combinado'));
        const lista = el('ol');
        passos.forEach((p) => { const li = el('li'); textoComNegrito(li, p); lista.append(li); });
        caixa.append(lista);
        box.append(caixa);
      }
    }

    function renderAcoes(lista) {
      const box = $('acoes');
      box.replaceChildren();
      if (!lista || !lista.length) { box.append(el('div', 'hoje-sub', 'Nenhuma ação executada ainda.')); return; }
      lista.forEach((a) => {
        const linha = el('div', 'acao');
        linha.append(el('span', null, a.ok ? '✅' : '⚠️'));
        const corpo = el('div');
        corpo.append(document.createTextNode(String(a.resultado || a.ferramenta).slice(0, 160)));
        const quando = new Date(String(a.criado_em).replace(' ', 'T'));
        corpo.append(el('small', null, `${a.ferramenta} · ${a.canal} · ${isNaN(quando) ? '' : quando.toLocaleString('pt-BR')}`));
        linha.append(corpo);
        box.append(linha);
      });
    }

    function addMsg(papel, texto) {
      const m = el('div', 'msg ' + papel);
      textoComNegrito(m, texto);
      $('chat').append(m);
      $('chat').scrollTop = $('chat').scrollHeight;
      return m;
    }

    const SUGESTOES = [
      'Quero guardar um valor por mês para quitar minhas dívidas',
      'Posso gastar R$ 100 hoje?',
      'Onde estou gastando demais?',
      'Monte um plano para eliminar meus empréstimos',
    ];

    let ocupado = false;
    async function enviar(texto) {
      texto = texto.trim();
      if (!texto || ocupado) return;
      ocupado = true;
      $('enviar').disabled = true;
      $('sugestoes').replaceChildren();
      addMsg('user', texto);
      $('texto').value = '';
      const espera = addMsg('assistant digitando', 'Pensando…');
      try {
        const r = await api({ acao: 'mensagem', texto });
        espera.remove();
        addMsg('assistant', r.resposta);
        renderHoje(r.orcamento, r.plano);
        api().then((d) => renderAcoes(d.acoes)).catch(() => {});
      } catch (e) {
        espera.remove();
        addMsg('erro', e.message);
      } finally {
        ocupado = false;
        $('enviar').disabled = false;
      }
    }

    function mostrarSugestoes() {
      const box = $('sugestoes');
      box.replaceChildren();
      SUGESTOES.forEach((s) => {
        const b = el('button', 'chip', s);
        b.type = 'button';
        b.addEventListener('click', () => enviar(s));
        box.append(b);
      });
    }

    async function carregar() {
      try {
        const d = await api();
        renderHoje(d.orcamento, d.plano);
        renderAcoes(d.acoes);
        if (!d.ia_ativa) {
          $('aviso').replaceChildren(el('div', 'aviso', 'A IA ainda não está ativada. O cálculo do dia já funciona; para conversar, adicione a chave da API em backend/config/assistente.php.'));
        }
        $('chat').replaceChildren();
        d.mensagens.forEach((m) => addMsg(m.papel, m.conteudo));
        if (!d.mensagens.length) {
          addMsg('assistant', 'Oi! Sou seu assistente financeiro. Me diga quanto você quer guardar por mês (ou quanto quer gastar no máximo) e eu vejo com você o que dá para fazer com as contas de hoje e como sair das dívidas.');
          mostrarSugestoes();
        }
      } catch (e) {
        $('hoje').replaceChildren(el('div', 'panel-title', 'Quanto posso gastar hoje'), el('div', 'hoje-sub', e.message));
      }
    }

    $('form').addEventListener('submit', (e) => { e.preventDefault(); enviar($('texto').value); });
    $('texto').addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviar($('texto').value); }
    });
    $('limpar').addEventListener('click', async () => {
      try { await api({ acao: 'limpar' }); await carregar(); } catch (e) { addMsg('erro', e.message); }
    });

    carregar();
  </script>
</body>
</html>
