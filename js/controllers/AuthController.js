/**
 * CONTROLLER — AuthController
 *
 * Gerencia toda a lógica de interação do usuário nas telas
 * de Login e Cadastro. Conecta a View (HTML) ao Model (User.js).
 */

const AuthController = (() => {

  /* ════════════════════════════════════════════
     Utilitários de View
  ════════════════════════════════════════════ */

  function _setFieldError(inputEl, errorEl, message) {
    if (message) {
      inputEl.classList.add('error');
      inputEl.classList.remove('success');
      errorEl.textContent = message;
      errorEl.classList.add('visible');
    } else {
      inputEl.classList.remove('error');
      inputEl.classList.add('success');
      errorEl.textContent = '';
      errorEl.classList.remove('visible');
    }
  }

  function _clearFieldError(inputEl, errorEl) {
    inputEl.classList.remove('error', 'success');
    errorEl.textContent = '';
    errorEl.classList.remove('visible');
  }

  function _showAlert(alertEl, message, type = 'error') {
    alertEl.className = `alert alert-${type} visible`;
    alertEl.querySelector('.alert-message').textContent = message;
  }

  function _hideAlert(alertEl) {
    alertEl.classList.remove('visible');
  }

  function _setButtonLoading(btn, loading) {
    if (loading) {
      btn.classList.add('loading');
      btn.disabled = true;
    } else {
      btn.classList.remove('loading');
      btn.disabled = false;
    }
  }

  /* ════════════════════════════════════════════
     Inicialização: Tela de Login
  ════════════════════════════════════════════ */

  function initLogin() {
    // Redireciona se já há sessão ativa
    if (UserModel.getSession()) {
      window.location.href = 'views/home.php';
      return;
    }

    const form      = document.getElementById('login-form');
    const emailIn   = document.getElementById('email');
    const passIn    = document.getElementById('password');
    const emailErr  = document.getElementById('email-error');
    const passErr   = document.getElementById('password-error');
    const alertEl   = document.getElementById('login-alert');
    const submitBtn = document.getElementById('btn-login');
    const toggleBtn = document.getElementById('toggle-password');

    if (!form) return;

    // Mostrar/ocultar senha
    toggleBtn.addEventListener('click', () => {
      const isText = passIn.type === 'text';
      passIn.type = isText ? 'password' : 'text';
      toggleBtn.setAttribute('aria-label', isText ? 'Mostrar senha' : 'Ocultar senha');
      toggleBtn.innerHTML = isText ? _eyeIcon() : _eyeOffIcon();
    });

    // Limpa erro ao digitar
    emailIn.addEventListener('input', () => _clearFieldError(emailIn, emailErr));
    passIn.addEventListener('input',  () => _clearFieldError(passIn,  passErr));

    // Submit
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      _hideAlert(alertEl);

      const data = {
        email:    emailIn.value,
        password: passIn.value,
      };

      // Validação client-side
      const errors = UserModel.validateLogin(data);
      let hasErr = false;

      _setFieldError(emailIn, emailErr, errors.email);
      _setFieldError(passIn,  passErr,  errors.password);

      if (UserModel.hasErrors(errors)) return;

      _setButtonLoading(submitBtn, true);

      const result = await UserModel.login(data);

      _setButtonLoading(submitBtn, false);

      if (!result.success) {
        _showAlert(alertEl, result.errors.general || 'Erro ao fazer login.');
        return;
      }

      // Login bem-sucedido → redireciona para home
      window.location.href = 'views/home.php';
    });
  }

  /* ════════════════════════════════════════════
     Inicialização: Tela de Cadastro
  ════════════════════════════════════════════ */

  function initRegister() {
    const form           = document.getElementById('register-form');
    const nameIn         = document.getElementById('name');
    const emailIn        = document.getElementById('email');
    const passIn         = document.getElementById('password');
    const confirmIn      = document.getElementById('confirm-password');
    const nameErr        = document.getElementById('name-error');
    const emailErr       = document.getElementById('email-error');
    const passErr        = document.getElementById('password-error');
    const confirmErr     = document.getElementById('confirm-password-error');
    const alertEl        = document.getElementById('register-alert');
    const submitBtn      = document.getElementById('btn-register');
    const toggleBtn      = document.getElementById('toggle-password');
    const toggleConfirm  = document.getElementById('toggle-confirm-password');
    const strengthFill   = document.getElementById('strength-fill');
    const strengthLabel  = document.getElementById('strength-label');

    if (!form) return;

    // Mostrar/ocultar senha
    toggleBtn.addEventListener('click', () => {
      const isText = passIn.type === 'text';
      passIn.type = isText ? 'password' : 'text';
      toggleBtn.setAttribute('aria-label', isText ? 'Mostrar senha' : 'Ocultar senha');
      toggleBtn.innerHTML = isText ? _eyeIcon() : _eyeOffIcon();
    });

    // Mostrar/ocultar confirmar senha
    toggleConfirm.addEventListener('click', () => {
      const isText = confirmIn.type === 'text';
      confirmIn.type = isText ? 'password' : 'text';
      toggleConfirm.setAttribute('aria-label', isText ? 'Mostrar confirmação' : 'Ocultar confirmação');
      toggleConfirm.innerHTML = isText ? _eyeIcon() : _eyeOffIcon();
    });

    // Limpa erros ao digitar
    nameIn.addEventListener('input',    () => _clearFieldError(nameIn,    nameErr));
    emailIn.addEventListener('input',   () => _clearFieldError(emailIn,   emailErr));
    confirmIn.addEventListener('input', () => {
      _clearFieldError(confirmIn, confirmErr);
      // Validação em tempo real de coincidência
      if (confirmIn.value && passIn.value && confirmIn.value !== passIn.value) {
        _setFieldError(confirmIn, confirmErr, 'As senhas não coincidem.');
      } else if (confirmIn.value && confirmIn.value === passIn.value) {
        confirmIn.classList.remove('error');
        confirmIn.classList.add('valid');
        confirmErr.classList.remove('visible');
      }
    });

    // Força da senha em tempo real
    passIn.addEventListener('input', () => {
      _clearFieldError(passIn, passErr);
      const strength = UserModel.passwordStrength(passIn.value);
      strengthFill.style.width      = `${(strength.score / 5) * 100}%`;
      strengthFill.style.background = strength.color;
      strengthLabel.textContent     = strength.label;
      // Re-valida confirmação se já preenchida
      if (confirmIn.value) {
        if (confirmIn.value !== passIn.value) {
          _setFieldError(confirmIn, confirmErr, 'As senhas não coincidem.');
        } else {
          _clearFieldError(confirmIn, confirmErr);
          confirmIn.classList.add('valid');
        }
      }
    });

    // Submit
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      _hideAlert(alertEl);

      const data = {
        name:     nameIn.value,
        email:    emailIn.value,
        password: passIn.value,
        invite_code: (document.getElementById('invite-code')?.value || '').trim(),
      };

      const errors = UserModel.validateRegister(data);
      _setFieldError(nameIn,  nameErr,  errors.name);
      _setFieldError(emailIn, emailErr, errors.email);
      _setFieldError(passIn,  passErr,  errors.password);

      // Validação da confirmação de senha
      let confirmError = null;
      if (!confirmIn.value) {
        confirmError = 'Confirme sua senha.';
      } else if (confirmIn.value !== passIn.value) {
        confirmError = 'As senhas não coincidem.';
      }
      _setFieldError(confirmIn, confirmErr, confirmError);

      if (UserModel.hasErrors(errors) || confirmError) return;

      _setButtonLoading(submitBtn, true);

      const result = await UserModel.register(data);

      _setButtonLoading(submitBtn, false);

      if (!result.success) {
        if (result.errors.email) {
          _setFieldError(emailIn, emailErr, result.errors.email);
        } else if (result.errors.invite_code) {
          _showAlert(alertEl, result.errors.invite_code);
        } else {
          _showAlert(alertEl, 'Erro ao criar conta. Tente novamente.');
        }
        return;
      }

      _showAlert(alertEl, 'Conta criada com sucesso! Redirecionando...', 'success');
      await _delay(1500);
      window.location.href = '../index.html';
    });
  }

  /* ════════════════════════════════════════════
     Ícones SVG inline
  ════════════════════════════════════════════ */

  function _eyeIcon() {
    return `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
      fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
      <circle cx="12" cy="12" r="3"/>
    </svg>`;
  }

  function _eyeOffIcon() {
    return `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
      fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
      <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
      <line x1="1" y1="1" x2="23" y2="23"/>
    </svg>`;
  }

  /* ════════════════════════════════════════════
     Utilitário
  ════════════════════════════════════════════ */

  function _delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  /* ── API pública ── */
  return { initLogin, initRegister };

})();
