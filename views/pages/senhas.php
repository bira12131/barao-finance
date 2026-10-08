<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <meta name="referrer" content="no-referrer" />
  <title>Senhas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <link rel="stylesheet" href="../../css/ferramentas.css" />
  <script src="../../js/app-runtime.js"></script>
  <script src="../../js/cofre-crypto.js"></script>
  <style>
    .oculto-visual { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; pointer-events: none; }
    .centro { max-width: 520px; margin: 0 auto; }
    .lista { display: flex; flex-direction: column; gap: 0.5rem; }
    .item { display: flex; align-items: center; gap: 0.75rem; min-height: 60px; padding: 0.55rem 0.75rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; cursor: pointer; width: 100%; text-align: left; color: inherit; font: inherit; }
    .avatar { width: 38px; height: 38px; border-radius: 11px; flex: none; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 1rem; }
    .i-main { flex: 1; min-width: 0; }
    .i-titulo { font-weight: 700; color: var(--text-heading); font-size: 0.94rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .i-sub { font-size: 0.76rem; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .busca { position: sticky; top: 0; z-index: 5; background: var(--bg-page); padding-bottom: 0.6rem; }
    .chave-box { font-size: 1.15rem; letter-spacing: 0.08em; text-align: center; padding: 1rem 0.6rem; border: 2px dashed var(--page-accent); border-radius: 14px; color: var(--text-heading); background: var(--bg-input); word-break: break-all; user-select: all; -webkit-user-select: all; }
    .check { display: flex; align-items: flex-start; gap: 0.6rem; font-size: 0.86rem; color: var(--text-body); cursor: pointer; margin: 0.8rem 0; line-height: 1.4; }
    .check input { width: 22px; height: 22px; accent-color: var(--primary); flex: none; margin-top: 1px; }
    .det { border-top: 1px solid var(--border); padding: 0.6rem 0; }
    .det:first-of-type { border-top: none; }
    .det-r { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; color: var(--text-muted); margin-bottom: 0.25rem; }
    .det-l { display: flex; align-items: center; gap: 0.5rem; }
    .det-v { flex: 1; min-width: 0; color: var(--text-heading); font-size: 0.92rem; word-break: break-all; white-space: pre-wrap; }
    .det-v.mono { font-size: 14px; }
    .det-v a { color: var(--page-accent); }
    .cod { font-size: 1.5rem; font-weight: 800; letter-spacing: 0.12em; }
    .menu-lista { display: flex; flex-direction: column; gap: 0.4rem; }
    .menu-lista .btn { justify-content: flex-start; }
    .gerada { word-break: break-all; padding: 0.8rem; border-radius: 12px; background: var(--bg-input); border: 1px solid var(--border); color: var(--text-heading); min-height: 3rem; }
    .opcoes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 0.8rem; }
    .alerta { font-size: 0.8rem; color: #ffb4b4; background: var(--danger-light); border: 1px solid rgba(217,79,79,0.5); border-radius: 12px; padding: 0.55rem 0.7rem; margin: 0.6rem 0; line-height: 1.45; }
    input[type=range] { width: 100%; accent-color: var(--primary); height: 32px; }
  </style>
</head>
<body class="inner-page tone-ferramentas">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Senhas</h1>
      <p>Cofre protegido pela sua senha mestra, cifrado no seu aparelho</p>
    </div>
    <div class="page-header-actions" id="acoes-topo" style="display:none">
      <button class="btn outline" id="b-bloquear" type="button"><i data-icone="cadeado"></i> Bloquear</button>
      <button class="icon-btn" id="b-menu" type="button" aria-label="Mais opções"><i data-icone="mais"></i></button>
    </div>
  </div>

  <div id="carregando" class="empty">Carregando…</div>

  <!-- Criar o cofre -->
  <section id="v-criar" class="centro" style="display:none">
    <div class="panel">
      <div class="panel-title">Criar o cofre de senhas</div>
      <p class="note" style="margin-bottom:0.8rem">Suas senhas são <b>cifradas aqui no seu aparelho</b> antes de ir para o servidor. O servidor (e quem invadir o banco de dados) só enxerga texto embaralhado.</p>
      <p class="note" style="margin-bottom:0.9rem">A chave é a sua <b>senha mestra</b>. Ela não é guardada em lugar nenhum, então <b>não existe "esqueci minha senha" por e-mail</b>: se perder a senha mestra e a chave de recuperação, ninguém consegue abrir o cofre, nem eu.</p>
      <form id="f-criar" autocomplete="on">
        <input class="oculto-visual" type="text" name="username" autocomplete="username" value="Cofre Barão Finance" tabindex="-1" aria-hidden="true" />
        <div class="field"><label class="label" for="c-senha">Senha mestra (mínimo 8 caracteres)</label>
          <input class="input" id="c-senha" name="password" type="password" autocomplete="new-password" autocapitalize="off" /></div>
        <div class="forca"><i id="c-forca"></i></div>
        <div class="hint" id="c-forca-txt" style="margin:0.3rem 0 0.7rem"></div>
        <div class="field"><label class="label" for="c-senha2">Repita a senha mestra</label>
          <input class="input" id="c-senha2" type="password" autocomplete="new-password" autocapitalize="off" /></div>
        <div class="hint" style="margin-bottom:0.6rem">Dica: o iPhone pode sugerir uma senha forte e guardá-la no app Senhas da Apple. Assim a mestra fica protegida pelo Face ID.</div>
        <div class="sheet-msg" id="c-msg"></div>
        <button class="btn" id="c-criar" type="submit" style="width:100%">Criar cofre</button>
      </form>
    </div>
  </section>

  <!-- Chave de recuperação (mostrada uma vez) -->
  <section id="v-chave" class="centro" style="display:none">
    <div class="panel">
      <div class="panel-title">Sua chave de recuperação</div>
      <p class="note" style="margin-bottom:0.8rem">Se você esquecer a senha mestra, <b>só esta chave</b> abre o cofre. Ela aparece <b>uma única vez</b>. Guarde fora do aparelho: por exemplo numa <b>nota protegida do app Senhas/Notas da Apple</b> e impressa.</p>
      <div class="chave-box mono" id="chave-texto"></div>
      <div class="acoes" style="display:flex; gap:0.5rem; margin-top:0.7rem; flex-wrap:wrap">
        <button class="btn outline" id="k-copiar" type="button" style="flex:1"><i data-icone="copiar"></i> Copiar</button>
        <button class="btn outline" id="k-baixar" type="button" style="flex:1"><i data-icone="baixar"></i> Baixar .txt</button>
      </div>
      <label class="check"><input type="checkbox" id="k-ok" /> Guardei a chave em um lugar seguro, fora deste aparelho.</label>
      <button class="btn" id="k-continuar" type="button" style="width:100%" disabled>Continuar</button>
    </div>
  </section>

  <!-- Cofre bloqueado -->
  <section id="v-bloq" class="centro" style="display:none">
    <div class="panel">
      <div class="panel-title">Cofre bloqueado</div>
      <form id="f-abrir" autocomplete="on">
        <input class="oculto-visual" type="text" name="username" autocomplete="username" value="Cofre Barão Finance" tabindex="-1" aria-hidden="true" />
        <div class="field"><label class="label" for="a-senha">Senha mestra</label>
          <input class="input" id="a-senha" name="password" type="password" autocomplete="current-password" autocapitalize="off" /></div>
        <div class="sheet-msg" id="a-msg"></div>
        <button class="btn" id="a-abrir" type="submit" style="width:100%">Abrir cofre</button>
      </form>
      <button class="btn outline small" id="a-esqueci" type="button" style="margin-top:0.8rem">Esqueci a senha mestra</button>
    </div>
  </section>

  <!-- Recuperar com a chave -->
  <section id="v-esqueci" class="centro" style="display:none">
    <div class="panel">
      <div class="panel-title">Recuperar com a chave de recuperação</div>
      <form id="f-esqueci" autocomplete="off">
        <div class="field"><label class="label" for="r-chave">Chave de recuperação</label>
          <input class="input mono" id="r-chave" type="text" autocapitalize="characters" autocomplete="off" autocorrect="off" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX" /></div>
        <div class="field"><label class="label" for="r-nova">Nova senha mestra (mínimo 8 caracteres)</label>
          <input class="input" id="r-nova" type="password" autocomplete="new-password" autocapitalize="off" /></div>
        <div class="field"><label class="label" for="r-nova2">Repita a nova senha mestra</label>
          <input class="input" id="r-nova2" type="password" autocomplete="new-password" autocapitalize="off" /></div>
        <div class="sheet-msg" id="r-msg"></div>
        <div class="sheet-actions"><button class="btn outline" id="r-voltar" type="button">Voltar</button><button class="btn" id="r-ok" type="submit">Recuperar</button></div>
      </form>
      <hr style="border:none; border-top:1px solid var(--border); margin:1rem 0" />
      <p class="note">Perdeu também a chave de recuperação? Só resta apagar o cofre e começar de novo (as senhas guardadas aqui se perdem).</p>
      <button class="btn danger small" id="r-apagar" type="button" style="margin-top:0.5rem">Apagar o cofre…</button>
    </div>
  </section>

  <!-- Cofre aberto -->
  <section id="v-aberto" style="display:none">
    <div id="aviso-itens" class="alerta" style="display:none"></div>
    <div class="busca"><input class="input" id="busca" type="search" placeholder="Buscar por nome, usuário ou site…" autocomplete="off" autocapitalize="off" spellcheck="false" /></div>
    <div class="lista" id="lista"></div>
    <button class="fab" id="fab" aria-label="Nova senha">+</button>
  </section>

  <div class="toast" id="toast" role="status" aria-live="polite" style="display:none"></div>

  <!-- Detalhe -->
  <div class="overlay" id="ov-det"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="det-titulo">
    <div class="sheet-title" id="det-titulo"></div>
    <div id="det-corpo"></div>
    <div class="sheet-actions"><button class="btn danger" id="det-excluir" type="button"><i data-icone="lixeira"></i> Excluir</button><button class="btn outline" id="det-editar" type="button"><i data-icone="lapis"></i> Editar</button><button class="btn outline" id="det-fechar" type="button">Fechar</button></div>
  </div></div>

  <!-- Editar / criar -->
  <div class="overlay" id="ov-ed"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="ed-titulo">
    <div class="sheet-title" id="ed-titulo">Nova senha</div>
    <div class="field"><label class="label" for="e-titulo">Nome</label><input class="input" id="e-titulo" type="text" maxlength="200" autocomplete="off" placeholder="Ex.: GitHub" /></div>
    <div class="field"><label class="label" for="e-url">Site</label><input class="input" id="e-url" type="url" autocomplete="off" autocapitalize="off" placeholder="https://" /></div>
    <div class="field"><label class="label" for="e-user">Usuário ou e-mail</label><input class="input" id="e-user" type="text" autocomplete="off" autocapitalize="off" autocorrect="off" /></div>
    <div class="field"><label class="label" for="e-senha">Senha</label>
      <div class="linha"><input class="input mono" id="e-senha" type="password" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-lpignore="true" />
        <button class="icon-btn" id="e-olho" type="button" aria-label="Mostrar senha"><i data-icone="olho"></i></button><button class="icon-btn" id="e-gerar" type="button" aria-label="Gerar senha"><i data-icone="aleatorio"></i></button></div>
      <div class="forca"><i id="e-forca"></i></div><div class="hint" id="e-forca-txt"></div></div>
    <div class="field"><label class="label" for="e-totp">Código de verificação (opcional)</label>
      <input class="input mono" id="e-totp" type="text" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" placeholder="chave secreta ou otpauth://…" />
      <span class="hint">A chave que o site mostra ao ativar a verificação em duas etapas. Aqui o app gera os códigos de 6 dígitos.</span></div>
    <div class="field"><label class="label" for="e-notas">Notas</label><textarea class="input" id="e-notas" rows="3"></textarea></div>
    <div class="sheet-msg" id="e-msg"></div>
    <div class="sheet-actions"><button class="btn outline" id="e-cancelar" type="button">Cancelar</button><button class="btn" id="e-salvar" type="button">Salvar</button></div>
  </div></div>

  <!-- Gerador -->
  <div class="overlay" id="ov-gen"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="g-titulo">
    <div class="sheet-title" id="g-titulo">Gerador de senhas</div>
    <div class="gerada mono" id="g-saida"></div>
    <div class="field" style="margin-top:0.8rem"><label class="label" for="g-tam">Tamanho: <span id="g-tam-v">20</span></label><input type="range" id="g-tam" min="8" max="64" value="20" /></div>
    <div class="opcoes">
      <label class="check"><input type="checkbox" id="g-min" checked /> minúsculas</label><label class="check"><input type="checkbox" id="g-mai" checked /> MAIÚSCULAS</label>
      <label class="check"><input type="checkbox" id="g-num" checked /> números</label><label class="check"><input type="checkbox" id="g-sim" checked /> símbolos</label>
    </div>
    <label class="check" style="margin-top:0"><input type="checkbox" id="g-amb" checked /> evitar caracteres parecidos (I, l, 1, O, 0)</label>
    <div class="sheet-actions"><button class="btn outline" id="g-outra" type="button"><i data-icone="aleatorio"></i> Gerar outra</button><button class="btn outline" id="g-copiar" type="button"><i data-icone="copiar"></i> Copiar</button><button class="btn" id="g-usar" type="button" style="display:none">Usar esta</button><button class="btn outline" id="g-fechar" type="button">Fechar</button></div>
  </div></div>

  <!-- Menu -->
  <div class="overlay" id="ov-menu"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="m-titulo">
    <div class="sheet-title" id="m-titulo">Opções do cofre</div>
    <div class="menu-lista">
      <button class="btn outline" id="m-gerador" type="button"><i data-icone="aleatorio"></i> Gerador de senhas</button>
      <button class="btn outline" id="m-importar" type="button"><i data-icone="baixar"></i> Importar de arquivo CSV (Apple Senhas, Chrome, 1Password)</button>
      <button class="btn outline" id="m-exportar" type="button"><i data-icone="enviar"></i> Exportar para CSV (formato Apple Senhas)</button>
      <button class="btn outline" id="m-troca" type="button"><i data-icone="chave"></i> Trocar a senha mestra</button>
      <button class="btn outline" id="m-recup" type="button"><i data-icone="boia"></i> Gerar nova chave de recuperação</button>
      <button class="btn outline" id="m-bloquear" type="button"><i data-icone="cadeado"></i> Bloquear agora</button>
      <button class="btn danger" id="m-apagar" type="button"><i data-icone="lixeira"></i> Apagar o cofre inteiro…</button>
    </div>
    <div class="sheet-actions"><button class="btn outline" id="m-fechar" type="button">Fechar</button></div>
  </div></div>

  <!-- Importar -->
  <div class="overlay" id="ov-imp"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="i-titulo">
    <div class="sheet-title" id="i-titulo">Importar senhas de um arquivo CSV</div>
    <p class="note" style="margin-bottom:0.7rem">No Mac (macOS 15 ou mais novo): app <b>Senhas › Arquivo › Exportar todas as senhas…</b>. O arquivo de Chrome e do 1Password também funciona. O arquivo fica em texto aberto: <b>apague-o depois de importar</b>.</p>
    <input class="input" id="i-arquivo" type="file" accept=".csv,text/csv,text/plain" />
    <div class="note" id="i-resumo" style="margin-top:0.7rem"></div>
    <div class="sheet-msg" id="i-msg"></div>
    <div class="sheet-actions"><button class="btn outline" id="i-cancelar" type="button">Cancelar</button><button class="btn" id="i-ok" type="button" disabled>Importar</button></div>
  </div></div>

  <!-- Trocar a senha mestra -->
  <div class="overlay" id="ov-troca"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="t-titulo">
    <div class="sheet-title" id="t-titulo">Trocar a senha mestra</div>
    <form id="f-troca" autocomplete="off">
      <div class="field"><label class="label" for="t-atual">Senha mestra atual</label><input class="input" id="t-atual" type="password" autocomplete="current-password" autocapitalize="off" /></div>
      <div class="field"><label class="label" for="t-nova">Nova senha mestra (mínimo 8 caracteres)</label><input class="input" id="t-nova" type="password" autocomplete="new-password" autocapitalize="off" /></div>
      <div class="field"><label class="label" for="t-nova2">Repita a nova senha</label><input class="input" id="t-nova2" type="password" autocomplete="new-password" autocapitalize="off" /></div>
      <div class="sheet-msg" id="t-msg"></div>
      <div class="sheet-actions"><button class="btn outline" id="t-cancelar" type="button">Cancelar</button><button class="btn" id="t-ok" type="submit">Trocar</button></div>
    </form>
  </div></div>

  <!-- Apagar o cofre -->
  <div class="overlay" id="ov-apagar"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="p-titulo">
    <div class="sheet-title" id="p-titulo">Apagar o cofre inteiro</div>
    <div class="alerta">Isso apaga <b>todas</b> as senhas guardadas e não tem volta. Só faça se perdeu a senha mestra e a chave de recuperação.</div>
    <div class="field"><label class="label" for="p-conf">Digite APAGAR COFRE para confirmar</label><input class="input" id="p-conf" type="text" autocomplete="off" autocapitalize="characters" /></div>
    <div class="sheet-msg" id="p-msg"></div>
    <div class="sheet-actions"><button class="btn outline" id="p-cancelar" type="button">Cancelar</button><button class="btn danger" id="p-ok" type="button">Apagar tudo</button></div>
  </div></div>

  <script>
    const $ = (id) => document.getElementById(id);
    const CC = window.CofreCrypto;
    const MIN_SENHA = 8;
    const OCIOSO_MS = 5 * 60 * 1000;          // bloqueia após 5 min sem uso
    const SEGUNDO_PLANO_MS = 90 * 1000;       // ...ou 90 s com o app em segundo plano
    const LIMPAR_AREA_MS = 30 * 1000;         // limpa a área de transferência 30 s após copiar uma senha

    let config = null, sessao = null, itens = [], editandoId = null, aposChave = 'aberto';
    let ultimoUso = Date.now(), saiuEm = null, timerTotp = null, timerArea = null, tentativas = 0, bloqueadoAte = 0;

    /* ── utilidades ── */
    function apiBase() {
      const { origin, pathname } = window.location;
      return origin + pathname.replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '').replace(/\/[^/]*\.(?:html|php)$/, '') + '/backend/api';
    }
    async function api(corpo) {
      const opts = corpo ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(corpo), cache: 'no-store' } : { cache: 'no-store' };
      const res = await fetch(apiBase() + '/cofre.php', opts);
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'Erro ao falar com o servidor.');
      return json.data;
    }
    function el(tag, cls, texto) { const e = document.createElement(tag); if (cls) e.className = cls; if (texto !== undefined) e.textContent = texto; return e; }
    let toastTimer = null;
    function aviso(texto, erro) {
      const t = $('toast'); t.textContent = texto; t.className = 'toast ' + (erro ? 'erro' : 'ok'); t.style.display = '';
      clearTimeout(toastTimer); toastTimer = setTimeout(() => { t.style.display = 'none'; }, erro ? 6000 : 3000);
    }
    function mostrar(nome) {
      ['carregando', 'v-criar', 'v-chave', 'v-bloq', 'v-esqueci', 'v-aberto'].forEach(id => { $(id).style.display = (id === nome) ? '' : 'none'; });
      $('acoes-topo').style.display = nome === 'v-aberto' ? '' : 'none';
    }
    const abrirSheet = (id) => $(id).classList.add('open');
    const fecharSheet = (id) => $(id).classList.remove('open');
    function fecharTodos() { document.querySelectorAll('.overlay.open').forEach(o => o.classList.remove('open')); }
    function ocupado(botao, texto, fn) {
      const antes = botao.textContent; botao.disabled = true; botao.textContent = texto;
      return Promise.resolve().then(fn).finally(() => { botao.disabled = false; botao.textContent = antes; });
    }

    /** Copia; para senhas, limpa a área de transferência depois de 30 s. */
    async function copiar(texto, sensivel, rotulo) {
      try { await navigator.clipboard.writeText(texto); }
      catch (_) { const t = document.createElement('textarea'); t.value = texto; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); }
      if (sensivel) {
        clearTimeout(timerArea);
        timerArea = setTimeout(() => { try { navigator.clipboard.writeText(' '); } catch (_) { /* sem permissão */ } }, LIMPAR_AREA_MS);
      }
      aviso((rotulo || 'Copiado') + (sensivel ? ' (a área de transferência é limpa em 30 s).' : '.'));
    }

    function medidor(barra, txt, senha) {
      const f = CC.forcaSenha(senha);
      const cor = { vazia: 'transparent', fraca: '#d94f4f', 'razoável': '#d97706', forte: '#2aa566', excelente: '#2aa566' }[f.nivel];
      barra.style.width = Math.min(100, f.bits / 1.1) + '%'; barra.style.background = cor;
      txt.textContent = senha ? 'Força: ' + f.nivel + ' (' + f.bits + ' bits)' : '';
    }

    const CORES = ['#6c5ce7', '#0984e3', '#00b894', '#e17055', '#d63031', '#e84393', '#b8860b', '#00a8b5'];
    function corDe(nome) { let h = 0; for (const c of nome) h = (h * 31 + c.charCodeAt(0)) >>> 0; return CORES[h % CORES.length]; }

    /* ── início ── */
    async function iniciar() {
      try {
        const d = await api();
        config = d.configurado ? d.config : null;
        window.__itensCifrados = d.itens || [];
        mostrar(d.configurado ? 'v-bloq' : 'v-criar');
        if (d.configurado) setTimeout(() => $('a-senha').focus(), 80);
      } catch (e) { $('carregando').textContent = e.message; $('carregando').style.display = ''; }
    }

    /* ── criar o cofre ── */
    $('c-senha').addEventListener('input', () => medidor($('c-forca'), $('c-forca-txt'), $('c-senha').value));
    $('f-criar').addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const s = $('c-senha').value, s2 = $('c-senha2').value, msg = $('c-msg');
      if (s.length < MIN_SENHA) { msg.textContent = 'A senha mestra precisa ter pelo menos ' + MIN_SENHA + ' caracteres.'; return; }
      if (s !== s2) { msg.textContent = 'As duas senhas não são iguais.'; return; }
      if (CC.forcaSenha(s).bits < 40 && !(await BFApp.modalConfirm('Essa senha mestra é fraca. Quem tiver acesso ao banco poderia tentar adivinhá-la. Usar mesmo assim?', 'Senha fraca'))) return;
      msg.textContent = '';
      await ocupado($('c-criar'), 'Criando…', async () => {
        try {
          const r = await CC.criarCofre(s);
          await api({ acao: 'criar', config: r.config });
          config = r.config; sessao = r.sessao; itens = []; window.__itensCifrados = [];
          $('c-senha').value = ''; $('c-senha2').value = '';
          mostrarChave(r.chaveRecuperacao, 'aberto');
        } catch (e) { msg.textContent = e.message; }
      });
    });

    function mostrarChave(chave, depois) {
      aposChave = depois; $('chave-texto').textContent = chave; $('k-ok').checked = false; $('k-continuar').disabled = true;
      mostrar('v-chave');
    }
    $('k-ok').addEventListener('change', () => { $('k-continuar').disabled = !$('k-ok').checked; });
    $('k-copiar').addEventListener('click', () => copiar($('chave-texto').textContent, true, 'Chave copiada'));
    $('k-baixar').addEventListener('click', () => {
      const texto = 'Barão Finance - chave de recuperação do cofre de senhas\r\n\r\n' + $('chave-texto').textContent + '\r\n\r\nGuarde fora do aparelho. Só esta chave abre o cofre se a senha mestra for esquecida.\r\n';
      const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([texto], { type: 'text/plain;charset=utf-8' })); a.download = 'chave-de-recuperacao-cofre.txt';
      document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 2000);
    });
    $('k-continuar').addEventListener('click', () => { $('chave-texto').textContent = ''; abrirCofreNaTela(); });

    /* ── abrir ── */
    async function decifrarTodos() {
      itens = []; let falhas = 0;
      for (const c of (window.__itensCifrados || [])) {
        try { itens.push(Object.assign({ id: c.id, _atualizado: c.atualizado_em }, await CC.decifrarItem(sessao, c.id, { iv: c.iv, ct: c.ct }))); }
        catch (_) { falhas++; }
      }
      const aviso = $('aviso-itens');
      aviso.style.display = falhas ? '' : 'none';
      aviso.textContent = falhas ? falhas + ' item(ns) não puderam ser lidos (dados alterados ou de outro cofre).' : '';
    }

    async function abrirCofreNaTela() {
      await decifrarTodos();
      ultimoUso = Date.now(); tentativas = 0;
      mostrar('v-aberto'); $('busca').value = ''; desenharLista();
    }

    $('f-abrir').addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const msg = $('a-msg'); const espera = Math.ceil((bloqueadoAte - Date.now()) / 1000);
      if (espera > 0) { msg.textContent = 'Aguarde ' + espera + ' s para tentar de novo.'; return; }
      await ocupado($('a-abrir'), 'Abrindo…', async () => {
        try {
          sessao = await CC.abrirCofre(config, $('a-senha').value);
          $('a-senha').value = ''; msg.textContent = '';
          await abrirCofreNaTela();
        } catch (e) {
          tentativas++;
          if (tentativas >= 5) bloqueadoAte = Date.now() + Math.min(60, Math.pow(2, tentativas - 4)) * 1000;
          msg.textContent = e.message === 'senha_incorreta' ? 'Senha mestra incorreta.' + (tentativas >= 5 ? ' Aguarde um pouco antes de tentar de novo.' : '') : e.message;
        }
      });
    });

    /* ── esqueci: chave de recuperação ── */
    $('a-esqueci').addEventListener('click', () => { $('r-msg').textContent = ''; mostrar('v-esqueci'); $('r-chave').focus(); });
    $('r-voltar').addEventListener('click', () => mostrar('v-bloq'));
    $('f-esqueci').addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const msg = $('r-msg'), nova = $('r-nova').value;
      if (nova.length < MIN_SENHA) { msg.textContent = 'A nova senha mestra precisa ter pelo menos ' + MIN_SENHA + ' caracteres.'; return; }
      if (nova !== $('r-nova2').value) { msg.textContent = 'As duas senhas não são iguais.'; return; }
      await ocupado($('r-ok'), 'Recuperando…', async () => {
        try {
          sessao = await CC.abrirComRecuperacao(config, $('r-chave').value);
          const troca = await CC.novaSenhaMestra(sessao, nova);
          await api({ acao: 'trocar_mestra', config: troca });
          config = Object.assign({}, config, troca);
          $('r-chave').value = ''; $('r-nova').value = ''; $('r-nova2').value = ''; msg.textContent = '';
          aviso('Senha mestra trocada.');
          await abrirCofreNaTela();
        } catch (e) { msg.textContent = e.message === 'recuperacao_incorreta' ? 'Chave de recuperação incorreta.' : e.message; sessao = null; }
      });
    });

    /* ── lista ── */
    function desenharLista() {
      const q = $('busca').value.trim().toLowerCase();
      const box = $('lista'); box.textContent = '';
      const vis = itens.filter(i => !q || (i.titulo + ' ' + i.usuario + ' ' + i.url).toLowerCase().includes(q))
        .sort((a, b) => a.titulo.localeCompare(b.titulo, 'pt-BR', { sensitivity: 'base' }));
      if (!vis.length) { box.appendChild(el('div', 'empty', itens.length ? 'Nada encontrado.' : 'O cofre está vazio. Toque em + para guardar a primeira senha, ou use o menu de três pontos › Importar para trazer as do app Senhas da Apple.')); return; }
      vis.forEach(i => {
        const b = el('button', 'item'); b.type = 'button';
        const av = el('span', 'avatar', (i.titulo.trim()[0] || '?').toUpperCase()); av.style.background = corDe(i.titulo);
        const m = el('div', 'i-main'); m.append(el('div', 'i-titulo', i.titulo), el('div', 'i-sub', i.usuario || i.url.replace(/^https?:\/\//, '') || ' '));
        b.append(av, m); b.addEventListener('click', () => abrirDetalhe(i.id)); box.appendChild(b);
      });
    }
    let esperaBusca = null;
    $('busca').addEventListener('input', () => { clearTimeout(esperaBusca); esperaBusca = setTimeout(desenharLista, 120); });

    /* ── detalhe ── */
    function urlSegura(u) {
      let t = String(u || '').trim(); if (!t) return '';
      if (!/^[a-z][a-z0-9+.-]*:/i.test(t)) t = 'https://' + t;
      return /^https?:\/\//i.test(t) ? t : '';
    }
    function linhaDet(rotulo, conteudo, botoes) {
      const d = el('div', 'det'); d.appendChild(el('div', 'det-r', rotulo));
      const l = el('div', 'det-l'); l.appendChild(conteudo);
      (botoes || []).forEach(b => l.appendChild(b)); d.appendChild(l); return d;
    }
    function botaoIcone(icone, rotulo, fn) { const b = el('button', 'icon-btn'); b.type = 'button'; b.appendChild(Icone.el(icone)); b.setAttribute('aria-label', rotulo); b.addEventListener('click', fn); return b; }

    function abrirDetalhe(id) {
      const i = itens.find(x => x.id === id); if (!i) return;
      editandoId = id; ultimoUso = Date.now();
      $('det-titulo').textContent = i.titulo;
      const c = $('det-corpo'); c.textContent = '';
      if (i.usuario) c.appendChild(linhaDet('Usuário', el('div', 'det-v', i.usuario), [botaoIcone('copiar', 'Copiar usuário', () => copiar(i.usuario, false, 'Usuário copiado'))]));
      if (i.senha) {
        const v = el('div', 'det-v mono', '••••••••••'); let visivel = false;
        const olho = botaoIcone('olho', 'Mostrar ou ocultar a senha', () => { visivel = !visivel; v.textContent = visivel ? i.senha : '••••••••••'; olho.replaceChildren(Icone.el(visivel ? 'olho-off' : 'olho')); });
        c.appendChild(linhaDet('Senha', v, [olho, botaoIcone('copiar', 'Copiar senha', () => copiar(i.senha, true, 'Senha copiada'))]));
      }
      const u = urlSegura(i.url);
      if (i.url) {
        const v = el('div', 'det-v');
        if (u) { const a = el('a', null, i.url); a.href = u; a.target = '_blank'; a.rel = 'noopener noreferrer'; v.appendChild(a); } else v.textContent = i.url;
        c.appendChild(linhaDet('Site', v, [botaoIcone('copiar', 'Copiar site', () => copiar(i.url, false, 'Site copiado'))]));
      }
      if (i.totp) {
        const cod = el('div', 'det-v cod mono', '······'); const resta = el('span', 'hint', '');
        const caixa = el('div', 'det-v'); caixa.append(cod, resta);
        let ultimo = '';
        const atualizar = async () => { try { const r = await CC.totp(i.totp); ultimo = r.codigo; cod.textContent = r.codigo.slice(0, 3) + ' ' + r.codigo.slice(3); resta.textContent = 'muda em ' + r.restante + ' s'; } catch (_) { cod.textContent = 'inválido'; resta.textContent = 'confira a chave'; } };
        atualizar(); clearInterval(timerTotp); timerTotp = setInterval(atualizar, 1000);
        c.appendChild(linhaDet('Código de verificação', caixa, [botaoIcone('copiar', 'Copiar código', () => copiar(ultimo, true, 'Código copiado'))]));
      }
      if (i.notas) c.appendChild(linhaDet('Notas', el('div', 'det-v', i.notas)));
      abrirSheet('ov-det');
    }
    function fecharDetalhe() { clearInterval(timerTotp); fecharSheet('ov-det'); $('det-corpo').textContent = ''; $('det-titulo').textContent = ''; }
    $('det-fechar').addEventListener('click', fecharDetalhe);
    $('det-editar').addEventListener('click', () => { const id = editandoId; fecharDetalhe(); abrirEdicao(id); });
    $('det-excluir').addEventListener('click', async () => {
      const i = itens.find(x => x.id === editandoId); if (!i) return;
      if (!(await BFApp.modalConfirm('Excluir "' + i.titulo + '" do cofre?', 'Excluir senha'))) return;
      try { await api({ acao: 'excluir_item', id: i.id }); itens = itens.filter(x => x.id !== i.id); fecharDetalhe(); desenharLista(); aviso('Excluído.'); }
      catch (e) { aviso(e.message, true); }
    });

    /* ── editar / criar ── */
    function abrirEdicao(id) {
      editandoId = id || null;
      const i = id ? itens.find(x => x.id === id) : null;
      $('ed-titulo').textContent = i ? 'Editar senha' : 'Nova senha';
      $('e-titulo').value = i ? i.titulo : ''; $('e-url').value = i ? i.url : ''; $('e-user').value = i ? i.usuario : '';
      $('e-senha').value = i ? i.senha : ''; $('e-senha').type = 'password'; $('e-olho').replaceChildren(Icone.el('olho')); $('e-totp').value = i ? i.totp : ''; $('e-notas').value = i ? i.notas : '';
      $('e-msg').textContent = ''; medidor($('e-forca'), $('e-forca-txt'), $('e-senha').value);
      abrirSheet('ov-ed'); setTimeout(() => $('e-titulo').focus(), 60);
    }
    $('fab').addEventListener('click', () => abrirEdicao(null));
    $('e-senha').addEventListener('input', () => medidor($('e-forca'), $('e-forca-txt'), $('e-senha').value));
    $('e-olho').addEventListener('click', () => { const ver = $('e-senha').type === 'password'; $('e-senha').type = ver ? 'text' : 'password'; $('e-olho').replaceChildren(Icone.el(ver ? 'olho-off' : 'olho')); });
    $('e-cancelar').addEventListener('click', () => fecharSheet('ov-ed'));
    $('e-salvar').addEventListener('click', async () => {
      const msg = $('e-msg');
      const obj = { titulo: $('e-titulo').value.trim(), url: $('e-url').value.trim(), usuario: $('e-user').value.trim(), senha: $('e-senha').value,
                    totp: $('e-totp').value.trim(), notas: $('e-notas').value };
      if (!obj.titulo) { msg.textContent = 'Dê um nome para o item.'; $('e-titulo').focus(); return; }
      if (obj.totp) { try { await CC.totp(obj.totp); } catch (_) { msg.textContent = 'A chave do código de verificação não é válida (use a chave secreta ou o link otpauth://).'; return; } }
      await ocupado($('e-salvar'), 'Salvando…', async () => {
        try {
          const id = editandoId || crypto.randomUUID();
          const blob = await CC.cifrarItem(sessao, id, obj);
          await api({ acao: 'salvar_item', id, iv: blob.iv, ct: blob.ct });
          const novo = Object.assign({ id }, obj);
          const pos = itens.findIndex(x => x.id === id);
          if (pos >= 0) itens[pos] = novo; else itens.push(novo);
          fecharSheet('ov-ed'); desenharLista(); aviso('Salvo no cofre.');
        } catch (e) { msg.textContent = e.message; }
      });
    });

    /* ── gerador ── */
    let destinoGerador = null;       // quando aberto pelo editor, "Usar esta" preenche a senha
    function gerar() {
      try {
        $('g-saida').textContent = CC.gerarSenha({ tamanho: +$('g-tam').value, minusculas: $('g-min').checked, maiusculas: $('g-mai').checked,
                                                   numeros: $('g-num').checked, simbolos: $('g-sim').checked, evitarAmbiguos: $('g-amb').checked });
      } catch (_) { $('g-saida').textContent = 'Marque ao menos um tipo de caractere.'; }
      $('g-tam-v').textContent = $('g-tam').value;
    }
    function abrirGerador(paraEditor) {
      destinoGerador = paraEditor ? true : null; $('g-usar').style.display = paraEditor ? '' : 'none'; gerar(); abrirSheet('ov-gen');
    }
    ['g-tam', 'g-min', 'g-mai', 'g-num', 'g-sim', 'g-amb'].forEach(id => $(id).addEventListener('input', gerar));
    $('g-outra').addEventListener('click', gerar);
    $('g-copiar').addEventListener('click', () => copiar($('g-saida').textContent, true, 'Senha copiada'));
    $('g-usar').addEventListener('click', () => { $('e-senha').value = $('g-saida').textContent; $('e-senha').type = 'text'; medidor($('e-forca'), $('e-forca-txt'), $('e-senha').value); fecharSheet('ov-gen'); $('e-olho').replaceChildren(Icone.el('olho-off')); });
    $('g-fechar').addEventListener('click', () => fecharSheet('ov-gen'));
    $('e-gerar').addEventListener('click', () => abrirGerador(true));

    /* ── menu ── */
    $('b-menu').addEventListener('click', () => abrirSheet('ov-menu'));
    $('m-fechar').addEventListener('click', () => fecharSheet('ov-menu'));
    $('m-gerador').addEventListener('click', () => { fecharSheet('ov-menu'); abrirGerador(false); });
    $('m-bloquear').addEventListener('click', () => bloquear());
    $('b-bloquear').addEventListener('click', () => bloquear());

    function bloquear(motivo) {
      CC.bloquear(sessao); sessao = null; itens = []; window.__itensCifrados = window.__itensCifrados || [];
      clearInterval(timerTotp); fecharTodos();
      $('lista').textContent = ''; $('det-corpo').textContent = ''; $('det-titulo').textContent = ''; $('busca').value = '';
      ['e-titulo', 'e-url', 'e-user', 'e-senha', 'e-totp', 'e-notas'].forEach(id => { $(id).value = ''; });
      iniciar();      // recarrega o texto cifrado atualizado
      if (motivo) aviso(motivo);
    }

    /* ── importar ── */
    let paraImportar = [];
    $('m-importar').addEventListener('click', () => { fecharSheet('ov-menu'); $('i-arquivo').value = ''; $('i-resumo').textContent = ''; $('i-msg').textContent = ''; $('i-ok').disabled = true; paraImportar = []; abrirSheet('ov-imp'); });
    $('i-cancelar').addEventListener('click', () => { paraImportar = []; fecharSheet('ov-imp'); });
    const chaveDup = (i) => [i.titulo, i.usuario, i.url].map(s => String(s || '').trim().toLowerCase()).join('|');
    $('i-arquivo').addEventListener('change', async () => {
      const f = $('i-arquivo').files[0]; if (!f) return;
      try {
        const r = CC.itensDeCsv(await f.text());
        const existentes = new Set(itens.map(chaveDup));
        paraImportar = r.itens.filter(i => !existentes.has(chaveDup(i)));
        const dup = r.itens.length - paraImportar.length;
        $('i-resumo').textContent = r.itens.length + ' item(ns) no arquivo' + (dup ? ', ' + dup + ' já existe(m) no cofre e será(ão) ignorado(s)' : '') + '. Colunas reconhecidas: ' + (r.colunas.join(', ') || 'nenhuma') + '.';
        $('i-ok').disabled = paraImportar.length === 0; $('i-msg').textContent = r.itens.length ? '' : 'Não encontrei senhas nesse arquivo (confira se é o CSV exportado).';
      } catch (e) { $('i-msg').textContent = 'Não consegui ler o arquivo.'; }
    });
    $('i-ok').addEventListener('click', async () => {
      await ocupado($('i-ok'), 'Cifrando e importando…', async () => {
        try {
          const novos = []; const lote = [];
          for (const it of paraImportar) { const id = crypto.randomUUID(); const b = await CC.cifrarItem(sessao, id, it); lote.push({ id, iv: b.iv, ct: b.ct }); novos.push(Object.assign({ id }, it)); }
          for (let k = 0; k < lote.length; k += 200) await api({ acao: 'importar', itens: lote.slice(k, k + 200) });
          itens = itens.concat(novos); desenharLista(); fecharSheet('ov-imp'); aviso(novos.length + ' senha(s) importada(s). Apague o arquivo CSV do aparelho.'); paraImportar = [];
        } catch (e) { $('i-msg').textContent = e.message; }
      });
    });

    /* ── exportar ── */
    $('m-exportar').addEventListener('click', async () => {
      fecharSheet('ov-menu');
      if (!(await BFApp.modalConfirm('O arquivo CSV guarda TODAS as senhas em texto aberto, sem proteção. Use só para levar ao app Senhas da Apple ou a um backup seu, e apague depois. Exportar?', 'Exportar senhas'))) return;
      const csv = CC.csvDeItens(itens.map(i => ({ titulo: i.titulo, url: i.url, usuario: i.usuario, senha: i.senha, notas: i.notas, totp: i.totp })));
      const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' })); a.download = 'barao-senhas.csv';
      document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 2000);
      aviso('Arquivo gerado. Apague-o depois de usar.');
    });

    /* ── trocar senha mestra / nova chave de recuperação ── */
    $('m-troca').addEventListener('click', () => { fecharSheet('ov-menu'); ['t-atual', 't-nova', 't-nova2'].forEach(id => { $(id).value = ''; }); $('t-msg').textContent = ''; abrirSheet('ov-troca'); setTimeout(() => $('t-atual').focus(), 60); });
    $('t-cancelar').addEventListener('click', () => fecharSheet('ov-troca'));
    $('f-troca').addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const msg = $('t-msg'), nova = $('t-nova').value;
      if (nova.length < MIN_SENHA) { msg.textContent = 'A nova senha mestra precisa ter pelo menos ' + MIN_SENHA + ' caracteres.'; return; }
      if (nova !== $('t-nova2').value) { msg.textContent = 'As duas senhas novas não são iguais.'; return; }
      await ocupado($('t-ok'), 'Trocando…', async () => {
        try {
          await CC.abrirCofre(config, $('t-atual').value);          // confirma que é você
          const troca = await CC.novaSenhaMestra(sessao, nova);
          await api({ acao: 'trocar_mestra', config: troca });
          config = Object.assign({}, config, troca); fecharSheet('ov-troca'); aviso('Senha mestra trocada.');
        } catch (e) { msg.textContent = e.message === 'senha_incorreta' ? 'A senha mestra atual está incorreta.' : e.message; }
      });
    });
    $('m-recup').addEventListener('click', async () => {
      fecharSheet('ov-menu');
      if (!(await BFApp.modalConfirm('Gerar uma nova chave de recuperação? A antiga deixa de funcionar.', 'Nova chave'))) return;
      try {
        const r = await CC.novaRecuperacao(sessao);
        await api({ acao: 'trocar_recuperacao', config: r.parcial });
        config = Object.assign({}, config, r.parcial);
        mostrarChave(r.chaveRecuperacao, 'aberto');
      } catch (e) { aviso(e.message, true); }
    });

    /* ── apagar o cofre ── */
    function pedirApagar() { fecharSheet('ov-menu'); $('p-conf').value = ''; $('p-msg').textContent = ''; abrirSheet('ov-apagar'); setTimeout(() => $('p-conf').focus(), 60); }
    $('m-apagar').addEventListener('click', pedirApagar);
    $('r-apagar').addEventListener('click', pedirApagar);
    $('p-cancelar').addEventListener('click', () => fecharSheet('ov-apagar'));
    $('p-ok').addEventListener('click', async () => {
      if ($('p-conf').value.trim().toUpperCase() !== 'APAGAR COFRE') { $('p-msg').textContent = 'Digite exatamente APAGAR COFRE.'; return; }
      try {
        await api({ acao: 'apagar_tudo', confirmacao: 'APAGAR COFRE' });
        CC.bloquear(sessao); sessao = null; itens = []; config = null; window.__itensCifrados = [];
        fecharTodos(); mostrar('v-criar'); aviso('Cofre apagado.');
      } catch (e) { $('p-msg').textContent = e.message; }
    });

    /* ── fechar folhas tocando fora ── */
    document.querySelectorAll('.overlay').forEach(o => o.addEventListener('click', (e) => {
      if (e.target !== o) return;
      if (o.id === 'ov-det') fecharDetalhe(); else o.classList.remove('open');
    }));

    /* ── bloqueio automático ── */
    ['click', 'keydown', 'touchstart', 'input'].forEach(ev => document.addEventListener(ev, () => { ultimoUso = Date.now(); }, { passive: true }));
    function verificarOcioso() { if (sessao && Date.now() - ultimoUso > OCIOSO_MS) bloquear('Cofre bloqueado por inatividade.'); }
    setInterval(verificarOcioso, 15000);
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) saiuEm = Date.now();
      else if (sessao && saiuEm && Date.now() - saiuEm > SEGUNDO_PLANO_MS) bloquear('Cofre bloqueado: você ficou fora do app.');
    });
    window.addEventListener('pagehide', () => { CC.bloquear(sessao); });

    iniciar();
  </script>
</body>
</html>
