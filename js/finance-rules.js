/**
 * Regras compartilhadas para somar receitas/despesas sem distorção.
 *
 * Alguns lançamentos só movem dinheiro entre contas ou pagam dívida já contada
 * (pagamento de fatura, aplicações/resgates, transferências entre contas próprias).
 * Somá-los como receita/despesa infla os dois totais e duplica as compras do cartão.
 */
(() => {
  if (window.BFFinance) return;

  const CAT_INTERNA  = /pagamento de cart[aã]o|investiment|mesma titularidade|mesma institui[cç][aã]o/;
  const DESC_INTERNA = /pagamento de fatura|valor adicionado na conta por cart|aplica[cç][aã]o rdb|resgate rdb/;

  /** Retorna 'receita', 'despesa' ou 'interno' (não entra nos totais). */
  function classificar(tx) {
    // O servidor já classifica (inclui pagamento de fatura por PIX, que só ele consegue reconhecer).
    if (tx._classe === 'receita' || tx._classe === 'despesa' || tx._classe === 'interno') return tx._classe;

    const cat   = String(tx.category || '').toLowerCase();
    const desc  = String(tx.description || '').toLowerCase();
    const conta = String(tx.account_type || tx.accountType || '').toUpperCase();
    const tipo  = String(tx.type || (Number(tx.amount) >= 0 ? 'CREDIT' : 'DEBIT')).toUpperCase();

    // Crédito na conta do cartão = pagamento/ajuste de fatura, não é receita.
    if (conta === 'CREDIT' && tipo === 'CREDIT') return 'interno';
    if (CAT_INTERNA.test(cat) || DESC_INTERNA.test(desc)) return 'interno';
    // Dinheiro de empréstimo entrando não é renda; as parcelas (débito) continuam sendo despesa.
    if (/empr[eé]stimo/.test(cat) && tipo === 'CREDIT') return 'interno';

    return tipo === 'CREDIT' ? 'receita' : 'despesa';
  }

  function totais(txs) {
    let receitas = 0, despesas = 0, internos = 0;
    (txs || []).forEach((tx) => {
      const valor = Math.abs(Number(tx.amount) || 0);
      const c = classificar(tx);
      if (c === 'receita') receitas += valor;
      else if (c === 'despesa') despesas += valor;
      else internos += valor;
    });
    return { receitas, despesas, internos };
  }

  const FUSO = 'America/Sao_Paulo';

  /** Do mais recente para o mais antigo, pela data/hora real (não altera o array original). */
  function ordenarRecentes(txs) {
    const t = (x) => { const v = Date.parse(x.date); return Number.isNaN(v) ? 0 : v; };
    return (txs || []).slice().sort((a, b) => t(b) - t(a));
  }

  /** "03/10/2026" ou "03/10/2026 21:04" no horário de Brasília; a hora some quando o banco não informa (00:00). */
  function formatarDataHora(d) {
    if (!d) return '—';
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) {
      const m = String(d).match(/^(\d{4})-(\d{2})-(\d{2})/);
      return m ? `${m[3]}/${m[2]}/${m[1]}` : '—';
    }
    const dia = dt.toLocaleDateString('pt-BR', { timeZone: FUSO });
    const hora = dt.toLocaleTimeString('pt-BR', { timeZone: FUSO, hour: '2-digit', minute: '2-digit' });
    return hora === '00:00' ? dia : `${dia} ${hora}`;
  }

  window.BFFinance = { classificar, totais, ordenarRecentes, formatarDataHora };
})();
