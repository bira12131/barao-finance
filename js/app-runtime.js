(() => {
  if (window.BFApp) return;

  const RUNTIME_ID = 'bf-app-modal-runtime';
  let modalState = null;
  let sessionModalOpen = false;

  function rootPath() {
    const { pathname } = window.location;
    const root = pathname
      .replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '')
      .replace(/\/[^/]*\.(?:html|php)$/, '');
    return root || '/';
  }

  function buildUrl(suffix) {
    const root = rootPath();
    const normalized = root.endsWith('/') ? root.slice(0, -1) : root;
    return `${window.location.origin}${normalized}${suffix}`;
  }

  function ensureModal() {
    if (modalState) return modalState;

    const wrapper = document.createElement('div');
    wrapper.id = RUNTIME_ID;
    wrapper.innerHTML = `
      <div class="bf-modal-backdrop" data-role="backdrop" aria-hidden="true">
        <div class="bf-modal-card" role="dialog" aria-modal="true" aria-labelledby="bf-modal-title">
          <div class="bf-modal-title" id="bf-modal-title"></div>
          <div class="bf-modal-message" id="bf-modal-message"></div>
          <div class="bf-modal-actions">
            <button type="button" class="bf-btn bf-btn-outline" data-role="cancel"></button>
            <button type="button" class="bf-btn bf-btn-primary" data-role="confirm"></button>
          </div>
        </div>
      </div>
    `;

    const style = document.createElement('style');
    style.textContent = `
      #${RUNTIME_ID} .bf-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(8, 12, 20, 0.72);
        padding: 1rem;
      }
      #${RUNTIME_ID} .bf-modal-backdrop.open {
        display: flex;
      }
      #${RUNTIME_ID} .bf-modal-card {
        width: min(460px, 100%);
        border: 1px solid rgba(72, 89, 117, 0.9);
        border-radius: 16px;
        background: linear-gradient(165deg, #1a2334, #121a2a);
        color: #e7edf9;
        box-shadow: 0 22px 44px rgba(0, 0, 0, 0.5);
        padding: 1rem;
      }
      #${RUNTIME_ID} .bf-modal-title {
        font-size: 1rem;
        font-weight: 800;
        margin-bottom: 0.45rem;
      }
      #${RUNTIME_ID} .bf-modal-message {
        font-size: 0.9rem;
        color: #aebdd8;
        line-height: 1.45;
        margin-bottom: 0.9rem;
      }
      #${RUNTIME_ID} .bf-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
      }
      #${RUNTIME_ID} .bf-btn {
        min-height: 36px;
        border-radius: 10px;
        padding: 0 0.9rem;
        border: 1px solid transparent;
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
      }
      #${RUNTIME_ID} .bf-btn-primary {
        background: #2f66d5;
        border-color: #2f66d5;
        color: #fff;
      }
      #${RUNTIME_ID} .bf-btn-outline {
        background: #0f1726;
        border-color: #3c4a66;
        color: #c8d4ea;
      }
    `;

    document.head.appendChild(style);
    document.body.appendChild(wrapper);

    const backdrop = wrapper.querySelector('[data-role="backdrop"]');
    const title = wrapper.querySelector('#bf-modal-title');
    const message = wrapper.querySelector('#bf-modal-message');
    const cancel = wrapper.querySelector('[data-role="cancel"]');
    const confirm = wrapper.querySelector('[data-role="confirm"]');

    modalState = { backdrop, title, message, cancel, confirm, resolver: null };

    function close(result) {
      if (modalState.resolver) {
        modalState.resolver(result);
      }
      modalState.resolver = null;
      modalState.backdrop.classList.remove('open');
      modalState.backdrop.setAttribute('aria-hidden', 'true');
    }

    cancel.addEventListener('click', () => close(false));
    confirm.addEventListener('click', () => close(true));

    return modalState;
  }

  function showModal(options) {
    const modal = ensureModal();
    modal.title.textContent = options.title || 'Aviso';
    modal.message.textContent = options.message || '';
    modal.cancel.textContent = options.cancelText || 'Cancelar';
    modal.confirm.textContent = options.confirmText || 'OK';

    modal.cancel.style.display = options.showCancel ? 'inline-flex' : 'none';

    modal.backdrop.classList.add('open');
    modal.backdrop.setAttribute('aria-hidden', 'false');

    return new Promise((resolve) => {
      modal.resolver = resolve;
    });
  }

  async function modalAlert(message, title = 'Aviso') {
    await showModal({
      title,
      message,
      confirmText: 'Entendi',
      showCancel: false,
    });
  }

  async function modalConfirm(message, title = 'Confirmação') {
    const accepted = await showModal({
      title,
      message,
      confirmText: 'Confirmar',
      cancelText: 'Cancelar',
      showCancel: true,
    });
    return accepted === true;
  }

  async function logoutAndRedirect() {
    const targetWindow = (() => {
      try {
        if (window.top && window.top !== window && window.top.location.origin === window.location.origin) {
          return window.top;
        }
      } catch (_) {
        // noop
      }
      return window;
    })();

    try {
      await fetch(buildUrl('/backend/api/logout.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        cache: 'no-store',
      });
    } catch (_) {
      // noop
    }

    try {
      targetWindow.sessionStorage.removeItem('baraofinance_session');
      targetWindow.localStorage.removeItem('bf_page');
    } catch (_) {
      // noop
    }

    targetWindow.location.replace(buildUrl('/index.html'));
  }

  async function handleSessionExpiredModal() {
    if (sessionModalOpen) return;
    sessionModalOpen = true;

    const targetApp = (() => {
      try {
        if (window.top && window.top !== window && window.top.BFApp) {
          return window.top.BFApp;
        }
      } catch (_) {
        // noop
      }
      return null;
    })();

    if (targetApp && typeof targetApp.triggerSessionExpired === 'function') {
      targetApp.triggerSessionExpired();
      return;
    }

    await showModal({
      title: 'Sessão expirada',
      message: 'Sua sessão foi encerrada por segurança. Clique para entrar novamente.',
      confirmText: 'Fazer login',
      showCancel: false,
    });

    await logoutAndRedirect();
  }

  const nativeFetch = window.fetch.bind(window);
  window.fetch = async (...args) => {
    const response = await nativeFetch(...args);
    if (response.status === 401) {
      handleSessionExpiredModal();
      return new Response(
        JSON.stringify({ success: false, message: 'Sessão expirada.' }),
        { status: 401, headers: { 'Content-Type': 'application/json' } }
      );
    }
    return response;
  };

  window.BFApp = {
    modalAlert,
    modalConfirm,
    logoutAndRedirect,
    triggerSessionExpired: handleSessionExpiredModal,
  };
})();


/**
 * Ícones de traço fino (mesmo estilo dos ícones do menu lateral: linha de 2px, pontas redondas).
 *
 *   <i data-icone="copiar"></i>      -> vira o SVG (Icone.aplicar() roda sozinho ao abrir a página)
 *   Icone.el('olho')                 -> elemento <svg> para montar na tela por código
 *   Icone.svg('olho')                -> texto do SVG
 */
(function (raiz) {
  'use strict';

  const CAMINHOS = {
    copiar: '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    olho: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    'olho-off': '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>',
    aleatorio: '<polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/>',
    baixar: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
    enviar: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
    chave: '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
    boia: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><line x1="4.93" y1="4.93" x2="9.17" y2="9.17"/><line x1="14.83" y1="14.83" x2="19.07" y2="19.07"/><line x1="14.83" y1="9.17" x2="19.07" y2="4.93"/><line x1="4.93" y1="19.07" x2="9.17" y2="14.83"/>',
    cadeado: '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    lixeira: '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
    mais: '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
    x: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    check: '<polyline points="20 6 9 17 4 12"/>',
    estrela: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
    trocar: '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
    prancheta: '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>',
    lapis: '<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>',
    link: '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
    arquivo: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
    'seta-esq': '<polyline points="15 18 9 12 15 6"/>',
    'seta-dir': '<polyline points="9 18 15 12 9 6"/>',
    engrenagem: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
  };

  function svg(nome) {
    const c = CAMINHOS[nome];
    if (!c) throw new Error('Ícone desconhecido: ' + nome);
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + c + '</svg>';
  }

  function el(nome) {
    const t = document.createElement('template');
    t.innerHTML = svg(nome);
    return t.content.firstChild;
  }

  /** Troca cada <i data-icone="nome"></i> pelo SVG correspondente. */
  function aplicar(base) {
    (base || document).querySelectorAll('i[data-icone]').forEach((i) => {
      try { i.replaceWith(el(i.getAttribute('data-icone'))); } catch (_) { /* ícone inexistente: deixa vazio */ }
    });
  }

  const api = { svg, el, aplicar, nomes: Object.keys(CAMINHOS) };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else {
    raiz.Icone = api;
    if (raiz.document) {
      if (raiz.document.readyState === 'loading') raiz.document.addEventListener('DOMContentLoaded', () => aplicar());
      else aplicar();
    }
  }
})(typeof window !== 'undefined' ? window : globalThis);
