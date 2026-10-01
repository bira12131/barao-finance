<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Configurações</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <script src="../../js/app-runtime.js"></script>
  <script src="../../js/pierre-sync.js"></script>
  <style>
    .config-section {
      margin-bottom: 1.5rem;
    }
    .config-section-title {
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.07em;
      margin-bottom: 0.75rem;
    }
    .config-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.875rem 0;
      border-bottom: 1px solid rgba(197,207,222,0.5);
      gap: 1rem;
    }
    .config-row:last-child { border-bottom: none; }
    .config-row-label {
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--text-heading);
    }
    .config-row-desc {
      font-size: 0.8rem;
      color: var(--text-muted);
      margin-top: 0.15rem;
    }
    .config-value {
      font-size: 0.875rem;
      color: var(--text-muted);
      flex-shrink: 0;
    }
    .btn-config {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 0.45rem 0.875rem;
      border-radius: var(--radius-sm);
      font-size: 0.82rem;
      font-weight: 600;
      cursor: pointer;
      border: 1.5px solid var(--border);
      background: var(--bg-input);
      color: var(--text-body);
      transition: background 0.15s, border-color 0.15s;
      flex-shrink: 0;
    }
    .btn-config:hover {
      border-color: var(--primary);
      color: var(--primary);
      background: var(--primary-light);
    }
    .btn-config.danger {
      border-color: #fca5a5;
      color: var(--danger);
    }
    .btn-config.danger:hover {
      background: #fff1f2;
    }
    .sync-status {
      font-size: 0.78rem;
      color: var(--text-muted);
      margin-top: 0.35rem;
    }
    .sync-status.ok   { color: #16a34a; }
    .sync-status.erro { color: var(--danger); }
    .badge-conectado {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 0.2rem 0.6rem;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 700;
      background: #dcfce7;
      color: #15803d;
      border: 1px solid #bbf7d0;
      margin-top: 0.35rem;
    }
  </style>
</head>
<body class="inner-page">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Configurações</h1>
      <p>Gerencie sua conta e preferências</p>
    </div>
  </div>

  <!-- Perfil -->
  <div class="card config-section" style="margin-bottom:1rem;">
    <div class="config-section-title">Perfil</div>

    <div class="config-row">
      <div>
        <div class="config-row-label">Nome</div>
      </div>
      <span class="config-value" id="cfg-nome">—</span>
    </div>

    <div class="config-row">
      <div>
        <div class="config-row-label">E-mail</div>
      </div>
      <span class="config-value" id="cfg-email">—</span>
    </div>

    <div class="config-row">
      <div>
        <div class="config-row-label">Senha</div>
        <div class="config-row-desc">Altere sua senha de acesso</div>
      </div>
      <button class="btn-config" disabled title="Em breve">
        Alterar senha
      </button>
    </div>
  </div>

  <!-- Conta -->
  <div class="card config-section">
    <div class="config-section-title">Conta</div>

    <!-- Pierre Finance — sincronização -->
    <div class="config-row" style="align-items:flex-start; flex-wrap:wrap; gap:0.75rem;">
      <div style="flex:1; min-width:0;">
        <div class="config-row-label">Pierre Finance</div>
        <div class="config-row-desc">Sincroniza contas bancárias, cartões e transações com a API Pierre Finance</div>
        <span class="badge-conectado" id="badge-pierre" style="display:none">
          ● Conectado
        </span>
        <div class="sync-status" id="sync-status">Verificando…</div>
      </div>
      <button class="btn-config" id="btn-sync-cfg" onclick="sincronizarAgora(this)"
        style="border-color:var(--primary); color:var(--primary);">
        ↻ Sincronizar agora
      </button>
    </div>

    <!-- Resultado da última sincronização -->
    <div id="sync-result" class="config-row" style="display:none; flex-direction:column; align-items:flex-start; gap:0.35rem;">
      <div class="config-row-label" style="font-size:0.82rem;">Última sincronização</div>
      <div id="sync-result-body" style="font-size:0.82rem; color:var(--text-muted); line-height:1.6;"></div>
    </div>

    <div class="config-row">
      <div>
        <div class="config-row-label" style="color:var(--danger);">Excluir conta</div>
        <div class="config-row-desc">Remove permanentemente todos os seus dados</div>
      </div>
      <button class="btn-config danger" disabled title="Em breve">
        Excluir
      </button>
    </div>
  </div>

  <script>
    const session = JSON.parse(sessionStorage.getItem('baraofinance_session') || 'null');
    if (session) {
      document.getElementById('cfg-nome').textContent  = session.name  || '—';
      document.getElementById('cfg-email').textContent = session.email || '—';
    }

    function _apiBase() {
      const { origin, pathname } = window.location;
      const root = pathname
        .replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '')
        .replace(/\/[^/]*\.(?:html|php)$/, '');
      return origin + root + '/backend/api';
    }

    // Carrega status da última sincronização ao abrir a página
    async function carregarStatusSync() {
      try {
        const res  = await fetch(_apiBase() + '/pierre/status.php', { cache: 'no-store' });
        const json = await res.json();
        const el   = document.getElementById('sync-status');
        const badge = document.getElementById('badge-pierre');

        if (json.data?.api_configurada) {
          badge.style.display = 'inline-flex';
        }

        if (json.data?.status === 'banco_nao_inicializado') {
          el.textContent = 'API conectada. Execute o SQL no banco para ativar o armazenamento local.';
          el.className   = 'sync-status';
          return;
        }

        if (json.data?.status === 'acesso_negado_owner') {
          el.textContent = 'Integração Pierre vinculada a outro usuário deste ambiente.';
          el.className   = 'sync-status erro';
          document.getElementById('btn-sync-cfg').disabled = true;
          return;
        }

        if (json.success && json.data?.ultima_sync) {
          const d = new Date(json.data.ultima_sync);
          const fmt = d.toLocaleString('pt-BR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
          el.textContent = `Última sincronização: ${fmt}`;
          el.className   = 'sync-status ok';
        } else {
          // Nunca sincronizado — dispara automaticamente
          el.textContent = 'Primeira sincronização em andamento…';
          el.className   = 'sync-status';
          sincronizarAgora(document.getElementById('btn-sync-cfg'));
        }
      } catch (_) {
        document.getElementById('sync-status').textContent = 'Não foi possível verificar o status.';
      }
    }

    // Sincronização manual
    async function sincronizarAgora(btn) {
      btn.disabled   = true;
      btn.textContent = '⟳ Sincronizando…';
      document.getElementById('sync-status').textContent = 'Sincronizando com Pierre Finance…';
      document.getElementById('sync-status').className   = 'sync-status';

      try {
        const res  = await fetch(_apiBase() + '/pierre/sincronizar.php', {
          method: 'POST',
          cache:  'no-store',
        });
        const raw  = await res.text();
        let json;
        try {
          json = JSON.parse(raw);
        } catch (_) {
          throw new Error(raw?.trim() || `Resposta inválida do servidor (HTTP ${res.status})`);
        }

        const resultEl = document.getElementById('sync-result');
        const bodyEl   = document.getElementById('sync-result-body');

        if (res.ok && json.success) {
          document.getElementById('sync-status').textContent = `Sincronizado com sucesso às ${new Date().toLocaleTimeString('pt-BR')}`;
          document.getElementById('sync-status').className   = 'sync-status ok';

          const d = json.data || {};
          bodyEl.innerHTML = `
            <strong>${d.total_contas ?? 0}</strong> conta(s) &nbsp;·&nbsp;
            <strong>${d.total_transacoes ?? 0}</strong> transação(ões) &nbsp;·&nbsp;
            <strong>${d.total_assinaturas ?? 0}</strong> assinatura(s) detectada(s)
            ${d.erros?.length ? `<br><span style="color:var(--danger)">⚠ ${d.erros.join(' | ')}</span>` : ''}
          `;
          resultEl.style.display = 'flex';
        } else {
          const detail = json?.data?.erro || (Array.isArray(json?.data?.erros) ? json.data.erros.join(' | ') : '');
          const msg = json?.message || `Erro ao sincronizar (HTTP ${res.status}).`;
          document.getElementById('sync-status').textContent = msg;
          document.getElementById('sync-status').className   = 'sync-status erro';
          if (detail) {
            bodyEl.innerHTML = `<span style="color:var(--danger)">${detail}</span>`;
            resultEl.style.display = 'flex';
          }
        }
      } catch (err) {
        const msg = (err && err.message) ? err.message : 'Erro de conexão.';
        document.getElementById('sync-status').textContent = msg;
        document.getElementById('sync-status').className   = 'sync-status erro';
      }

      btn.disabled    = false;
      btn.textContent = '↻ Sincronizar agora';
    }

    carregarStatusSync();
  </script>

</body>
</html>
