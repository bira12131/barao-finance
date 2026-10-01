<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
  <title>Cadastro de Despesa Prevista</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/app.css" />
  <script src="../../js/app-runtime.js"></script>
  <style>
    .box {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 14px;
      box-shadow: var(--shadow-card);
      padding: 1rem;
      max-width: 620px;
    }
    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.75rem;
    }
    .field { display: flex; flex-direction: column; gap: 0.28rem; }
    .field.full { grid-column: 1 / -1; }
    .label {
      font-size: 0.78rem;
      color: var(--text-muted);
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .input {
      height: 40px;
      padding: 0 0.8rem;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      background: var(--bg-input);
      color: var(--text-body);
      font-size: 0.84rem;
      outline: none;
    }
    .input:focus { border-color: var(--primary); }
    .actions { margin-top: 0.85rem; display: flex; gap: 0.55rem; }
    .btn {
      height: 38px;
      padding: 0 1rem;
      border-radius: var(--radius-sm);
      border: 1.5px solid var(--primary);
      background: var(--primary);
      color: #fff;
      font-size: 0.82rem;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
    }
    .btn.outline {
      background: var(--bg-input);
      color: var(--text-body);
      border-color: var(--border);
    }
    .msg {
      font-size: 0.82rem;
      color: var(--text-muted);
      margin-top: 0.55rem;
      min-height: 1.1rem;
    }
    @media (max-width: 760px) {
      .grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body class="inner-page">

  <div class="page-header">
    <div class="page-header-left">
      <h1>Cadastrar despesa prevista</h1>
      <p>Informe descrição, primeira cobrança e por quantos meses a despesa irá durar</p>
    </div>
  </div>

  <div class="box">
    <div class="grid">
      <div class="field full">
        <label class="label" for="descricao">Descrição</label>
        <input class="input" id="descricao" type="text" placeholder="Ex.: GitHub, Netflix, Aluguel" />
      </div>

      <div class="field">
        <label class="label" for="primeira">Mês da primeira cobrança</label>
        <input class="input" id="primeira" type="month" />
      </div>

      <div class="field">
        <label class="label" for="duracao">Encerrar em quantos meses</label>
        <input class="input" id="duracao" type="number" min="1" step="1" placeholder="Ex.: 12" />
      </div>

      <div class="field full">
        <label class="label" for="valor">Valor da parcela</label>
        <input class="input" id="valor" type="text" inputmode="numeric" placeholder="R$ 0,00" />
      </div>

    </div>

    <div class="actions">
      <button class="btn" onclick="salvar()">Salvar</button>
      <a class="btn outline" href="despesas-previstas.php">Voltar</a>
    </div>

    <div id="msg" class="msg"></div>
  </div>

  <script>
    function _apiBase() {
      const { origin, pathname } = window.location;
      const root = pathname
        .replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '')
        .replace(/\/[^/]*\.(?:html|php)$/, '');
      return origin + root + '/backend/api';
    }

    async function salvar() {
      const descricao = document.getElementById('descricao').value.trim();
      const primeira = document.getElementById('primeira').value;
      const duracao = Number(document.getElementById('duracao').value || 0);
      const valorParcela = Number(document.getElementById('valor').dataset.value || 0);
      const msg = document.getElementById('msg');

      if (!descricao || !primeira || duracao <= 0 || valorParcela <= 0) {
        msg.textContent = 'Preencha descrição, mês da primeira cobrança, duração e valor da parcela.';
        msg.style.color = 'var(--danger)';
        return;
      }

      try {
        const res = await fetch(_apiBase() + '/pierre/despesas_previstas.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            descricao,
            primeira_cobranca_mes: primeira,
            duracao_meses: duracao,
            valor_parcela: valorParcela,
          }),
        });

        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Erro ao salvar');

        msg.textContent = 'Despesa prevista cadastrada com sucesso.';
        msg.style.color = '#16a34a';
        document.getElementById('descricao').value = '';
        document.getElementById('duracao').value = '';
        document.getElementById('valor').value = '';
        document.getElementById('valor').dataset.value = '';
      } catch (e) {
        msg.textContent = 'Não foi possível salvar a despesa prevista.';
        msg.style.color = 'var(--danger)';
      }
    }

    function bindCurrencyInput(id) {
      const el = document.getElementById(id);
      if (!el) return;

      el.dataset.value = '';
      el.addEventListener('input', () => {
        const digits = el.value.replace(/\D/g, '');
        if (!digits) {
          el.value = '';
          el.dataset.value = '';
          return;
        }

        const amount = Number(digits) / 100;
        el.dataset.value = amount.toFixed(2);
        el.value = amount.toLocaleString('pt-BR', {
          style: 'currency',
          currency: 'BRL',
        });
      });
    }

    bindCurrencyInput('valor');
  </script>

</body>
</html>
