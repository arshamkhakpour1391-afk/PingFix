(() => {
  'use strict';
  document.documentElement.classList.add('hs-js');
  let filterAside = null;
  let filterMarker = null;
  const restoreFilters = () => {
    if (filterAside && filterMarker?.isConnected) filterMarker.replaceWith(filterAside);
    filterAside = null; filterMarker = null;
  };
  const closeFilters = () => {
    restoreFilters();
    const dialog = document.getElementById('hs-filter-dialog');
    if (dialog?.open) dialog.close();
  };
  window.HamrahNavigation = { closeFilters };
  const open = (dialog) => {
    if (!dialog) return;
    document.querySelectorAll('dialog[open]').forEach((other) => { if (other !== dialog) other.close(); });
    if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
    document.body.classList.add('hs-dialog-open');
  };
  document.querySelectorAll('.hs-dialog').forEach((dialog) => {
    dialog.addEventListener('close', () => {
      if (dialog.id === 'hs-filter-dialog') restoreFilters();
      if (!document.querySelector('dialog[open]')) document.body.classList.remove('hs-dialog-open');
    });
    dialog.addEventListener('click', (event) => {
      const rect = dialog.getBoundingClientRect();
      if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
  });
  document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-hs-dialog-open]');
    if (opener) { open(document.getElementById(opener.dataset.hsDialogOpen)); return; }
    const closer = event.target.closest('[data-hs-dialog-close]');
    if (closer) { closer.closest('dialog')?.close(); return; }
    if (event.target.closest('[data-hs-open-filters]')) {
      const aside = document.querySelector('[data-hs-shop-shell] .hs-filter-aside');
      const dialog = document.getElementById('hs-filter-dialog');
      const host = dialog?.querySelector('[data-hs-filter-host]');
      if (!aside || !host) return;
      filterAside = aside; filterMarker = document.createElement('span'); filterMarker.hidden = true;
      aside.before(filterMarker); host.append(aside); open(dialog); return;
    }
    const search = event.target.closest('[data-hs-focus-search]');
    if (search) {
      const input = document.getElementById('hs-search-header');
      if (input) { event.preventDefault(); input.scrollIntoView({ block: 'center' }); input.focus({ preventScroll: true }); }
    }
  });
  matchMedia('(min-width: 901px)').addEventListener('change', (event) => { if (event.matches) { closeFilters(); document.getElementById('hs-mobile-menu')?.close(); } });
})();
