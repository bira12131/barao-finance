<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Cadastro de Categorias</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <script src="../../js/app-runtime.js"></script>
  <style>
    .cadastro-box {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 16px;
      box-shadow: var(--shadow-card);
      padding: 1rem;
    }
    .row {
      display: flex;
      gap: 0.55rem;
      flex-wrap: wrap;
      margin-bottom: 0.7rem;
    }
    .input {
      height: 40px;
      min-width: 260px;
      padding: 0 0.85rem;
      border: 1px solid #bcc8db;
      border-radius: 10px;
      background: #f7f9fd;
      color: var(--text-body);
      font-size: 0.84rem;
      outline: none;
      transition: border-color 0.15s, box-shadow 0.15s;
    }
    .input:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(59,111,212,0.14);
    }
    .btn {
      height: 40px;
      padding: 0 1.1rem;
      border-radius: 10px;
      border: 1px solid #245ec6;
      background: linear-gradient(135deg, #2e69cf 0%, #3b6fd4 100%);
      color: #fff;
      font-weight: 600;
      font-size: 0.82rem;
      cursor: pointer;
      box-shadow: 0 10px 18px rgba(59,111,212,0.24);
      transition: transform 0.14s, opacity 0.14s;
    }
    .btn:hover {
      opacity: 0.95;
      transform: translateY(-1px);
    }
    .msg {
      font-size: 0.8rem;
      color: var(--text-muted);
      margin-bottom: 0.5rem;
    }
  </style>
</head>
<body class="inner-page">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Cadastro de categorias</h1>
      <p>Gerencie suas categorias personalizadas</p>
    </div>
  </div>

  <div class="cadastro-box" style="margin-bottom:1rem;">
    <div class="row">
      <input id="categoria-nome" class="input" type="text" placeholder="Ex.: Mercado, Uber, Farmácia" />
      <button class="btn" type="button" onclick="salvarCategoria()">+ Cadastrar</button>
    </div>
    <div class="msg" id="msg">Cadastre uma categoria por vez para manter seu padrão organizado.</div>
  </div>

  <script>
    function _apiBase() {
      const { origin, pathname } = window.location;
      const root = pathname
        .replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '')
        .replace(/\/[^/]*\.(?:html|php)$/, '');
      return origin + root + '/backend/api';
    }

    async function salvarCategoria() {
      const input = document.getElementById('categoria-nome');
      const nome = (input.value || '').trim();
      if (!nome) return;

      try {
        const res = await fetch(_apiBase() + '/pierre/categorias.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ acao: 'criar', categoria: nome }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Erro');

        input.value = '';
        document.getElementById('msg').textContent = 'Categoria cadastrada com sucesso.';
      } catch (_) {
        document.getElementById('msg').textContent = 'Não foi possível cadastrar a categoria.';
      }
    }
  </script>

</body>
</html>
