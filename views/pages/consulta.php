<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Consulta VB ⇄ SQL</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <link rel="stylesheet" href="../../css/ferramentas.css" />
  <script src="../../js/app-runtime.js"></script>
  <script src="../../js/consulta-tools.js"></script>
  <style>
    .saida { white-space: pre; overflow: auto; min-height: 140px; max-height: 55vh; }
    .vars { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.6rem; }
    .vars .field { margin: 0; }
    .opcoes { display: grid; gap: 0.2rem; }
    .check { display: flex; align-items: center; gap: 0.6rem; min-height: 40px; font-size: 0.86rem; color: var(--text-body); cursor: pointer; }
    .check input { width: 22px; height: 22px; accent-color: var(--primary); flex: none; }
    .aviso { font-size: 0.78rem; color: var(--warning); line-height: 1.45; margin-top: 0.4rem; }
    .acoes { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.6rem; }
    .acoes .btn { flex: 1 1 auto; }
    details > summary { cursor: pointer; font-weight: 700; color: var(--text-heading); min-height: 40px; display: flex; align-items: center; }
    @media (max-width: 640px) { .vars { grid-template-columns: 1fr; } }
    @media (min-width: 900px) { .colunas { display: grid; grid-template-columns: 1fr 1fr; gap: 0.9rem; align-items: start; } .colunas .panel { margin-bottom: 0; } }
  </style>
</head>
<body class="inner-page tone-ferramentas">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Consulta VB ⇄ SQL</h1>
      <p>Limpe a consulta do código ou gere o código a partir da consulta</p>
    </div>
  </div>

  <div class="seg" role="tablist">
    <button type="button" id="m-vb" class="on" role="tab">Código VB → SQL limpo</button>
    <button type="button" id="m-sql" role="tab">SQL → Código VB</button>
  </div>

  <div class="colunas">
    <div class="panel">
      <div class="panel-title" id="t-entrada">Cole o código VB.NET</div>
      <textarea class="input mono" id="entrada" rows="12" spellcheck="false" autocapitalize="off" autocomplete="off" autocorrect="off"
        placeholder='strsql = "select * from clientes " &amp; vbcrlf &amp;&#10;"where id = " &amp; id&#10;dtbconsulta = conexao.retornaDT(strsql, ...)'></textarea>
      <div class="acoes">
        <button class="btn outline small" id="b-colar" type="button"><i data-icone="prancheta"></i> Colar</button>
        <button class="btn outline small" id="b-limpar" type="button">Limpar</button>
        <button class="btn outline small" id="b-trocar" type="button" title="Leva o resultado para a entrada e inverte o modo"><i data-icone="trocar"></i> Trocar</button>
      </div>
      <div class="hint" id="dica" style="display:none; margin-top:0.5rem"></div>

      <details id="opcoes-vb" style="display:none; margin-top:0.6rem">
        <summary>Opções do código VB</summary>
        <div class="row2" style="margin-top:0.5rem">
          <div class="field"><label class="label" for="o-var">Nome da variável</label><input class="input" id="o-var" type="text" autocapitalize="off" autocorrect="off" /></div>
          <div class="field"><label class="label" for="o-quebra">Quebra de linha</label>
            <select class="input" id="o-quebra">
              <option value="vbcrlf">vbcrlf</option><option value="vbCrLf">vbCrLf</option><option value="vbNewLine">vbNewLine</option>
              <option value="Environment.NewLine">Environment.NewLine</option><option value="">(nenhuma)</option>
            </select></div>
        </div>
        <div class="field"><label class="label" for="o-estilo">Estilo</label>
          <select class="input" id="o-estilo">
            <option value="continuacao">Uma instrução: x = "..." &amp; vbcrlf &amp; (continua na linha de baixo)</option>
            <option value="acumular">Linha a linha: x &amp;= "..."</option>
          </select></div>
        <div class="opcoes">
          <label class="check"><input type="checkbox" id="o-vars" /> Transformar {variavel} em concatenação (" &amp; variavel &amp; ")</label>
          <label class="check"><input type="checkbox" id="o-fim" /> Quebra de linha também no fim</label>
          <label class="check"><input type="checkbox" id="o-exec" /> Incluir a linha de execução</label>
        </div>
        <div class="field" id="f-exec" style="display:none"><label class="label" for="o-linha">Linha de execução ({sql} vira o nome da variável)</label>
          <input class="input mono" id="o-linha" type="text" autocapitalize="off" autocorrect="off" spellcheck="false" /></div>
      </details>
    </div>

    <div class="panel">
      <div class="panel-title" id="t-saida">Consulta limpa</div>
      <div class="field" id="f-qual" style="display:none"><label class="label" for="qual">Consulta encontrada</label><select class="input" id="qual"></select></div>
      <textarea class="input mono saida" id="saida" rows="10" readonly spellcheck="false"></textarea>
      <div class="aviso" id="avisos"></div>

      <div id="bloco-vars" style="display:none; margin-top:0.7rem">
        <div class="label" style="margin-bottom:0.4rem">Valores das variáveis (opcional)</div>
        <div class="vars" id="vars"></div>
        <div class="hint" style="margin-top:0.4rem">Preencha para já gerar a consulta pronta para rodar no banco; os campos em branco continuam como {variavel}.</div>
      </div>

      <div class="acoes">
        <button class="btn" id="b-copiar" type="button"><i data-icone="copiar"></i> Copiar</button>
        <button class="btn outline" id="b-linha" type="button" style="display:none">Uma linha</button>
        <button class="btn outline" id="b-salvar" type="button"><i data-icone="arquivo"></i> Salvar nos códigos</button>
      </div>
    </div>
  </div>

  <div class="toast" id="toast" role="status" aria-live="polite" style="display:none"></div>

  <div class="overlay" id="ov">
    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="ov-title">
      <div class="sheet-title" id="ov-title">Salvar nos códigos</div>
      <div class="field"><label class="label" for="s-nome">Nome</label><input class="input" id="s-nome" type="text" maxlength="200" autocomplete="off" /></div>
      <div class="field"><label class="label" for="s-tags">Tags (separadas por vírgula)</label><input class="input" id="s-tags" type="text" autocomplete="off" autocapitalize="off" placeholder="ex.: relatório, clientes" /></div>
      <div class="sheet-msg" id="s-msg"></div>
      <div class="sheet-actions"><button class="btn outline" id="s-cancel" type="button">Cancelar</button><button class="btn" id="s-save" type="button">Salvar</button></div>
    </div>
  </div>

  <script>
    const $ = (id) => document.getElementById(id);
    const CT = window.ConsultaTools;
    const CHAVE_OPC = 'bf_consulta_opcoes';

    let modo = 'vb';                 // 'vb' (VB → SQL) | 'sql' (SQL → VB)
    let consultas = [];              // resultado de vbParaSql
    let atual = 0;                   // consulta escolhida
    let valores = {};                // valores das variáveis
    let umaLinhaLigado = false;

    /* ── opções guardadas no aparelho ── */
    function lerOpcoes() {
      let o = {};
      try { o = JSON.parse(localStorage.getItem(CHAVE_OPC) || '{}'); } catch (_) { /* sem armazenamento */ }
      return Object.assign({}, CT.PADRAO_VB, o);
    }
    function salvarOpcoes() {
      try { localStorage.setItem(CHAVE_OPC, JSON.stringify(opcoesDaTela())); } catch (_) { /* ok */ }
    }
    function opcoesDaTela() {
      return { variavel: $('o-var').value.trim() || 'strsql', quebra: $('o-quebra').value, estilo: $('o-estilo').value,
               converterVariaveis: $('o-vars').checked, quebraNoFim: $('o-fim').checked, incluirExecucao: $('o-exec').checked,
               linhaExecucao: $('o-linha').value };
    }
    function opcoesParaTela() {
      const o = lerOpcoes();
      $('o-var').value = o.variavel; $('o-quebra').value = o.quebra; $('o-estilo').value = o.estilo;
      $('o-vars').checked = !!o.converterVariaveis; $('o-fim').checked = !!o.quebraNoFim; $('o-exec').checked = !!o.incluirExecucao;
      $('o-linha').value = o.linhaExecucao; $('f-exec').style.display = o.incluirExecucao ? '' : 'none';
    }

    /* ── aviso curto ── */
    let toastTimer = null;
    function aviso(texto, erro) {
      const t = $('toast'); t.textContent = texto; t.className = 'toast ' + (erro ? 'erro' : 'ok'); t.style.display = '';
      clearTimeout(toastTimer); toastTimer = setTimeout(() => { t.style.display = 'none'; }, erro ? 6000 : 2500);
    }

    /* ── conversão ao vivo ── */
    function converter() {
      const texto = $('entrada').value;
      $('avisos').textContent = '';
      $('dica').style.display = 'none';

      if (modo === 'vb') {
        const r = CT.vbParaSql(texto);
        consultas = r.consultas;
        if (atual >= consultas.length) atual = 0;
        const q = consultas[atual];
        // várias consultas: escolher qual mostrar
        $('f-qual').style.display = consultas.length > 1 ? '' : 'none';
        if (consultas.length > 1) {
          $('qual').textContent = '';
          consultas.forEach((c, i) => { const o = document.createElement('option'); o.value = i; o.textContent = c.variavel; $('qual').appendChild(o); });
          $('qual').value = atual;
        }
        // campos das variáveis
        const vs = q ? q.variaveis : [];
        $('bloco-vars').style.display = vs.length ? '' : 'none';
        const caixa = $('vars');
        const emUso = [...caixa.querySelectorAll('input')].map(i => i.dataset.nome).join('\n');
        if (emUso !== vs.join('\n')) {
          caixa.textContent = '';
          vs.forEach((nome) => {
            const f = document.createElement('div'); f.className = 'field';
            const l = document.createElement('label'); l.className = 'label'; l.textContent = nome;
            const i = document.createElement('input'); i.className = 'input mono'; i.type = 'text'; i.dataset.nome = nome; i.value = valores[nome] || '';
            i.autocapitalize = 'off'; i.autocomplete = 'off'; i.spellcheck = false;
            i.addEventListener('input', () => { valores[nome] = i.value; atualizarSaida(); });
            f.append(l, i); caixa.appendChild(f);
          });
        }
        $('avisos').textContent = r.avisos.join(' ');
        // texto parece SQL puro (sem aspas): sugere inverter o modo
        if (!q && texto.trim() && !/"/.test(texto) && /\b(select|insert|update|delete|with)\b/i.test(texto)) {
          $('dica').textContent = 'Isso parece SQL puro. Use "SQL → Código VB" para gerar o código.'; $('dica').style.display = '';
        }
        atualizarSaida();
      } else {
        $('f-qual').style.display = 'none'; $('bloco-vars').style.display = 'none';
        $('saida').value = texto.trim() ? CT.sqlParaVb(texto, opcoesDaTela()) : '';
      }
      $('b-copiar').disabled = !$('saida').value;
      $('b-salvar').disabled = !$('saida').value;
    }

    function atualizarSaida() {
      const q = consultas[atual];
      if (!q) { $('saida').value = ''; return; }
      let sql = CT.aplicarValores(q.sql, valores);
      if (umaLinhaLigado) sql = CT.umaLinha(sql);
      $('saida').value = sql;
      $('b-copiar').disabled = !sql; $('b-salvar').disabled = !sql;
    }

    function mudarModo(novo) {
      modo = novo;
      $('m-vb').classList.toggle('on', novo === 'vb'); $('m-sql').classList.toggle('on', novo === 'sql');
      $('t-entrada').textContent = novo === 'vb' ? 'Cole o código VB.NET' : 'Cole a consulta SQL';
      $('t-saida').textContent = novo === 'vb' ? 'Consulta limpa' : 'Código VB.NET';
      $('opcoes-vb').style.display = novo === 'sql' ? '' : 'none';
      $('b-linha').style.display = novo === 'vb' ? '' : 'none';
      $('entrada').placeholder = novo === 'vb'
        ? 'strsql = "select * from clientes " & vbcrlf &\n"where id = " & id\ndtbconsulta = conexao.retornaDT(strsql, ...)'
        : 'select c.id, c.nome\nfrom clientes c\nwhere c.nome = \'{nome}\'';
      converter();
    }

    /* ── copiar / colar ── */
    async function copiar(texto) {
      try { await navigator.clipboard.writeText(texto); }
      catch (_) { const t = document.createElement('textarea'); t.value = texto; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); }
    }

    $('b-copiar').addEventListener('click', async () => { await copiar($('saida').value); aviso('Copiado.'); });
    $('b-colar').addEventListener('click', async () => {
      try { $('entrada').value = await navigator.clipboard.readText(); converter(); }
      catch (_) { aviso('O navegador não deixou colar automaticamente: toque e segure no campo e escolha Colar.', true); $('entrada').focus(); }
    });
    $('b-limpar').addEventListener('click', () => { $('entrada').value = ''; valores = {}; converter(); $('entrada').focus(); });
    $('b-trocar').addEventListener('click', () => {
      const saida = $('saida').value;
      if (!saida) return;
      $('entrada').value = saida; valores = {}; atual = 0;
      mudarModo(modo === 'vb' ? 'sql' : 'vb');
    });
    $('b-linha').addEventListener('click', () => { umaLinhaLigado = !umaLinhaLigado; $('b-linha').textContent = umaLinhaLigado ? 'Várias linhas' : 'Uma linha'; atualizarSaida(); });
    $('qual').addEventListener('change', () => { atual = +$('qual').value; valores = {}; $('vars').textContent = ''; converter(); });

    let espera = null;
    $('entrada').addEventListener('input', () => { clearTimeout(espera); espera = setTimeout(converter, 120); });
    $('m-vb').addEventListener('click', () => mudarModo('vb'));
    $('m-sql').addEventListener('click', () => mudarModo('sql'));
    ['o-var', 'o-quebra', 'o-estilo', 'o-vars', 'o-fim', 'o-exec', 'o-linha'].forEach((id) => $(id).addEventListener('input', () => {
      $('f-exec').style.display = $('o-exec').checked ? '' : 'none'; salvarOpcoes(); converter();
    }));

    /* ── salvar nos códigos ── */
    function apiBase() {
      const { origin, pathname } = window.location;
      return origin + pathname.replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '').replace(/\/[^/]*\.(?:html|php)$/, '') + '/backend/api';
    }
    $('b-salvar').addEventListener('click', () => {
      $('s-nome').value = modo === 'vb' ? CT.sugerirNome($('saida').value) : CT.sugerirNome($('entrada').value) + ' (VB)';
      $('s-tags').value = ''; $('s-msg').textContent = ''; $('ov').classList.add('open');
      setTimeout(() => { $('s-nome').focus(); $('s-nome').select(); }, 50);
    });
    $('s-cancel').addEventListener('click', () => $('ov').classList.remove('open'));
    $('ov').addEventListener('click', (e) => { if (e.target === $('ov')) $('ov').classList.remove('open'); });
    $('s-save').addEventListener('click', async () => {
      const nome = $('s-nome').value.trim();
      if (!nome) { $('s-msg').textContent = 'Dê um nome.'; return; }
      $('s-save').disabled = true;
      try {
        const res = await fetch(apiBase() + '/snippets.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, cache: 'no-store',
          body: JSON.stringify({ acao: 'criar', nome, linguagem: modo === 'vb' ? 'sql' : 'vbnet', tags: $('s-tags').value, conteudo: $('saida').value }) });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Não foi possível salvar.');
        $('ov').classList.remove('open'); aviso('Salvo em Códigos.');
      } catch (e) { $('s-msg').textContent = e.message; }
      finally { $('s-save').disabled = false; }
    });

    /* ── início: opções, e texto vindo de outra tela (Códigos → "Converter") ── */
    opcoesParaTela();
    try {
      const pre = JSON.parse(localStorage.getItem('bf_consulta_prefill') || 'null');
      if (pre && typeof pre.texto === 'string') { $('entrada').value = pre.texto; localStorage.removeItem('bf_consulta_prefill'); mudarModo(pre.modo === 'sql' ? 'sql' : 'vb'); }
      else mudarModo('vb');
    } catch (_) { mudarModo('vb'); }
  </script>
</body>
</html>
