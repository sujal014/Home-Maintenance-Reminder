/* Client-side checks for the login and register forms.
   The server repeats every check, so this only makes feedback faster. */
const authForm = document.querySelector('[data-auth-form]');

if (authForm) {
  const mode = authForm.dataset.authForm;           // "login" or "register"
  const field = (name) => authForm.elements[name];

  function setError(name, message) {
    const el = authForm.querySelector(`[data-error-for="${name}"]`);
    if (el) el.textContent = message;
  }

  function validate() {
    const errors = {};
    const email = field('email').value.trim();
    const password = field('password').value;

    if (mode === 'register') {
      const name = field('name').value.trim();
      if (name.length < 2) errors.name = 'Please enter your name (at least 2 characters).';
      if (password.length < 8) errors.password = 'Password must be at least 8 characters.';
      if (field('confirm').value !== password) errors.confirm = 'Passwords do not match.';
    } else if (!password) {
      errors.password = 'Please enter your password.';
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.email = 'Please enter a valid email address.';
    return errors;
  }

  authForm.addEventListener('submit', (e) => {
    const errors = validate();
    for (const name of ['name', 'email', 'password', 'confirm']) {
      if (field(name)) setError(name, errors[name] ?? '');
    }
    if (Object.keys(errors).length) e.preventDefault();   // stay on the page
  });

  document.getElementById('show-pw').addEventListener('change', (e) => {
    for (const name of ['password', 'confirm']) {
      if (field(name)) field(name).type = e.target.checked ? 'text' : 'password';
    }
  });
}
