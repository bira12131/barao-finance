<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Códigos</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <link rel="stylesheet" href="../../css/ferramentas.css" />
  <script src="../../js/app-runtime.js"></script>
  <style>
    .busca { position: sticky; top: 0; z-index: 5; background: var(--bg-page); padding-bottom: 0.5rem; }
    .lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 0.7rem; }
    .cartao { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 0.75rem 0.85rem; cursor: pointer; display: flex; flex-direction: column; gap: 0.45rem; min-width: 0; }
    .cartao:active { transform: scale(0.99); }
    .c-top { display: flex; align-items: center; gap: 0.5rem; }
    .c-nome { flex: 1; min-width: 0; font-weight: 700; color: var(--text-heading); font-size: 0.95rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .estrela { width: 40px; height: 40px; flex: none; border: none; background: transparent; font-size: 1.25rem; color: var(--text-muted); cursor: pointer; border-radius: 10px; }
    .estrela.on { color: #f5b942; }
    .c-meta { display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; font-size: 0.72rem; color: var(--text-muted); }
    .c-tag { padding: 0.12rem 0.5rem; border-radius: 99px; border: 1px solid var(--border); }
    .previa { margin: 0; font-size: 12.5px; line-height: 1.4; color: var(--text-body); background: var(--bg-input); border: 1px solid var(--border); border-radius: 10px; padding: 0.45rem 0.6rem; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; white-space: pre-wrap; word-break: break-all; }
    .c-acoes { display: flex; gap: 0.4rem; }
    .editor { min-height: 38vh; }
    .linha-btns { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.7rem; }
    .linha-btns .btn { flex: 1 1 calc(50% - 0.5rem); }
    @media (min-width: 900px) { .linha-btns .btn { flex: 0 1 auto; } .editor { min-height: 50vh; } }
  </style>
</head>
<body class="inner-page tone-ferramentas">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Códigos</h1>
      <p>Guarde códigos, consultas e anotações de texto, cada um com seu nome</p>
    </div>
    <div class="page-header-actions">
      <button class="btn outline" id="b-importar" type="button"><i data-icone="arquivo"></i> Importar arquivos</button>
      <input type="file" id="arquivos" multiple accept=".sql,.vb,.vbs,.cs,.js,.mjs,.ts,.php,.html,.htm,.css,.py,.sh,.json,.xml,.md,.txt,.log,.config,.ini,.yml,.yaml,.csv,text/*" style="display:none" />
    </div>
  </div>

  <div class="busca">
    <input class="input" id="q" type="search" placeholder="Buscar por nome, tag ou dentro do código…" autocomplete="off" autocapitalize="off" spellcheck="false" />
    <div class="chips" id="filtros" style="margin-top:0.55rem"></div>
  </div>

  <div class="lista" id="lista"></div>

  <button class="fab" id="fab" aria-label="Novo código">+</button>
  <div class="toast" id="toast" role="status" aria-live="polite" style="display:none"></div>

  <div class="overlay" id="ov">
    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="ed-titulo">
      <div class="sheet-title" id="ed-titulo">Novo código</div>
      <div class="field"><label class="label" for="e-nome">Nome</label><input class="input" id="e-nome" type="text" maxlength="200" autocomplete="off" placeholder="Ex.: Relatório de clientes ativos" /></div>
      <div class="row2">
        <div class="field"><label class="label" for="e-ling">Tipo</label><select class="input" id="e-ling"></select></div>
        <div class="field"><label class="label" for="e-tags">Tags</label><input class="input" id="e-tags" type="text" autocomplete="off" autocapitalize="off" placeholder="separe por vírgula" /></div>
      </div>
      <div class="field"><label class="label" for="e-cont">Conteúdo</label>
        <textarea class="input mono editor" id="e-cont" spellcheck="false" autocapitalize="off" autocomplete="off" autocorrect="off" wrap="off"></textarea></div>
      <label class="linha" style="min-height:40px; gap:0.6rem; font-size:0.86rem; color:var(--text-body); cursor:pointer"><input type="checkbox" id="e-fav" style="width:22px;height:22px;accent-color:var(--primary)" /> Favorito</label>
      <div class="sheet-msg" id="e-msg"></div>
      <div class="linha-btns">
        <button class="btn" id="e-salvar" type="button"><i data-icone="check"></i> Salvar</button>
        <button class="btn outline" id="e-copiar" type="button"><i data-icone="copiar"></i> Copiar</button>
        <button class="btn outline" id="e-baixar" type="button"><i data-icone="baixar"></i> Baixar arquivo</button>
        <button class="btn outline" id="e-converter" type="button" style="display:none"><i data-icone="trocar"></i> Converter</button>
        <button class="btn danger" id="e-excluir" type="button" style="display:none"><i data-icone="lixeira"></i> Excluir</button>
        <button class="btn outline" id="e-cancelar" type="button">Fechar</button>
      </div>
    </div>
  </div>

  <script>
    const $ = (id) => document.getElementById(id);
    const LING = { sql: ['SQL', 'sql'], vbnet: ['VB.NET', 'vb'], csharp: ['C#', 'cs'], javascript: ['JavaScript', 'js'], php: ['PHP', 'php'], html: ['HTML', 'html'], css: ['CSS', 'css'],
                   python: ['Python', 'py'], bash: ['Bash', 'sh'], json: ['JSON', 'json'], xml: ['XML', 'xml'], markdown: ['Markdown', 'md'], texto: ['Texto', 'txt'] };
    const EXT = { sql: 'sql', vb: 'vbnet', vbs: 'vbnet', cs: 'csharp', js: 'javascript', mjs: 'javascript', ts: 'javascript', php: 'php', html: 'html', htm: 'html', css: 'css', py: 'python',
                  sh: 'bash', json: 'json', xml: 'xml', config: 'xml', md: 'markdown' };

    function apiBase() {
      const { origin, pathname } = window.location;
      return origin + pathname.replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '').replace(/\/[^/]*\.(?:html|php)$/, '') + '/backend/api';
    }
    async function api(corpo, query) {
      const url = new URL(apiBase() + '/snippets.php');
      if (query) Object.entries(query).forEach(([k, v]) => { if (v !== '' && v != null) url.searchParams.set(k, v); });
      const opts = corpo ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(corpo), cache: 'no-store' } : { cache: 'no-store' };
      const res = await fetch(url.toString(), opts);
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'Erro ao falar com o servidor.');
      return json.data;
    }

    let toastTimer = null;
    function aviso(texto, erro) {
      const t = $('toast'); t.textContent = texto; t.className = 'toast ' + (erro ? 'erro' : 'ok'); t.style.display = '';
      clearTimeout(toastTimer); toastTimer = setTimeout(() => { t.style.display = 'none'; }, erro ? 6000 : 2500);
    }
    async function copiar(texto) {
      try { await navigator.clipboard.writeText(texto); }
      catch (_) { const t = document.createElement('textarea'); t.value = texto; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); }
    }
    function el(tag, cls, texto) { const e = document.createElement(tag); if (cls) e.className = cls; if (texto !== undefined) e.textContent = texto; return e; }

    Object.entries(LING).forEach(([k, v]) => { const o = el('option', null, v[0]); o.value = k; $('e-ling').appendChild(o); });

    /* ── lista ── */
    let itens = [], tags = [], filtroLing = '', filtroTag = '', soFavoritos = false;

    async function carregar() {
      try { const d = await api(null, { q: $('q').value.trim() }); itens = d.itens || []; tags = d.tags || []; }
      catch (e) { aviso(e.message, true); }
      desenhar();
    }

    function filtrados() {
      return itens.filter(i => (!filtroLing || i.linguagem === filtroLing) && (!soFavoritos || i.favorito) && (!filtroTag || (',' + i.tags + ',').includes(',' + filtroTag + ',')));
    }

    function chip(rotulo, ativo, aoClicar, icone) { const c = el('button', 'chip' + (ativo ? ' on' : '')); if (icone) c.appendChild(Icone.el(icone)); c.appendChild(document.createTextNode(rotulo)); c.type = 'button'; c.addEventListener('click', aoClicar); return c; }

    function desenhar() {
      const f = $('filtros'); f.textContent = '';
      f.appendChild(chip('Todos', !filtroLing && !filtroTag && !soFavoritos, () => { filtroLing = ''; filtroTag = ''; soFavoritos = false; desenhar(); }));
      f.appendChild(chip('Favoritos', soFavoritos, () => { soFavoritos = !soFavoritos; desenhar(); }, 'estrela'));
      [...new Set(itens.map(i => i.linguagem))].forEach(l => f.appendChild(chip(LING[l] ? LING[l][0] : l, filtroLing === l, () => { filtroLing = filtroLing === l ? '' : l; desenhar(); })));
      tags.slice(0, 12).forEach(t => f.appendChild(chip('#' + t.tag, filtroTag === t.tag, () => { filtroTag = filtroTag === t.tag ? '' : t.tag; desenhar(); })));

      const box = $('lista'); box.textContent = '';
      const lista = filtrados();
      if (!lista.length) {
        const e = el('div', 'empty', itens.length ? 'Nada com esse filtro.' : ($('q').value.trim() ? 'Nada encontrado.' : 'Nenhum código ainda. Toque em + para guardar o primeiro, ou em "Importar arquivos" para trazer arquivos .sql, .vb, .txt…'));
        e.style.gridColumn = '1 / -1'; box.appendChild(e); return;
      }
      lista.forEach(i => box.appendChild(cartao(i)));
    }

    function cartao(i) {
      const c = el('div', 'cartao'); c.tabIndex = 0; c.setAttribute('role', 'button');
      const top = el('div', 'c-top');
      top.appendChild(el('div', 'c-nome', i.nome));
      const est = el('button', 'estrela' + (i.favorito ? ' on' : '')); est.appendChild(Icone.el('estrela')); est.type = 'button'; est.setAttribute('aria-label', i.favorito ? 'Tirar dos favoritos' : 'Favoritar');
      est.addEventListener('click', async (ev) => { ev.stopPropagation(); try { await api({ acao: 'favoritar', id: i.id, favorito: !i.favorito }); await carregar(); } catch (e) { aviso(e.message, true); } });
      top.appendChild(est);
      c.appendChild(top);

      const meta = el('div', 'c-meta');
      meta.appendChild(el('span', 'badge', LING[i.linguagem] ? LING[i.linguagem][0] : i.linguagem));
      (i.tags ? i.tags.split(',') : []).forEach(t => meta.appendChild(el('span', 'c-tag', '#' + t)));
      meta.appendChild(el('span', null, tamanho(i.tamanho) + ' · ' + quando(i.atualizado_em)));
      c.appendChild(meta);

      if (i.previa) c.appendChild(el('pre', 'previa mono', i.previa));
      const ac = el('div', 'c-acoes');
      const cp = el('button', 'btn outline small'); cp.appendChild(Icone.el('copiar')); cp.appendChild(document.createTextNode('Copiar')); cp.type = 'button';
      cp.addEventListener('click', async (ev) => { ev.stopPropagation(); try { const d = await api(null, { id: i.id }); await copiar(d.item.conteudo); aviso('Copiado: ' + i.nome); } catch (e) { aviso(e.message, true); } });
      ac.appendChild(cp); c.appendChild(ac);

      const abrir = () => abrirEditor(i.id);
      c.addEventListener('click', abrir);
      c.addEventListener('keydown', (e) => { if (e.key === 'Enter') abrir(); });
      return c;
    }

    function tamanho(n) { return n < 1024 ? n + ' B' : (n / 1024).toFixed(n < 10240 ? 1 : 0) + ' KB'; }
    function quando(iso) {
      const d = new Date(String(iso).replace(' ', 'T').replace(/([+-]\d\d)$/, '$1:00'));
      return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit' });
    }

    /* ── editor ── */
    let editando = null;     // item completo em edição (ou null = novo)

    async function abrirEditor(id) {
      editando = null;
      if (id) {
        try { editando = (await api(null, { id })).item; } catch (e) { aviso(e.message, true); return; }
      }
      $('ed-titulo').textContent = editando ? 'Editar código' : 'Novo código';
      $('e-nome').value = editando ? editando.nome : '';
      $('e-ling').value = editando ? editando.linguagem : (filtroLing || 'sql');
      $('e-tags').value = editando ? editando.tags : '';
      $('e-cont').value = editando ? editando.conteudo : '';
      $('e-fav').checked = editando ? editando.favorito : false;
      $('e-excluir').style.display = editando ? '' : 'none';
      $('e-msg').textContent = '';
      atualizarConverter();
      $('ov').classList.add('open');
      setTimeout(() => $(editando ? 'e-cont' : 'e-nome').focus(), 60);
    }
    const fechar = () => { $('ov').classList.remove('open'); editando = null; };
    function atualizarConverter() { $('e-converter').style.display = ['sql', 'vbnet'].includes($('e-ling').value) ? '' : 'none'; }

    async function salvar() {
      const corpo = { acao: editando ? 'editar' : 'criar', id: editando ? editando.id : undefined, nome: $('e-nome').value.trim(), linguagem: $('e-ling').value,
                      tags: $('e-tags').value, conteudo: $('e-cont').value, favorito: $('e-fav').checked };
      if (!corpo.nome) { $('e-msg').textContent = 'Dê um nome ao código.'; $('e-nome').focus(); return; }
      $('e-salvar').disabled = true;
      try { await api(corpo); fechar(); aviso('Salvo.'); await carregar(); }
      catch (e) { $('e-msg').textContent = e.message; }
      finally { $('e-salvar').disabled = false; }
    }

    function nomeDeArquivo() {
      const base = ($('e-nome').value.trim() || 'codigo').replace(/[\\/:*?"<>|\u0000-\u001f]+/g, '_').slice(0, 120);
      return base + '.' + (LING[$('e-ling').value] || LING.texto)[1];
    }
    function baixar() {
      const blob = new Blob([$('e-cont').value], { type: 'text/plain;charset=utf-8' });
      const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = nomeDeArquivo(); document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(a.href), 2000);
    }

    /** Leva o texto para o conversor (SQL → VB para SQL; VB → SQL para código VB). */
    function converter() {
      try { localStorage.setItem('bf_consulta_prefill', JSON.stringify({ modo: $('e-ling').value === 'sql' ? 'sql' : 'vb', texto: $('e-cont').value })); } catch (_) { /* sem armazenamento */ }
      const pagina = 'pages/consulta.php';
      try {
        if (window.parent && window.parent !== window) {
          const frame = window.parent.document.getElementById('page-frame');
          if (frame) {
            frame.src = pagina; window.parent.localStorage.setItem('bf_page', pagina);
            window.parent.document.querySelectorAll('[data-page]').forEach(i => i.classList.toggle('active', i.dataset.page === pagina));
            return;
          }
        }
      } catch (_) { /* cai no link direto */ }
      window.location.href = 'consulta.php';
    }

    $('fab').addEventListener('click', () => abrirEditor(null));
    $('e-salvar').addEventListener('click', salvar);
    $('e-cancelar').addEventListener('click', fechar);
    $('e-copiar').addEventListener('click', async () => { await copiar($('e-cont').value); aviso('Copiado.'); });
    $('e-baixar').addEventListener('click', baixar);
    $('e-converter').addEventListener('click', converter);
    $('e-ling').addEventListener('change', atualizarConverter);
    $('e-excluir').addEventListener('click', async () => {
      if (!editando || !(await BFApp.modalConfirm(`Excluir "${editando.nome}"?`, 'Excluir'))) return;
      try { await api({ acao: 'excluir', id: editando.id }); fechar(); aviso('Excluído.'); await carregar(); } catch (e) { $('e-msg').textContent = e.message; }
    });
    $('ov').addEventListener('click', (e) => { if (e.target === $('ov')) fechar(); });

    let espera = null;
    $('q').addEventListener('input', () => { clearTimeout(espera); espera = setTimeout(carregar, 250); });

    /* ── importar arquivos de texto ── */
    $('b-importar').addEventListener('click', () => $('arquivos').click());
    $('arquivos').addEventListener('change', async () => {
      const arquivos = [...$('arquivos').files];
      $('arquivos').value = '';
      if (!arquivos.length) return;
      const lote = [], recusados = [];
      for (const a of arquivos) {
        if (a.size > 400000) { recusados.push(a.name + ' (maior que 400 KB)'); continue; }
        const texto = await a.text();
        if (texto.includes('\u0000')) { recusados.push(a.name + ' (não é texto)'); continue; }
        const m = a.name.match(/^(.*?)(?:\.([A-Za-z0-9]+))?$/);
        lote.push({ nome: m[1] || a.name, linguagem: EXT[(m[2] || '').toLowerCase()] || 'texto', tags: 'importado', conteudo: texto });
      }
      try {
        let criados = 0;
        for (let i = 0; i < lote.length; i += 50) criados += (await api({ acao: 'importar', itens: lote.slice(i, i + 50) })).importados;
        aviso(criados + ' arquivo(s) importado(s)' + (recusados.length ? '. Ignorados: ' + recusados.join('; ') : '.'), recusados.length > 0);
        await carregar();
      } catch (e) { aviso(e.message, true); }
    });

    carregar();
  </script>
</body>
</html>
