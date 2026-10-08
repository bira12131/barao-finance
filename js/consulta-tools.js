/**
 * Ferramentas de consulta SQL <-> código VB.NET.
 *
 *  vbParaSql(codigo)  : extrai a consulta limpa de um trecho VB.NET (strsql = "" & vbcrlf & "select ..." ...)
 *  sqlParaVb(sql)     : monta o trecho VB.NET a partir de uma consulta limpa
 *  aplicarValores     : troca os {placeholders} por valores
 *  umaLinha           : junta a consulta em uma linha só
 *
 * Roda no navegador (window.ConsultaTools) e no Node (module.exports), por isso é testável.
 */
(function (raiz) {
  'use strict';

  /* ════════════════════════════════════════════
     Leitura do VB.NET
  ════════════════════════════════════════════ */

  const QUEBRAS = new Set(['vbcrlf', 'vbnewline', 'vblf', 'vbcr', 'environment.newline', 'controlchars.crlf',
    'controlchars.newline', 'controlchars.lf', 'controlchars.cr']);
  const TABS = new Set(['vbtab', 'controlchars.tab']);
  const PARECE_SQL = /\b(select|insert|update|delete|with|exec|execute|create|alter|drop|declare|merge|truncate|from|where)\b/i;

  /** Remove o comentário de uma linha (' ou REM), respeitando aspas. */
  function tirarComentario(linha) {
    let dentro = false;
    for (let i = 0; i < linha.length; i++) {
      const c = linha[i];
      if (c === '"') dentro = !dentro;                 // "" dentro de string alterna duas vezes: continua correto
      else if (c === "'" && !dentro) return linha.slice(0, i);
    }
    if (/^\s*rem(\s|$)/i.test(linha)) return '';
    return linha;
  }

  function parentesesAbertos(texto) {
    let dentro = false, n = 0;
    for (const c of texto) {
      if (c === '"') dentro = !dentro;
      else if (!dentro) { if (c === '(') n++; else if (c === ')') n--; }
    }
    return n > 0;
  }

  /** Parênteses de fechamento nunca passam dos de abertura e terminam zerados (ignora o que está em aspas). */
  function balanceado(texto) {
    let dentro = false, n = 0;
    for (const c of texto) {
      if (c === '"') dentro = !dentro;
      else if (!dentro) { if (c === '(') n++; else if (c === ')') { n--; if (n < 0) return false; } }
    }
    return n === 0;
  }

  function terminaEmOperador(texto) {
    return /[&+,(=]\s*$/.test(texto);                  // continuação implícita (VB 2010+)
  }

  /** Quebra o código em instruções, juntando as linhas continuadas (`_`, `&` no fim, parênteses abertos). */
  function instrucoes(codigo) {
    const out = [];
    let atual = '';
    for (const bruta of codigo.replace(/\r\n?/g, '\n').split('\n')) {
      let l = tirarComentario(bruta).replace(/\s+$/, '');
      if (l.trim() === '' && atual === '') continue;
      let continua = false;
      if (/(^|\s)_$/.test(l)) { l = l.replace(/\s?_$/, ''); continua = true; }
      atual += (atual ? ' ' : '') + l.trim();
      if (continua || terminaEmOperador(atual) || parentesesAbertos(atual)) continue;
      out.push(atual);
      atual = '';
    }
    if (atual) out.push(atual);
    return out;
  }

  /** Lê uma string VB a partir de src[i] === '"'. @return {valor, fim} (fim = índice depois da aspa final) */
  function lerString(src, i) {
    let j = i + 1, valor = '';
    while (j < src.length) {
      if (src[j] === '"') {
        if (src[j + 1] === '"') { valor += '"'; j += 2; continue; }
        break;
      }
      valor += src[j++];
    }
    return { valor, fim: j + 1 };
  }

  /** $"texto {expr} texto" -> partes. */
  function lerInterpolada(src, i) {
    const partes = [];
    let j = i + 2, texto = '';
    while (j < src.length) {
      const c = src[j];
      if (c === '"') {
        if (src[j + 1] === '"') { texto += '"'; j += 2; continue; }
        break;
      }
      if (c === '{') {
        if (src[j + 1] === '{') { texto += '{'; j += 2; continue; }
        let k = j + 1, prof = 1;
        while (k < src.length && prof > 0) { if (src[k] === '{') prof++; else if (src[k] === '}') prof--; k++; }
        if (texto) { partes.push({ tipo: 'texto', valor: texto }); texto = ''; }
        const expr = src.slice(j + 1, k - 1).split(/[:,]/)[0].trim();
        partes.push({ tipo: 'expr', valor: expr });
        j = k;
        continue;
      }
      if (c === '}' && src[j + 1] === '}') { texto += '}'; j += 2; continue; }
      texto += c;
      j++;
    }
    if (texto) partes.push({ tipo: 'texto', valor: texto });
    return { partes, fim: j + 1 };
  }

  /** Tokens de uma instrução. Cada token guarda onde começa/termina no texto original. */
  function tokenizar(src) {
    const t = [];
    let i = 0;
    const n = src.length;
    while (i < n) {
      const c = src[i];
      if (c === ' ' || c === '\t') { i++; continue; }
      if (c === '"') {
        const r = lerString(src, i);
        t.push({ tipo: 'str', valor: r.valor, ini: i, fim: r.fim });
        i = r.fim;
        continue;
      }
      if (c === '$' && src[i + 1] === '"') {
        const r = lerInterpolada(src, i);
        t.push({ tipo: 'interp', partes: r.partes, ini: i, fim: r.fim });
        i = r.fim;
        continue;
      }
      if ((c === '&' || c === '+') && src[i + 1] === '=') { t.push({ tipo: 'op', valor: c + '=', ini: i, fim: i + 2 }); i += 2; continue; }
      if ('&+=(),'.includes(c)) { t.push({ tipo: 'op', valor: c, ini: i, fim: i + 1 }); i++; continue; }
      let j = i;
      while (j < n && !' \t&+=(),"'.includes(src[j])) j++;
      if (j === i) j = i + 1;
      t.push({ tipo: 'id', valor: src.slice(i, j), ini: i, fim: j });
      i = j;
    }
    return t;
  }

  function tirarParentesesExternos(e) {
    for (;;) {
      e = e.trim();
      if (e[0] !== '(' || e[e.length - 1] !== ')') return e;
      let dentro = false, prof = 0, fecha = -1;
      for (let i = 0; i < e.length; i++) {
        const c = e[i];
        if (c === '"') dentro = !dentro;
        else if (!dentro) { if (c === '(') prof++; else if (c === ')') { prof--; if (prof === 0) { fecha = i; break; } } }
      }
      if (fecha !== e.length - 1) return e;
      e = e.slice(1, -1);
    }
  }

  /** Deixa o nome do placeholder legível: tira .ToString(), CStr(x), CInt(x), parênteses sobrando... */
  function simplificar(expr) {
    let e = tirarParentesesExternos(expr);
    for (let k = 0; k < 6; k++) {
      const antes = e;
      e = e.replace(/\.ToString\(\)\s*$/i, '').replace(/\.Trim\(\)\s*$/i, '');
      let m = e.match(/^(?:CStr|CInt|CLng|CDbl|CDec|CSng|CBool|Str|Val)\s*\(([\s\S]*)\)$/i) || e.match(/^Convert\.To\w+\(([\s\S]*)\)$/i);
      if (m && balanceado(m[1])) e = m[1];   // CInt(a) + CInt(b) não pode virar a) + CInt(b
      e = tirarParentesesExternos(e);
      if (e === antes) break;
    }
    return e.trim() || 'valor';
  }

  /** Um operando (lista de tokens) -> partes {tipo:'texto'|'expr'}. */
  function classificar(tokens, src) {
    if (!tokens.length) return [];
    if (tokens.length === 1) {
      const k = tokens[0];
      if (k.tipo === 'str') return k.valor === '' ? [] : [{ tipo: 'texto', valor: k.valor }];
      if (k.tipo === 'interp') return k.partes.map(p => (p.tipo === 'texto' ? p : { tipo: 'expr', valor: simplificar(p.valor) }));
      if (k.tipo === 'id') {
        const nome = k.valor.toLowerCase();
        if (QUEBRAS.has(nome)) return [{ tipo: 'texto', valor: '\n' }];
        if (TABS.has(nome)) return [{ tipo: 'texto', valor: '\t' }];
        if (nome === 'string.empty' || nome === 'vbnullstring') return [];
      }
    }
    const raw = src.slice(tokens[0].ini, tokens[tokens.length - 1].fim);
    let m = raw.match(/^Chr[W]?\s*\(\s*(\d+)\s*\)$/i);
    if (m) {
      const cod = +m[1];
      if (cod === 13) return [];                                   // CR de um Chr(13) & Chr(10)
      return [{ tipo: 'texto', valor: cod === 10 ? '\n' : String.fromCharCode(cod) }];
    }
    m = raw.match(/^Space\s*\(\s*(\d+)\s*\)$/i);
    if (m) return [{ tipo: 'texto', valor: ' '.repeat(+m[1]) }];
    m = raw.match(/^New\s+String\s*\(\s*"(.)"\s*,\s*(\d+)\s*\)$/i);
    if (m) return [{ tipo: 'texto', valor: m[1].repeat(+m[2]) }];
    return [{ tipo: 'expr', valor: simplificar(raw) }];
  }

  /** Expressão de concatenação (tokens) -> partes. `&` sempre concatena; `+` só quando encosta numa string. */
  function partesDaExpressao(tokens, src) {
    const operandos = [[]];
    const ops = [];
    let prof = 0;
    for (const k of tokens) {
      if (k.tipo === 'op') {
        if (k.valor === '(') prof++;
        else if (k.valor === ')') prof--;
        else if (prof === 0 && (k.valor === '&' || k.valor === '+')) { ops.push(k.valor); operandos.push([]); continue; }
      }
      operandos[operandos.length - 1].push(k);
    }
    const ehTexto = (op) => op.length === 1 && (op[0].tipo === 'str' || op[0].tipo === 'interp');

    // Junta com "+" os operandos que não encostam em string (soma numérica, não concatenação).
    const grupos = [operandos[0]];
    for (let i = 0; i < ops.length; i++) {
      const prox = operandos[i + 1];
      if (ops[i] === '+' && !ehTexto(grupos[grupos.length - 1]) && !ehTexto(prox)) {
        grupos[grupos.length - 1] = grupos[grupos.length - 1].concat([{ tipo: 'op', valor: '+', ini: 0, fim: 0 }], prox);
        // texto bruto contínuo: refaz os limites usando o primeiro/último token reais
      } else {
        grupos.push(prox);
      }
    }
    const partes = [];
    for (const g of grupos) {
      const reais = g.filter(k => k.fim > 0);
      for (const p of classificar(reais, src)) partes.push(p);
    }
    return partes;
  }

  /** Entende `x = ...`, `x &= ...`, `Dim x As String = ...`, `If c Then x &= ...`. */
  function lerInstrucao(src) {
    let tokens = tokenizar(src);
    if (!tokens.length) return null;

    // If cond Then <instrução> (uma linha só)
    if (tokens[0].tipo === 'id' && /^if$/i.test(tokens[0].valor)) {
      const t = tokens.findIndex(k => k.tipo === 'id' && /^then$/i.test(k.valor));
      if (t < 0 || t === tokens.length - 1) return { condicional: true };
      tokens = tokens.slice(t + 1);
      const e = tokens.findIndex(k => k.tipo === 'id' && /^else$/i.test(k.valor));
      if (e >= 0) tokens = tokens.slice(0, e);
      if (!tokens.length) return { condicional: true };
    }

    let prof = 0, iOp = -1;
    for (let i = 0; i < tokens.length; i++) {
      const k = tokens[i];
      if (k.tipo !== 'op') continue;
      if (k.valor === '(') prof++;
      else if (k.valor === ')') prof--;
      else if (prof === 0 && (k.valor === '=' || k.valor === '&=' || k.valor === '+=')) { iOp = i; break; }
    }
    if (iOp <= 0) return null;

    // lado esquerdo: [Dim|Public|Private|Static|Const]* nome [As Tipo]
    const esq = tokens.slice(0, iOp).filter(k => k.tipo === 'id').map(k => k.valor);
    while (esq.length && /^(dim|public|private|static|const|shared|friend|protected)$/i.test(esq[0])) esq.shift();
    const iAs = esq.findIndex(v => /^as$/i.test(v));
    const nome = (iAs >= 0 ? esq.slice(0, iAs) : esq).join('');
    if (!/^[A-Za-z_][\w.]*$/.test(nome)) return null;

    const rhs = tokens.slice(iOp + 1);
    if (!rhs.length) return null;
    return { variavel: nome, op: tokens[iOp].valor, partes: partesDaExpressao(rhs, src) };
  }

  /* ════════════════════════════════════════════
     Texto final
  ════════════════════════════════════════════ */

  function montarTexto(partes) {
    return partes.map(p => (p.tipo === 'texto' ? p.valor : '{' + p.valor + '}')).join('');
  }

  function limparSql(texto) {
    const linhas = texto.replace(/\r\n?/g, '\n').split('\n').map(l => l.replace(/[ \t]+$/, ''));
    const out = [];
    for (const l of linhas) {
      if (l === '' && (out.length === 0 || out[out.length - 1] === '')) continue;   // sem brancos no começo nem em sequência
      out.push(l);
    }
    while (out.length && out[out.length - 1] === '') out.pop();
    return out.join('\n');
  }

  function variaveisDe(sql) {
    const vistos = new Set();
    const re = /\{([^{}\n]+)\}/g;
    let m;
    while ((m = re.exec(sql))) vistos.add(m[1]);
    return [...vistos];
  }

  /**
   * Extrai as consultas de um trecho VB.NET.
   * @return {{consultas: Array<{variavel:string, sql:string, variaveis:string[]}>, avisos:string[]}}
   */
  function vbParaSql(codigo) {
    const avisos = [];
    const lista = instrucoes(codigo || '');
    const mapa = new Map();      // variável (minúscula) -> {nome, partes}
    let condicionais = false;
    let semAtribuicao = true;

    for (const instr of lista) {
      if (/^\s*(if|elseif|else|end\s+if|select\s+case|case|for|next|while|end\s+while|do|loop|try|catch|finally|end\s+try|with|end\s+with)\b/i.test(instr)
          && !/^\s*if\b.*\bthen\b\s*\S/i.test(instr)) {
        if (/^\s*(if|elseif|else|select\s+case|case)\b/i.test(instr)) condicionais = true;
        continue;
      }
      const r = lerInstrucao(instr);
      if (!r) continue;
      if (r.condicional) { condicionais = true; continue; }
      semAtribuicao = false;

      const chave = r.variavel.toLowerCase();
      const atual = mapa.get(chave) || { nome: r.variavel, partes: [] };
      let partes = r.partes;
      let acumula = r.op === '&=' || r.op === '+=';
      if (!acumula && partes.length && partes[0].tipo === 'expr' && partes[0].valor.toLowerCase() === chave) {
        acumula = true;                                  // x = x & "..."
        partes = partes.slice(1);
      }
      atual.partes = acumula ? atual.partes.concat(partes) : partes;
      mapa.set(chave, atual);
      if (/\bif\b/i.test(instr) && /^\s*if\b/i.test(instr)) condicionais = true;
    }

    // Sem atribuição nenhuma: o usuário colou só a expressão ("" & vbcrlf & "select ...")
    if (semAtribuicao) {
      const tudo = lista.join(' ');
      const tokens = tokenizar(tudo);
      if (tokens.some(k => k.tipo === 'str' || k.tipo === 'interp')) {
        mapa.set('consulta', { nome: 'consulta', partes: partesDaExpressao(tokens, tudo) });
      }
    }

    // Só vale se há texto de verdade em alguma string (uma conta como y = x + 1 só tem placeholders).
    let candidatas = [...mapa.values()]
      .filter(v => v.partes.some(p => p.tipo === 'texto' && /\S/.test(p.valor)))
      .map(v => ({ variavel: v.nome, sql: limparSql(montarTexto(v.partes)) }));
    const sqls = candidatas.filter(c => c.sql.trim() !== '' && PARECE_SQL.test(c.sql));
    if (sqls.length) candidatas = sqls;
    else candidatas = candidatas.filter(c => c.sql.trim() !== '' && /[A-Za-z]/.test(c.sql.replace(/\{[^{}\n]*\}/g, '')));

    if (!candidatas.length) avisos.push('Não encontrei texto de consulta. Cole o trecho com a atribuição (ex.: strsql = "" & vbcrlf & "select ...").');
    if (condicionais && candidatas.length) avisos.push('Há trechos condicionais (If/Select Case): juntei todos os pedaços, como se todas as condições fossem verdadeiras.');
    if (candidatas.length > 1) avisos.push('Encontrei ' + candidatas.length + ' consultas no trecho; separei por variável.');

    return {
      consultas: candidatas.map(c => ({ variavel: c.variavel, sql: c.sql, variaveis: variaveisDe(c.sql) })),
      avisos,
    };
  }

  /** Troca {nome} pelos valores informados (os que ficarem em branco continuam como {nome}). */
  function aplicarValores(sql, valores) {
    return sql.replace(/\{([^{}\n]+)\}/g, (m, nome) => (valores && Object.prototype.hasOwnProperty.call(valores, nome) && valores[nome] !== '' ? valores[nome] : m));
  }

  /** Junta em uma linha. Comentários de linha (--) viram comentários de bloco para não engolirem o resto. */
  function umaLinha(sql) {
    const linhas = sql.replace(/\r\n?/g, '\n').split('\n').map(l => {
      const i = achaComentarioLinha(l);
      return i < 0 ? l : l.slice(0, i) + '/* ' + l.slice(i + 2).trim() + ' */';
    });
    return linhas.map(l => l.trim()).filter(Boolean).join(' ').replace(/\s{2,}/g, ' ').trim();
  }

  function achaComentarioLinha(l) {
    let aspa = false;
    for (let i = 0; i < l.length - 1; i++) {
      if (l[i] === "'") aspa = !aspa;
      else if (!aspa && l[i] === '-' && l[i + 1] === '-') return i;
    }
    return -1;
  }

  /* ════════════════════════════════════════════
     SQL -> VB.NET
  ════════════════════════════════════════════ */

  const PADRAO_VB = {
    variavel: 'strsql',
    quebra: 'vbcrlf',                 // vbcrlf, vbNewLine, Environment.NewLine ou vazio (sem quebra)
    estilo: 'continuacao',            // continuacao: x = "linha" & vbcrlf & / "linha" & vbcrlf &   |   acumular: x = "linha" & vbcrlf, depois x &= ...
    converterVariaveis: true,         // {nome} vira " & nome & "
    quebraNoFim: false,
    incluirExecucao: false,
    linhaExecucao: 'dtbconsulta = conexao.retornaDT({sql}, conexaosql.enretorno.azul)',
  };

  /** Uma linha de SQL -> literal VB: aspas dobradas e {nome} virando concatenação. */
  function literalVb(linha, converterVariaveis) {
    const esc = (s) => s.replace(/"/g, '""');
    if (!converterVariaveis) return '"' + esc(linha) + '"';
    const re = /\{([A-Za-z_][\w.()\s,"']*)\}/g;
    let out = '', ultimo = 0, m;
    while ((m = re.exec(linha))) {
      const antes = linha.slice(ultimo, m.index);
      out += (out === '' ? '"' : '') + esc(antes) + '" & ' + m[1].trim() + ' & "';
      if (out.startsWith('" & ')) out = '"' + out;       // linha que começa com variável
      ultimo = m.index + m[0].length;
    }
    if (out === '') return '"' + esc(linha) + '"';
    out += esc(linha.slice(ultimo)) + '"';
    return out.replace(/ & ""$/, '');                    // não deixa " & "" " sobrando
  }

  function sqlParaVb(sql, opcoes) {
    const o = Object.assign({}, PADRAO_VB, opcoes || {});
    const linhas = limparSql(String(sql || '')).split('\n');
    if (linhas.length === 1 && linhas[0] === '') linhas[0] = '';
    const q = o.quebra && o.quebra.trim() ? o.quebra.trim() : '';
    const v = o.variavel.trim() || 'strsql';

    // A primeira linha da consulta já vem na atribuição (sem o "" & vbcrlf & inicial)
    const fimLinha = (i) => {
      const ultima = i === linhas.length - 1;
      if (o.estilo === 'acumular') return (q && (!ultima || o.quebraNoFim)) ? ' & ' + q : '';
      return ultima ? (o.quebraNoFim && q ? ' & ' + q : '') : (q ? ' & ' + q + ' &' : ' &');
    };
    const out = linhas.map((l, i) => {
      const lit = literalVb(l, o.converterVariaveis) + fimLinha(i);
      return i === 0 ? v + ' = ' + lit : (o.estilo === 'acumular' ? v + ' &= ' + lit : lit);
    });
    let codigo = out.join('\n');

    if (o.incluirExecucao && o.linhaExecucao.trim()) {
      codigo += '\n' + o.linhaExecucao.replace(/\{sql\}/g, v);
    }
    return codigo;
  }

  /** Nome sugerido para guardar a consulta (ex.: "SELECT clientes"). */
  function sugerirNome(sql) {
    const s = String(sql || '').trim();
    if (!s) return 'Consulta';
    const verbo = (s.match(/^\s*(select|insert|update|delete|with|exec|execute|create|alter|drop|merge)\b/i) || [])[1];
    const tabela = (s.match(/\b(?:from|into|update|join)\s+([\w.\[\]#@]+)/i) || [])[1];
    const v = verbo ? verbo.toUpperCase() : 'Consulta';
    return tabela ? v + ' ' + tabela.replace(/[\[\]]/g, '') : v;
  }

  const api = { vbParaSql, sqlParaVb, aplicarValores, umaLinha, sugerirNome, variaveisDe, PADRAO_VB };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else raiz.ConsultaTools = api;
})(typeof window !== 'undefined' ? window : globalThis);
