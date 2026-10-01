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
   */
  async function atualizar(recarregarFn, btn) {
    if (btn) {
      btn.disabled = true;
      btn.textContent = '⟳ Sincronizando...';
    }

    try {
      // Força sincronização no Pierre Finance
      const res  = await fetch(_apiBase() + '/pierre/sincronizar.php', {
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
