document.addEventListener('DOMContentLoaded', () => {
  const layout = document.getElementById('appLayout');
  const openMenu = document.getElementById('openMenu');
  const closeMenu = document.getElementById('closeMenu');
  const backdrop = document.getElementById('sidebarBackdrop');

  if (layout && openMenu) {
    openMenu.addEventListener('click', () => layout.classList.add('menu-open'));
  }
  if (layout && closeMenu) {
    closeMenu.addEventListener('click', () => layout.classList.remove('menu-open'));
  }
  if (layout && backdrop) {
    backdrop.addEventListener('click', () => layout.classList.remove('menu-open'));
  }

  document.querySelectorAll('.pw-toggle').forEach((btn) => {
    btn.addEventListener('click', () => {
      const wrap = btn.closest('.pw-wrap');
      const input = wrap ? wrap.querySelector('input') : null;
      if (!input) return;
      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      btn.textContent = isHidden ? 'Sembunyi' : 'Lihat';
    });
  });

  document.querySelectorAll('.alert-close').forEach((btn) => {
    btn.addEventListener('click', () => {
      const alertBox = btn.closest('.alert');
      if (alertBox) alertBox.remove();
    });
  });
});
