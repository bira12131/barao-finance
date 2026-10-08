/**
 * Cofre de senhas: criptografia no navegador (o servidor só guarda texto cifrado).
 *
 *  - Senha mestra  -> PBKDF2-SHA256 (600 mil voltas) -> chave que protege a CHAVE DE DADOS (DEK, 256 bits aleatória)
 *  - Chave de recuperação (aleatória, mostrada uma única vez) protege a mesma DEK por outro caminho
 *  - Cada item é cifrado com AES-256-GCM usando a DEK, "preso" ao próprio id (um item não vale em outro lugar)
 *
 * Sem a senha mestra OU a chave de recuperação não há como ler os dados: nem o servidor consegue.
 * Roda no navegador (window.CofreCrypto) e no Node (module.exports), por isso é testável.
 */
(function (raiz) {
  'use strict';

  const subtle = () => (raiz.crypto || globalThis.crypto).subtle;
  const aleatorio = (n) => (raiz.crypto || globalThis.crypto).getRandomValues(new Uint8Array(n));
  const enc = new TextEncoder();
  const dec = new TextDecoder();

  const ITERACOES = 600000;
  const VERSAO = 1;

  /* ════════════════════════════════════════════
     Base64 e Base32
  ════════════════════════════════════════════ */

  function paraB64(bytes) {
    let s = '';
    for (let i = 0; i < bytes.length; i += 0x8000) s += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
    return btoa(s);
  }

  function deB64(texto) {
    const s = atob(texto);
    const out = new Uint8Array(s.length);
    for (let i = 0; i < s.length; i++) out[i] = s.charCodeAt(i);
    return out;
  }

  const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

  function paraBase32(bytes) {
    let bits = 0, valor = 0, out = '';
    for (const b of bytes) {
      valor = (valor << 8) | b;
      bits += 8;
      while (bits >= 5) { out += BASE32[(valor >>> (bits - 5)) & 31]; bits -= 5; }
    }
    if (bits > 0) out += BASE32[(valor << (5 - bits)) & 31];
    return out;
  }

  function deBase32(texto) {
    const limpo = String(texto).toUpperCase().replace(/[\s=-]+/g, '');
    let bits = 0, valor = 0;
    const out = [];
    for (const c of limpo) {
      const i = BASE32.indexOf(c);
      if (i < 0) throw new Error('base32_invalido');
      valor = (valor << 5) | i;
      bits += 5;
      if (bits >= 8) { out.push((valor >>> (bits - 8)) & 255); bits -= 8; }
    }
    return new Uint8Array(out);
  }

  /* ════════════════════════════════════════════
     Chaves
  ════════════════════════════════════════════ */

  async function derivarChave(segredo, sal, iteracoes) {
    const base = await subtle().importKey('raw', enc.encode(segredo), 'PBKDF2', false, ['deriveKey']);
    return subtle().deriveKey(
      { name: 'PBKDF2', hash: 'SHA-256', salt: sal, iterations: iteracoes },
      base, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']
    );
  }

  const normalizarSenha = (s) => String(s).normalize('NFKC');

  /** Chave de recuperação: o que o usuário digita (com traços, minúsculas, 0/1 no lugar de O/I) vira a forma canônica. */
  function normalizarRecuperacao(texto) {
    return String(texto).toUpperCase().replace(/0/g, 'O').replace(/1/g, 'I').replace(/8/g, 'B').replace(/[^A-Z2-7]/g, '');
  }

  function formatarRecuperacao(base32) {
    return base32.match(/.{1,4}/g).join('-');
  }

  async function cifrar(chave, bytes, aad) {
    const iv = aleatorio(12);
    const ct = new Uint8Array(await subtle().encrypt({ name: 'AES-GCM', iv, additionalData: enc.encode(aad) }, chave, bytes));
    return { iv: paraB64(iv), ct: paraB64(ct) };
  }

  async function decifrar(chave, blob, aad) {
    return new Uint8Array(await subtle().decrypt(
      { name: 'AES-GCM', iv: deB64(blob.iv), additionalData: enc.encode(aad) }, chave, deB64(blob.ct)
    ));
  }

  async function sessaoDeDek(dekBytes) {
    const chave = await subtle().importKey('raw', dekBytes, { name: 'AES-GCM' }, true, ['encrypt', 'decrypt']);
    return { chave, bloqueado: false };
  }

  const AAD_MESTRA = 'cofre:v1:dek:mestra';
  const AAD_RECUP = 'cofre:v1:dek:recuperacao';

  /* ════════════════════════════════════════════
     Ciclo de vida do cofre
  ════════════════════════════════════════════ */

  /**
   * Cria o cofre.
   * @return {{config:object, chaveRecuperacao:string, sessao:object}}  `config` é o que vai para o servidor.
   */
  async function criarCofre(senhaMestra, opcoes) {
    const iter = (opcoes && opcoes.iteracoes) || ITERACOES;
    if (String(senhaMestra).length < 8) throw new Error('senha_curta');

    const dek = aleatorio(32);
    const recBytes = aleatorio(20);
    const recuperacao = paraBase32(recBytes);                      // 32 caracteres

    const salMestra = aleatorio(16), salRecup = aleatorio(16);
    const kekMestra = await derivarChave(normalizarSenha(senhaMestra), salMestra, iter);
    const kekRecup = await derivarChave(recuperacao, salRecup, iter);

    return {
      config: {
        versao: VERSAO,
        iter_mestra: iter, salt_mestra: paraB64(salMestra), dek_mestra: await cifrar(kekMestra, dek, AAD_MESTRA),
        iter_recup: iter, salt_recup: paraB64(salRecup), dek_recup: await cifrar(kekRecup, dek, AAD_RECUP),
      },
      chaveRecuperacao: formatarRecuperacao(recuperacao),
      sessao: await sessaoDeDek(dek),
    };
  }

  /** Abre com a senha mestra. Lança Error('senha_incorreta') se estiver errada. */
  async function abrirCofre(config, senhaMestra) {
    const kek = await derivarChave(normalizarSenha(senhaMestra), deB64(config.salt_mestra), config.iter_mestra);
    let dek;
    try { dek = await decifrar(kek, config.dek_mestra, AAD_MESTRA); }
    catch (_) { throw new Error('senha_incorreta'); }
    return sessaoDeDek(dek);
  }

  async function abrirComRecuperacao(config, chaveRecuperacao) {
    const kek = await derivarChave(normalizarRecuperacao(chaveRecuperacao), deB64(config.salt_recup), config.iter_recup);
    let dek;
    try { dek = await decifrar(kek, config.dek_recup, AAD_RECUP); }
    catch (_) { throw new Error('recuperacao_incorreta'); }
    return sessaoDeDek(dek);
  }

  /** Reembrulha a mesma DEK com uma nova senha mestra (os itens não precisam ser regravados). */
  async function novaSenhaMestra(sessao, novaSenha, opcoes) {
    if (String(novaSenha).length < 8) throw new Error('senha_curta');
    const iter = (opcoes && opcoes.iteracoes) || ITERACOES;
    const dek = new Uint8Array(await subtle().exportKey('raw', sessao.chave));
    const sal = aleatorio(16);
    const kek = await derivarChave(normalizarSenha(novaSenha), sal, iter);
    return { iter_mestra: iter, salt_mestra: paraB64(sal), dek_mestra: await cifrar(kek, dek, AAD_MESTRA) };
  }

  /** Gera outra chave de recuperação (a antiga deixa de valer). */
  async function novaRecuperacao(sessao, opcoes) {
    const iter = (opcoes && opcoes.iteracoes) || ITERACOES;
    const dek = new Uint8Array(await subtle().exportKey('raw', sessao.chave));
    const recuperacao = paraBase32(aleatorio(20));
    const sal = aleatorio(16);
    const kek = await derivarChave(recuperacao, sal, iter);
    return {
      parcial: { iter_recup: iter, salt_recup: paraB64(sal), dek_recup: await cifrar(kek, dek, AAD_RECUP) },
      chaveRecuperacao: formatarRecuperacao(recuperacao),
    };
  }

  function bloquear(sessao) {
    if (sessao) { sessao.chave = null; sessao.bloqueado = true; }
  }

  /* ════════════════════════════════════════════
     Itens
  ════════════════════════════════════════════ */

  const aadItem = (id) => 'cofre:v1:item:' + id;

  async function cifrarItem(sessao, id, objeto) {
    if (!sessao || !sessao.chave) throw new Error('cofre_bloqueado');
    return cifrar(sessao.chave, enc.encode(JSON.stringify(objeto)), aadItem(id));
  }

  async function decifrarItem(sessao, id, blob) {
    if (!sessao || !sessao.chave) throw new Error('cofre_bloqueado');
    return JSON.parse(dec.decode(await decifrar(sessao.chave, blob, aadItem(id))));
  }

  /* ════════════════════════════════════════════
     Gerador e força
  ════════════════════════════════════════════ */

  const CLASSES = {
    minusculas: 'abcdefghijklmnopqrstuvwxyz',
    maiusculas: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
    numeros: '0123456789',
    simbolos: '!@#$%&*()-_=+[]{};:,.?/',
  };
  const AMBIGUOS = /[Il1O0o|`'"]/g;

  /** Inteiro uniforme em [0, max) sem viés. */
  function inteiroAleatorio(max) {
    const limite = Math.floor(0x100000000 / max) * max;
    for (;;) {
      const v = new Uint32Array(aleatorio(4).buffer)[0];
      if (v < limite) return v % max;
    }
  }

  function gerarSenha(op) {
    const o = Object.assign({ tamanho: 20, minusculas: true, maiusculas: true, numeros: true, simbolos: true, evitarAmbiguos: true }, op || {});
    const classes = ['minusculas', 'maiusculas', 'numeros', 'simbolos']
      .filter(c => o[c])
      .map(c => (o.evitarAmbiguos ? CLASSES[c].replace(AMBIGUOS, '') : CLASSES[c]));
    if (!classes.length) throw new Error('nenhuma_classe');
    const tamanho = Math.max(classes.length, Math.min(128, o.tamanho | 0));
    const todos = classes.join('');

    const out = classes.map(c => c[inteiroAleatorio(c.length)]);      // ao menos um de cada tipo
    while (out.length < tamanho) out.push(todos[inteiroAleatorio(todos.length)]);
    for (let i = out.length - 1; i > 0; i--) {                         // embaralha (Fisher-Yates)
      const j = inteiroAleatorio(i + 1);
      [out[i], out[j]] = [out[j], out[i]];
    }
    return out.join('');
  }

  /** Estimativa simples de força (bits de entropia) com penalidade para repetição e sequências. */
  function forcaSenha(senha) {
    const s = String(senha || '');
    if (!s) return { bits: 0, nivel: 'vazia' };
    let pool = 0;
    if (/[a-z]/.test(s)) pool += 26;
    if (/[A-Z]/.test(s)) pool += 26;
    if (/\d/.test(s)) pool += 10;
    if (/[^A-Za-z0-9]/.test(s)) pool += 28;
    let bits = s.length * Math.log2(pool || 1);
    if (/(.)\1{2,}/.test(s)) bits *= 0.75;
    if (/(?:abc|bcd|cde|123|234|345|456|567|678|789|qwe|asd|zxc)/i.test(s)) bits *= 0.8;
    if (/^(?:senha|password|admin|123456|qwerty)/i.test(s)) bits = Math.min(bits, 20);
    bits = Math.round(bits);
    const nivel = bits < 40 ? 'fraca' : bits < 60 ? 'razoável' : bits < 80 ? 'forte' : 'excelente';
    return { bits, nivel };
  }

  /* ════════════════════════════════════════════
     Código de verificação (TOTP, RFC 6238)
  ════════════════════════════════════════════ */

  function lerOtpauth(entrada) {
    const t = String(entrada || '').trim();
    let segredo = t, periodo = 30, digitos = 6, algoritmo = 'SHA-1';
    if (/^otpauth:\/\//i.test(t)) {
      const u = new URL(t);
      segredo = u.searchParams.get('secret') || '';
      periodo = +(u.searchParams.get('period') || 30);
      digitos = +(u.searchParams.get('digits') || 6);
      const a = (u.searchParams.get('algorithm') || 'SHA1').toUpperCase().replace('SHA', 'SHA-');
      algoritmo = ['SHA-1', 'SHA-256', 'SHA-512'].includes(a) ? a : 'SHA-1';
    }
    return { segredo: segredo.replace(/\s+/g, ''), periodo, digitos, algoritmo };
  }

  async function totp(entrada, agoraMs) {
    const p = lerOtpauth(entrada);
    const agora = agoraMs === undefined ? Date.now() : agoraMs;
    const contador = Math.floor(agora / 1000 / p.periodo);
    const msg = new Uint8Array(8);
    new DataView(msg.buffer).setUint32(4, contador >>> 0);
    new DataView(msg.buffer).setUint32(0, Math.floor(contador / 0x100000000));
    const chave = await subtle().importKey('raw', deBase32(p.segredo), { name: 'HMAC', hash: p.algoritmo }, false, ['sign']);
    const h = new Uint8Array(await subtle().sign('HMAC', chave, msg));
    const off = h[h.length - 1] & 0xf;
    const bin = ((h[off] & 0x7f) << 24) | (h[off + 1] << 16) | (h[off + 2] << 8) | h[off + 3];
    const codigo = String(bin % Math.pow(10, p.digitos)).padStart(p.digitos, '0');
    return { codigo, restante: p.periodo - (Math.floor(agora / 1000) % p.periodo), periodo: p.periodo };
  }

  /* ════════════════════════════════════════════
     CSV (importar/exportar: app Senhas da Apple, Chrome, 1Password…)
  ════════════════════════════════════════════ */

  function lerCsv(texto) {
    const linhas = [];
    let campo = '', linha = [], aspas = false;
    const t = String(texto).replace(/^﻿/, '');
    for (let i = 0; i < t.length; i++) {
      const c = t[i];
      if (aspas) {
        if (c === '"') { if (t[i + 1] === '"') { campo += '"'; i++; } else aspas = false; }
        else campo += c;
      } else if (c === '"') aspas = true;
      else if (c === ',') { linha.push(campo); campo = ''; }
      else if (c === '\n' || c === '\r') {
        if (c === '\r' && t[i + 1] === '\n') i++;
        linha.push(campo); campo = '';
        if (linha.some(v => v !== '') || linha.length > 1) linhas.push(linha);
        linha = [];
      } else campo += c;
    }
    linha.push(campo);
    if (linha.some(v => v !== '')) linhas.push(linha);
    return linhas;
  }

  const SINONIMOS = {
    titulo: ['title', 'name', 'nome', 'titulo', 'título', 'item', 'account', 'login_name'],
    url: ['url', 'website', 'web site', 'site', 'login_uri', 'login url', 'login_url'],
    usuario: ['username', 'login', 'login_username', 'user', 'usuario', 'usuário', 'email', 'e-mail'],
    senha: ['password', 'senha', 'login_password', 'pass'],
    notas: ['notes', 'note', 'notas', 'extra', 'observacoes', 'observações'],
    totp: ['otpauth', 'totp', 'otp', 'login_totp', 'one-time password'],
  };

  /** CSV -> itens {titulo, url, usuario, senha, notas, totp}. Reconhece os cabeçalhos mais comuns. */
  function itensDeCsv(texto) {
    const linhas = lerCsv(texto);
    if (linhas.length < 2) return { itens: [], colunas: [] };
    const cab = linhas[0].map(c => c.trim().toLowerCase());
    const idx = {};
    for (const campo of Object.keys(SINONIMOS)) idx[campo] = cab.findIndex(c => SINONIMOS[campo].includes(c));
    const itens = [];
    for (const l of linhas.slice(1)) {
      const pega = (campo) => (idx[campo] >= 0 ? (l[idx[campo]] || '').trim() : '');
      const item = { titulo: pega('titulo'), url: pega('url'), usuario: pega('usuario'), senha: l[idx.senha] || '', notas: pega('notas'), totp: pega('totp') };
      if (!item.titulo) item.titulo = item.url.replace(/^https?:\/\/(www\.)?/i, '').split('/')[0] || item.usuario || 'Sem título';
      if (item.senha || item.usuario || item.url || item.totp) itens.push(item);
    }
    return { itens, colunas: Object.keys(idx).filter(k => idx[k] >= 0) };
  }

  /** Itens -> CSV no formato do app Senhas da Apple (Title,URL,Username,Password,Notes,OTPAuth). */
  function csvDeItens(itens) {
    const esc = (v) => {
      v = String(v == null ? '' : v);
      return /[",\r\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
    };
    const linhas = [['Title', 'URL', 'Username', 'Password', 'Notes', 'OTPAuth']];
    for (const i of itens) linhas.push([i.titulo, i.url, i.usuario, i.senha, i.notas, i.totp]);
    return linhas.map(l => l.map(esc).join(',')).join('\r\n') + '\r\n';
  }

  const api = {
    ITERACOES, criarCofre, abrirCofre, abrirComRecuperacao, novaSenhaMestra, novaRecuperacao, bloquear,
    cifrarItem, decifrarItem, gerarSenha, forcaSenha, totp, itensDeCsv, csvDeItens, lerCsv,
    normalizarRecuperacao, paraB64, deB64, paraBase32, deBase32,
  };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else raiz.CofreCrypto = api;
})(typeof window !== 'undefined' ? window : globalThis);
