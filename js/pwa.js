(() => {
  const INDEX_ENTRY_KEY = 'bf_pwa_index_entry';

  function getRootPath() {
    const { pathname } = window.location;
    const root = pathname
      .replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '')
      .replace(/\/[^/]*\.(?:html|php)$/, '');

    return root || '/';
  }

  function joinPath(root, suffix) {
    const normalizedRoot = root.endsWith('/') ? root.slice(0, -1) : root;
    return `${normalizedRoot}${suffix}`;
  }

  function isStandaloneMode() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  }

  function isMobileDevice() {
    return window.matchMedia('(max-width: 900px)').matches
      || /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');
  }

  function isIndexPath(pathname, rootPath) {
    if (rootPath === '/') {
      return pathname === '/' || pathname === '/index.html';
    }

    return pathname === rootPath || pathname === `${rootPath}/` || pathname === `${rootPath}/index.html`;
  }

  function ensureIndexAsInstallEntry() {
    const rootPath = getRootPath();
    const { pathname } = window.location;

    if (isIndexPath(pathname, rootPath)) {
      sessionStorage.setItem(INDEX_ENTRY_KEY, '1');
      return;
    }

    if (isStandaloneMode() && !sessionStorage.getItem(INDEX_ENTRY_KEY)) {
      const indexUrl = joinPath(rootPath, '/index.html?source=shortcut');
      window.location.replace(indexUrl);
    }
  }

  async function clearCachesOnMobileIndex() {
    const rootPath = getRootPath();
    const { pathname } = window.location;

    if (!isIndexPath(pathname, rootPath) || !isMobileDevice()) return;

    const markerKey = 'bf_last_mobile_cache_clear';
    const now = Date.now();
    const last = Number(localStorage.getItem(markerKey) || 0);

    // Evita limpar cache em toda abertura (janela de 6h).
    if (last && now - last < 6 * 60 * 60 * 1000) return;

    try {
      if ('caches' in window) {
        const keys = await caches.keys();
        await Promise.all(keys.map((key) => caches.delete(key)));
      }

      if ('serviceWorker' in navigator) {
        const regs = await navigator.serviceWorker.getRegistrations();
        await Promise.all(regs.map((reg) => reg.update()));
      }

      localStorage.removeItem('bf_page');
      localStorage.setItem(markerKey, String(now));
    } catch (_) {
      // Falha de limpeza não bloqueia o app.
    }
  }

  function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;

    const rootPath = getRootPath();
    const swUrl = joinPath(rootPath, '/sw.js');
    const scope = joinPath(rootPath, '/');
    const reloadedMarker = 'bf_sw_reloaded_once';

    window.addEventListener('load', () => {
      navigator.serviceWorker.register(swUrl, { scope }).then((registration) => {
        registration.update().catch(() => null);

        if (registration.waiting) {
          registration.waiting.postMessage({ type: 'SKIP_WAITING' });
        }

        registration.addEventListener('updatefound', () => {
          const newWorker = registration.installing;
          if (!newWorker) return;

          newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              newWorker.postMessage({ type: 'SKIP_WAITING' });
            }
          });
        });
      }).catch(() => {
        // Falha de registro nao deve interromper o app.
      });

      navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (sessionStorage.getItem(reloadedMarker)) return;
        sessionStorage.setItem(reloadedMarker, '1');
        window.location.reload();
      });
    });
  }

  ensureIndexAsInstallEntry();
  clearCachesOnMobileIndex();
  registerServiceWorker();
})();
