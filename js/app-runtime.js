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
