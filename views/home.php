<?php
/*
 * VIEW — home.php (Shell principal)
 *
 * Renderiza o layout com sidebar + iframe de conteúdo.
 * Verificação de sessão feita via JavaScript (sessionStorage).
 *
 * TODO: quando o login PHP estiver implementado, descomentar:
 * session_start();
 * if (!isset($_SESSION['usuario_id'])) {
 *     header('Location: ../index.html'); exit;
 * }
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Barão Finance</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="manifest" href="../manifest.webmanifest" />
  <link rel="icon" type="image/svg+xml" href="../assets/icons/icon.svg" />
  <link rel="apple-touch-icon" href="../assets/icons/icon-180.svg" />
  <meta name="theme-color" content="#3b6fd4" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="default" />
  <meta name="apple-mobile-web-app-title" content="Barao Finance" />
  <link rel="stylesheet" href="../css/app.css" />
  <script src="../js/app-runtime.js"></script>
</head>
<body>
<div class="app-layout">

  <!-- ══════════════════════════════════
       SIDEBAR
  ══════════════════════════════════ -->
  <aside class="sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
      <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="48" height="48" rx="12" fill="#3b6fd4"/>
        <path d="M14 34V20l10-8 10 8v14" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M20 34v-8h8v8" stroke="#a8c4f5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M10 34h28" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
      <span class="sidebar-logo-name">Barão <span>Finance</span></span>
    </div>

    <!-- Navegação principal -->
    <nav class="sidebar-nav">

      <span class="sidebar-section-label">Visão geral</span>

      <a class="sidebar-nav-item" data-page="pages/dashboard.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
          <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
        </svg>
        Dashboard
      </a>

      <a class="sidebar-nav-item" data-page="pages/agenda.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        Agenda
      </a>

      <span class="sidebar-section-label" style="margin-top:0.5rem;">Movimentações</span>

      <a class="sidebar-nav-item" data-page="pages/transacoes.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="1" x2="12" y2="23"/>
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
        </svg>
        Transações
      </a>

      <a class="sidebar-nav-item" data-page="pages/contas.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="5" width="20" height="14" rx="2"/>
          <line x1="2" y1="10" x2="22" y2="10"/>
        </svg>
        Contas
      </a>

      <a class="sidebar-nav-item" data-page="pages/cartoes.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
          <line x1="1" y1="10" x2="23" y2="10"/>
        </svg>
        Cartões
      </a>

      <span class="sidebar-section-label" style="margin-top:0.5rem;">Patrimônio e dívidas</span>

      <a class="sidebar-nav-item" data-page="pages/investimentos.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>
        </svg>
        Investimentos
      </a>

      <a class="sidebar-nav-item" data-page="pages/emprestimos.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="7" width="18" height="12" rx="2"/><path d="M3 11h18"/><circle cx="12" cy="15" r="1.5"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
        </svg>
        Empréstimos
      </a>

      <span class="sidebar-section-label" style="margin-top:0.5rem;">Planejamento</span>

      <a class="sidebar-nav-item" data-page="pages/assistente.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        Assistente IA
      </a>

      <a class="sidebar-nav-item" data-page="pages/metas.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>
        </svg>
        Metas
      </a>

      <a class="sidebar-nav-item" data-page="pages/despesas-previstas.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 3h18v4H3z"/>
          <path d="M6 7v14h12V7"/>
          <path d="M9 12h6"/>
        </svg>
        Despesas previstas
      </a>

      <a class="sidebar-nav-item" data-page="pages/assinaturas.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 6L9 17l-5-5"/>
          <path d="M3 4h18"/>
        </svg>
        Assinaturas
      </a>

      <span class="sidebar-section-label" style="margin-top:0.5rem;">Organização</span>

      <a class="sidebar-nav-item" data-page="pages/categorias-selecao.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 11 12 14 22 4"/>
          <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
        </svg>
        Seleção de categorias
      </a>

      <span class="sidebar-section-label" style="margin-top:0.5rem;">Ferramentas</span>

      <a class="sidebar-nav-item" data-page="pages/senhas.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        Senhas
      </a>

      <a class="sidebar-nav-item" data-page="pages/codigos.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>
        </svg>
        Códigos
      </a>

      <a class="sidebar-nav-item" data-page="pages/consulta.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/>
        </svg>
        Consulta VB ⇄ SQL
      </a>

      <span class="sidebar-section-label" style="margin-top:0.5rem;">Conta</span>

      <a class="sidebar-nav-item" data-page="pages/configuracoes.php" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="3"/>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
        </svg>
        Configurações
      </a>

    </nav>

    <!-- Rodapé: logout -->
    <div class="sidebar-bottom">
      <button class="sidebar-nav-item" id="btn-logout">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Sair
      </button>
    </div>

    <!-- Usuário logado -->
    <div class="sidebar-user">
      <div class="sidebar-user-avatar" id="user-avatar">?</div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name" id="user-name">Carregando...</div>
        <div class="sidebar-user-role" id="user-email">—</div>
      </div>
    </div>

  </aside>

  <!-- ══════════════════════════════════
       CONTEÚDO (iframe)
  ══════════════════════════════════ -->
  <main class="app-content">
    <header class="app-topbar">
      <div class="app-topbar-date" id="app-current-date">Carregando data...</div>
      <div class="app-profile-wrap">
        <button id="btn-profile-menu" class="app-profile-button" type="button" aria-expanded="false" aria-controls="app-profile-dropdown">
          <span class="app-profile-avatar" id="top-user-avatar">?</span>
          <span class="app-profile-name" id="top-user-name">Usuário</span>
        </button>
        <div id="app-profile-dropdown" class="app-profile-dropdown" aria-hidden="true">
          <div class="app-profile-dropdown-name" id="top-user-name-dropdown">Usuário</div>
          <div class="app-profile-dropdown-email" id="top-user-email-dropdown">email@dominio.com</div>
          <button id="btn-logout-top" class="app-profile-logout" type="button">Sair</button>
        </div>
      </div>
    </header>
    <iframe id="page-frame" class="app-frame" src="pages/dashboard.php" title="Conteúdo principal"></iframe>
  </main>

</div>

<nav class="mobile-bottom-nav" aria-label="Navegação principal mobile">
  <div class="mobile-bottom-nav-inner">
    <a class="mobile-tab" data-page="pages/dashboard.php" href="#" aria-label="Dashboard">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect>
        <rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect>
      </svg>
      <span>Início</span>
    </a>
    <a class="mobile-tab" data-page="pages/transacoes.php" href="#" aria-label="Transações">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="1" x2="12" y2="23"></line>
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
      </svg>
      <span>Transações</span>
    </a>
    <a class="mobile-tab" data-page="pages/contas.php" href="#" aria-label="Contas">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line>
      </svg>
      <span>Contas</span>
    </a>
    <a class="mobile-tab" data-page="pages/cartoes.php" href="#" aria-label="Cartões">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line>
      </svg>
      <span>Cartões</span>
    </a>
    <button class="mobile-tab" type="button" data-action="toggle-more" aria-label="Mais opções" aria-expanded="false">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
      <span>Mais</span>
    </button>
  </div>
</nav>

<div id="mobile-more-backdrop" class="mobile-more-backdrop" aria-hidden="true"></div>
<section id="mobile-more-sheet" class="mobile-more-sheet" aria-label="Mais opções" aria-hidden="true">
  <div class="mobile-more-header">Mais opções</div>
  <div class="mobile-more-list">
    <a class="mobile-more-item" data-page="pages/agenda.php" href="#">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
      </svg>
      Agenda
    </a>
    <a class="mobile-more-item" data-page="pages/assistente.php" href="#">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
      </svg>
      Assistente IA
    </a>
    <a class="mobile-more-item" data-page="pages/metas.php" href="#">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>
      </svg>
      Metas
    </a>
    <a class="mobile-more-item" data-page="pages/investimentos.php" href="#">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>
      </svg>
      Investimentos
    </a>
    <a class="mobile-more-item" data-page="pages/emprestimos.php" href="#">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="7" width="18" height="12" rx="2"/><path d="M3 11h18"/><circle cx="12" cy="15" r="1.5"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
      </svg>
      Empréstimos
    </a>
    <a class="mobile-more-item" data-page="pages/assinaturas.php" href="#">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 6L9 17l-5-5"></path><path d="M3 4h18"></path>
      </svg>
      Assinaturas
    </a>
    <a class="mobile-more-item" data-page="pages/categorias-selecao.php" href="#">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="9 11 12 14 22 4"></polyline>
        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
      </svg>
      Categorias
    </a>
    <a class="mobile-more-item" data-page="pages/despesas-previstas.php" href="#">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 3h18v4H3z"></path>
        <path d="M6 7v14h12V7"></path>
        <path d="M9 12h6"></path>
      </svg>
      Despesas previstas
    </a>
    <a class="mobile-more-item" data-page="pages/senhas.php" href="#">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
      </svg>
      Senhas
    </a>
    <a class="mobile-more-item" data-page="pages/codigos.php" href="#">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>
      </svg>
      Códigos
    </a>
    <a class="mobile-more-item" data-page="pages/consulta.php" href="#">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/>
      </svg>
      Consulta VB ⇄ SQL
    </a>
    <a class="mobile-more-item" data-page="pages/configuracoes.php" href="#">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="3"></circle>
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
      </svg>
      Configurações
    </a>
  </div>
</section>

<script src="../js/pwa.js"></script>
<script src="../js/models/User.js"></script>
<script>
  // ── Verificação de sessão ────────────────────────────────────
  const session = UserModel.getSession();
  if (!session) {
    window.location.replace('../index.html');
  }

  // ── Preenche dados do usuário ────────────────────────────────
  const firstName = (session?.name || 'Usuário').split(' ')[0];
  document.getElementById('user-name').textContent  = session?.name  || 'Usuário';
  document.getElementById('user-email').textContent = session?.email || '';
  document.getElementById('user-avatar').textContent = firstName.charAt(0).toUpperCase();
  document.getElementById('top-user-name').textContent = firstName;
  document.getElementById('top-user-avatar').textContent = firstName.charAt(0).toUpperCase();
  document.getElementById('top-user-name-dropdown').textContent = session?.name || 'Usuário';
  document.getElementById('top-user-email-dropdown').textContent = session?.email || 'Sem e-mail';

  const dateEl = document.getElementById('app-current-date');
  dateEl.textContent = new Intl.DateTimeFormat('pt-BR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long'
  }).format(new Date());

  const profileBtn = document.getElementById('btn-profile-menu');
  const profileDropdown = document.getElementById('app-profile-dropdown');

  function fecharMenuPerfil() {
    profileBtn.setAttribute('aria-expanded', 'false');
    profileDropdown.setAttribute('aria-hidden', 'true');
    profileDropdown.classList.remove('open');
  }

  function toggleMenuPerfil() {
    const abrir = !profileDropdown.classList.contains('open');
    profileBtn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
    profileDropdown.setAttribute('aria-hidden', abrir ? 'false' : 'true');
    profileDropdown.classList.toggle('open', abrir);
  }

  profileBtn.addEventListener('click', toggleMenuPerfil);
  document.addEventListener('click', (event) => {
    if (!profileDropdown.contains(event.target) && !profileBtn.contains(event.target)) {
      fecharMenuPerfil();
    }
  });

  // ── Navegação via iframe ─────────────────────────────────────
  const frame    = document.getElementById('page-frame');
  const navItems = document.querySelectorAll('[data-page]');
  const mobileMoreToggles = document.querySelectorAll('[data-action="toggle-more"]');
  const mobileMoreBackdrop = document.getElementById('mobile-more-backdrop');
  const mobileMoreSheet = document.getElementById('mobile-more-sheet');

  function isMobileViewport() {
    return window.matchMedia('(max-width: 640px)').matches;
  }

  function fecharMaisMobile() {
    document.body.classList.remove('mobile-more-open');
    mobileMoreToggles.forEach((btn) => btn.setAttribute('aria-expanded', 'false'));
    if (mobileMoreSheet) mobileMoreSheet.setAttribute('aria-hidden', 'true');
  }

  function toggleMaisMobile() {
    const abrir = !document.body.classList.contains('mobile-more-open');
    document.body.classList.toggle('mobile-more-open', abrir);
    mobileMoreToggles.forEach((btn) => btn.setAttribute('aria-expanded', abrir ? 'true' : 'false'));
    if (mobileMoreSheet) mobileMoreSheet.setAttribute('aria-hidden', abrir ? 'false' : 'true');
  }

  function navigate(page) {
    frame.src = page;
    localStorage.setItem('bf_page', page);
    navItems.forEach(item => {
      item.classList.toggle('active', item.dataset.page === page);
    });

    if (isMobileViewport()) {
      fecharMaisMobile();
    }
  }

  // Em modo app instalado, sempre abre no dashboard.
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  const lastPage = isStandalone ? 'pages/dashboard.php' : (localStorage.getItem('bf_page') || 'pages/dashboard.php');
  navigate(lastPage);

  navItems.forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      navigate(item.dataset.page);
    });
  });

  mobileMoreToggles.forEach((btn) => btn.addEventListener('click', toggleMaisMobile));
  if (mobileMoreBackdrop) {
    mobileMoreBackdrop.addEventListener('click', fecharMaisMobile);
  }
  frame.addEventListener('load', () => {
    if (isMobileViewport()) {
      fecharMaisMobile();
    }
  });

  window.addEventListener('resize', () => {
    if (!isMobileViewport()) {
      fecharMaisMobile();
    }
  });

  // ── Logout ───────────────────────────────────────────────────
  function executarLogout() {
    BFApp.logoutAndRedirect();
  }

  document.getElementById('btn-logout').addEventListener('click', executarLogout);
  document.getElementById('btn-logout-top').addEventListener('click', executarLogout);
  const mobileLogout = document.getElementById('btn-logout-mobile');
  if (mobileLogout) {
    mobileLogout.addEventListener('click', executarLogout);
  }
</script>

</body>
</html>
