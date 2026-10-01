/**
 * MODEL — User
 *
 * Responsável pela estrutura de dados do usuário,
 * validação client-side e comunicação com a API PHP.
 */

const UserModel = (() => {

  const SESSION_KEY = 'baraofinance_session';

  /* ── Detecta automaticamente a URL base do backend ── */
  const _apiBase = (() => {
    const { origin, pathname } = window.location;
    const root = pathname
      .replace(/\/views(?:\/pages)?\/[^/]*\.(?:html|php)$/, '')
      .replace(/\/[^/]*\.(?:html|php)$/, '');
    return origin + root + '/backend/api';
  })();

  /* ════════════════════════════════════════════
     Validações client-side
  ════════════════════════════════════════════ */

  function validateName(name) {
    if (!name || name.trim().length < 2) return 'Nome deve ter ao menos 2 caracteres.';
    if (name.trim().length > 100)        return 'Nome muito longo (máx. 100 caracteres).';
    return null;
  }

  function validateEmail(email) {
    if (!email || !email.trim()) return 'E-mail é obrigatório.';
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!re.test(email.trim())) return 'Informe um e-mail válido.';
    return null;
  }

  function validatePassword(password) {
    if (!password)           return 'Senha é obrigatória.';
    if (password.length < 6) return 'A senha deve ter ao menos 6 caracteres.';
    return null;
  }

  function validateRegister({ name, email, password }) {
    return {
      name:     validateName(name),
      email:    validateEmail(email),
      password: validatePassword(password),
    };
  }

  function validateLogin({ email, password }) {
    return {
      email:    validateEmail(email),
      password: password ? null : 'Senha é obrigatória.',
    };
  }

  function hasErrors(errors) {
    return Object.values(errors).some(e => e !== null);
  }

  /* ════════════════════════════════════════════
     Comunicação com a API
  ════════════════════════════════════════════ */

  async function register({ name, email, password }) {
    // Validação antes de chamar a API
    const errors = validateRegister({ name, email, password });
    if (hasErrors(errors)) return { success: false, errors };

    try {
      const res  = await fetch(`${_apiBase}/register.php`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ name, email, password }),
      });

      const json = await res.json();

      if (!res.ok) {
        return {
          success: false,
          errors: json.data?.errors || { general: json.message },
        };
      }

      return { success: true, user: json.data.user };

    } catch {
      return {
        success: false,
        errors: { general: 'Erro de conexão. Verifique sua internet e tente novamente.' },
      };
    }
  }

  async function login({ email, password }) {
    const errors = validateLogin({ email, password });
    if (hasErrors(errors)) return { success: false, errors };

    try {
      const res  = await fetch(`${_apiBase}/login.php`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ email, password }),
      });

      const json = await res.json();

      if (!res.ok) {
        return {
          success: false,
          errors: json.data?.errors || { general: json.message },
        };
      }

      // Normaliza chaves (backend retorna 'nome', frontend usa 'name')
      const userData = {
        id:    json.data.user.id,
        name:  json.data.user.nome  || json.data.user.name,
        email: json.data.user.email,
      };
      // Persiste sessão no navegador
      sessionStorage.setItem(SESSION_KEY, JSON.stringify(userData));
      return { success: true, user: userData };

    } catch {
      return {
        success: false,
        errors: { general: 'Erro de conexão. Verifique sua internet e tente novamente.' },
      };
    }
  }

  function logout() {
    sessionStorage.removeItem(SESSION_KEY);
  }

  function getSession() {
    try {
      return JSON.parse(sessionStorage.getItem(SESSION_KEY)) || null;
    } catch {
      return null;
    }
  }

  /* ════════════════════════════════════════════
     Utilitário de UI — força da senha
  ════════════════════════════════════════════ */

  function passwordStrength(password) {
    if (!password) return { score: 0, label: '', color: '#e2e8f0' };
    let score = 0;
    if (password.length >= 6)               score++;
    if (password.length >= 10)              score++;
    if (/[A-Z]/.test(password))             score++;
    if (/[0-9]/.test(password))             score++;
    if (/[^A-Za-z0-9]/.test(password))     score++;

    const levels = [
      { label: '',          color: '#c5cfde' },
      { label: 'Fraca',     color: '#d94f4f' },
      { label: 'Razoável',  color: '#f97316' },
      { label: 'Boa',       color: '#eab308' },
      { label: 'Forte',     color: '#2aa566' },
      { label: 'Excelente', color: '#16a34a' },
    ];

    return { score, ...levels[score] };
  }

  /* ── API pública do Model ── */
  return {
    register,
    login,
    logout,
    getSession,
    validateRegister,
    validateLogin,
    validateName,
    validateEmail,
    validatePassword,
    hasErrors,
    passwordStrength,
  };

})();
