(() => {
  'use strict';
  const root = document.documentElement;
  const themeButtons = [...document.querySelectorAll('[data-theme-choice]')];
  const validThemes = ['light', 'dark', 'green', 'rose'];
  const setTheme = (theme) => {
    if (!validThemes.includes(theme)) return;
    root.dataset.theme = theme;
    themeButtons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.themeChoice === theme)));
    try { localStorage.setItem('cm-theme', theme); } catch (_) { /* Storage may be disabled. */ }
  };
  let savedTheme = 'light';
  try { const value = localStorage.getItem('cm-theme'); if (validThemes.includes(value)) savedTheme = value; } catch (_) { /* Storage may be disabled. */ }
  setTheme(savedTheme);
  themeButtons.forEach((button) => button.addEventListener('click', () => setTheme(button.dataset.themeChoice)));

  const language = document.querySelector('#language-select');
  if (language) language.addEventListener('change', () => {
    const url = new URL(window.location.href);
    url.searchParams.set('lang', language.value);
    window.location.assign(url.toString());
  });

  const menuButton = document.querySelector('.mobile-menu-toggle');
  const sidebar = document.querySelector('.sidebar');
  if (menuButton && sidebar) {
    menuButton.addEventListener('click', () => {
      const open = sidebar.classList.toggle('is-open');
      menuButton.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', (event) => {
      if (!sidebar.contains(event.target) && !menuButton.contains(event.target)) {
        sidebar.classList.remove('is-open');
        menuButton.setAttribute('aria-expanded', 'false');
      }
    });
  }

  let deferredInstallPrompt = null;
  const installButton = document.querySelector('.install-button');
  const installStatus = document.querySelector('.install-status');
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    if (installButton) installButton.hidden = false;
  });
  if (installButton) installButton.addEventListener('click', async () => {
    if (!deferredInstallPrompt) {
      if (installStatus) installStatus.textContent = 'Use your browser menu to install this app when available.';
      return;
    }
    deferredInstallPrompt.prompt();
    await deferredInstallPrompt.userChoice;
    deferredInstallPrompt = null;
    installButton.hidden = true;
  });
  if ('serviceWorker' in navigator && location.protocol === 'https:') {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
  }
})();
