/**
 * pierre-sync.js
 *
 * Utilitário de sincronização com o Pierre Finance.
 * Incluído nas páginas que precisam do botão "Atualizar".
 *
 * Uso:
 *   PierreSync.atualizar(carregarDadosFn, btnElement)
 */

const PierreSync = (() => {

  function _apiBase() {
    const { origin, pathname } = window.location;
    const root = pathname
      .replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '')
      .replace(/\/[^/]*\.(?:html|php)$/, '');
    return origin + root + '/backend/api';
  }

  /**
   * Sincroniza dados com o Pierre Finance e depois chama a função de reload.
   *
   * @param {Function} recarregarFn  - função que (re)carrega os dados da página
   * @param {HTMLElement} [btn]      - botão que disparou a ação (desabilitado durante sync)
   * @param {{escopo?: 'contas'}} [opcoes] - 'contas' atualiza só saldos e cartões (rápido, sem transações)
   */
  async function atualizar(recarregarFn, btn, opcoes = {}) {
    if (btn) {
      btn.disabled = true;
      btn.textContent = '⟳ Sincronizando...';
    }

    // Pede aos bancos para atualizarem (a Pierre leva ~12s e termina em segundo plano).
    // Não esperamos: o clique seguinte já traz os dados novos.
    try {
      fetch(_apiBase() + '/pierre/sincronizar.php?somente_bancos=1', {
        method: 'POST',
        keepalive: true,
        cache: 'no-store',
      }).catch(() => {});
    } catch (_) {
      // sem problema: a sincronização abaixo segue normalmente
    }

    try {
      // Traz o que a Pierre já tem (rápido)
      const res  = await fetch(_apiBase() + '/pierre/sincronizar.php' + (opcoes.escopo === 'contas' ? '?escopo=contas' : ''), {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        // cache: 'no-store' garante que o browser não reutilize resposta antiga
        cache:   'no-store',
      });
      await res.json(); // consome a resposta (erros de sync são silenciosos)
    } catch (_) {
      // Sync falhou (sem contas conectadas, etc.) — recarrega mesmo assim
    }

    // Recarrega dados da página com cache-busting
    if (typeof recarregarFn === 'function') {
      await recarregarFn();
    }

    if (btn) {
      btn.disabled = false;
      btn.textContent = '↻ Atualizar';
    }
  }

  return { atualizar };
})();
