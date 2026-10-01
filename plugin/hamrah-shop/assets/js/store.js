(() => {
  'use strict';
  const config = window.HamrahShop;
  if (!config) return;
  document.documentElement.classList.add('hs-js');
  const words = config.words;
  let toastTimer;
  const toast = (message) => {
    let node = document.querySelector('[data-hs-toast]');
    if (!node) {
      node = document.createElement('div');
      node.className = 'hs-toast';
      node.setAttribute('role', 'status');
      node.setAttribute('aria-live', 'polite');
      node.dataset.hsToast = '';
      document.body.append(node);
    }
    clearTimeout(toastTimer);
    node.textContent = message;
    node.classList.add('is-visible');
    toastTimer = setTimeout(() => node.classList.remove('is-visible'), 6000);
  };
  const safeUrl = (value) => {
    try { const url = new URL(value, location.href); return ['http:', 'https:'].includes(url.protocol) ? url.href : ''; }
    catch { return ''; }
  };
  const endpoint = (path, params) => {
    const url = new URL(path, location.href);
    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
    return url;
  };
  const json = async (url, signal) => {
    const headers = { Accept: 'application/json' };
    if (config.restNonce) headers['X-WP-Nonce'] = config.restNonce;
    const response = await fetch(url, { signal, credentials: 'same-origin', headers });
    if (!response.ok) throw new Error('request');
    return response.json();
  };
  const node = (tag, className, text) => {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = text;
    return element;
  };
  const picture = (product) => {
    const url = product.image ? safeUrl(product.image) : '';
    if (!url) return node('span', 'hs-no-image', words.noImage);
    const img = node('img'); img.src = url; img.alt = product.name; img.loading = 'lazy'; img.width = 300; img.height = 300;
    return img;
  };
  const normalize = (value) => value.toLocaleLowerCase().replace(/[يى]/g, 'ی').replace(/ك/g, 'ک').replace(/\u200c/g, ' ').trim();

  // Each search form works without JavaScript; this only adds cancellable suggestions.
  if (config.liveSearch) document.querySelectorAll('[data-hs-search]').forEach((form) => {
    const input = form.querySelector('input[name="s"]');
    const box = form.querySelector('[data-hs-search-results]');
    if (!input || !box) return;
    box.setAttribute('aria-label', input.labels?.[0]?.textContent || words.view);
    let timer, controller;
    const hide = () => { box.hidden = true; input.setAttribute('aria-expanded', 'false'); };
    const show = (hasOptions = false) => { box.hidden = false; box.setAttribute('role', hasOptions ? 'listbox' : 'status'); input.setAttribute('aria-expanded', String(hasOptions)); };
    input.addEventListener('input', () => {
      clearTimeout(timer); controller?.abort();
      const query = input.value.trim();
      if (Array.from(query).length < 2) { hide(); return; }
      timer = setTimeout(async () => {
        controller = new AbortController();
        const current = controller;
        box.replaceChildren(node('p', 'hs-search-status', words.loading)); show();
        try {
          const data = await json(endpoint(config.searchUrl, { q: query }), current.signal);
          if (current.signal.aborted || input.value.trim() !== query) return;
          const products = Array.isArray(data.products) ? data.products : [];
          box.replaceChildren();
          if (!products.length) { box.append(node('p', 'hs-search-status', words.noResults)); return; }
          const list = node('ul', 'hs-search-list'); list.setAttribute('role', 'presentation');
          products.forEach((product) => {
            const url = safeUrl(product.url); if (!url) return;
            const item = node('li'); const link = node('a', 'hs-search-result'); link.href = url;
            item.setAttribute('role', 'presentation'); link.setAttribute('role', 'option'); link.setAttribute('aria-selected', 'false');
            link.addEventListener('focus', () => { box.querySelectorAll('[role=option]').forEach((option) => option.setAttribute('aria-selected', String(option === link))); });
            const text = node('span', 'hs-search-result-text');
            text.append(node('span', 'hs-search-result-name', product.name), node('span', 'hs-search-result-price', product.price));
            link.append(picture(product), text); item.append(link); list.append(item);
          });
          box.append(list); show(true);
        } catch (error) {
          if (error.name !== 'AbortError') box.replaceChildren(node('p', 'hs-search-status', words.error));
        }
      }, 300);
    });
    form.addEventListener('submit', () => { clearTimeout(timer); controller?.abort(); hide(); });
    form.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') { hide(); input.focus(); return; }
      if (box.hidden || !['ArrowDown', 'ArrowUp'].includes(event.key)) return;
      const links = [...box.querySelectorAll('a')]; if (!links.length) return;
      event.preventDefault();
      const at = links.indexOf(document.activeElement);
      if (event.key === 'ArrowDown') links[(at + 1) % links.length].focus();
      else if (at <= 0) input.focus(); else links[at - 1].focus();
    });
    form.addEventListener('focusout', () => setTimeout(() => { if (!form.contains(document.activeElement)) hide(); }, 100));
    document.addEventListener('click', (event) => { if (!form.contains(event.target)) hide(); });
  });

  const readWishlist = () => {
    try {
      const ids = JSON.parse(localStorage.getItem(config.wishlistKey) || '[]');
      return Array.isArray(ids) ? [...new Set(ids.filter((id) => Number.isSafeInteger(id) && id > 0))].slice(0, 100) : [];
    } catch { return []; }
  };
  const writeWishlist = (ids) => {
    try { localStorage.setItem(config.wishlistKey, JSON.stringify(ids)); return true; }
    catch { toast(words.storageError); return false; }
  };
  const syncWishlist = () => {
    const ids = readWishlist();
    document.querySelectorAll('[data-hs-wishlist]').forEach((button) => {
      const active = ids.includes(Number(button.dataset.hsWishlist));
      if (!button.dataset.hsBaseLabel) button.dataset.hsBaseLabel = button.getAttribute('aria-label') || words.view;
      button.setAttribute('aria-pressed', String(active));
      button.setAttribute('aria-label', active ? words.remove : button.dataset.hsBaseLabel);
      button.classList.toggle('is-active', active);
    });
  };
  let wishlistController;
  const renderWishlist = async () => {
    const page = document.querySelector('[data-hs-wishlist-page]'); if (!page) return;
    const grid = page.querySelector('[data-hs-wishlist-grid]');
    const status = page.querySelector('[data-hs-wishlist-status]');
    const ids = readWishlist(); wishlistController?.abort(); grid.replaceChildren();
    let clear = page.querySelector('[data-hs-wishlist-clear]');
    if (!clear) { clear = node('button', 'button hs-wishlist-clear', words.clearWishlist); clear.type = 'button'; clear.dataset.hsWishlistClear = ''; page.append(clear); }
    clear.hidden = !ids.length;
    if (!ids.length) { status.textContent = words.emptyWishlist; return; }
    status.textContent = words.loading;
    wishlistController = new AbortController(); const current = wishlistController;
    try {
      const data = await json(endpoint(config.productsUrl, { ids: ids.join(',') }), current.signal);
      if (current.signal.aborted) return;
      const products = Array.isArray(data.products) ? data.products : [];
      products.forEach((product) => {
        const url = safeUrl(product.url); if (!url) return;
        const card = node('article', 'hs-wishlist-card');
        const imageLink = node('a', 'hs-wishlist-image'); imageLink.href = url; imageLink.append(picture(product));
        const heading = node('h2'); const titleLink = node('a', '', product.name); titleLink.href = url; heading.append(titleLink);
        const price = node('p', 'hs-wishlist-price', product.price);
        const stock = node('p', 'hs-wishlist-stock', product.stock_label);
        const actions = node('div', 'hs-wishlist-actions');
        const view = node('a', 'hs-button', words.view); view.href = url;
        const remove = node('button', 'hs-wishlist-remove', words.remove); remove.type = 'button'; remove.dataset.hsWishlist = String(product.id); remove.setAttribute('aria-label', `${words.remove}: ${product.name}`);
        actions.append(view, remove); card.append(imageLink, heading, price, stock, actions); grid.append(card);
      });
      status.textContent = products.length ? '' : words.unavailableWishlist;
      syncWishlist();
    } catch (error) {
      if (error.name !== 'AbortError') {
        status.textContent = words.error;
        const retry = node('button', 'button', words.retry); retry.type = 'button'; retry.addEventListener('click', renderWishlist); status.append(' ', retry);
      }
    }
  };
  if (config.wishlist) {
    syncWishlist(); renderWishlist();
    document.addEventListener('click', (event) => {
      const clear = event.target.closest('[data-hs-wishlist-clear]');
      if (clear) { if (writeWishlist([])) { syncWishlist(); renderWishlist(); } return; }
      const button = event.target.closest('[data-hs-wishlist]'); if (!button) return;
      event.preventDefault(); event.stopPropagation();
      const id = Number(button.dataset.hsWishlist); if (!Number.isSafeInteger(id) || id <= 0) return;
      const ids = readWishlist(); const exists = ids.includes(id);
      if (!exists && ids.length >= 100) { toast(words.limit); return; }
      const next = exists ? ids.filter((value) => value !== id) : [...ids, id];
      if (writeWishlist(next)) { syncWishlist(); renderWishlist(); toast(exists ? words.removed : words.added); }
    });
    window.addEventListener('storage', (event) => { if (event.key === config.wishlistKey) { syncWishlist(); renderWishlist(); } });
  }

  // Bounded facet lists: local matching first, real taxonomy lookup only when needed.
  const initFacets = (root = document) => root.querySelectorAll('[data-hs-facet]').forEach((facet) => {
    if (facet.dataset.hsReady) return; facet.dataset.hsReady = '1';
    const input = facet.querySelector('[data-hs-term-search]'); if (!input) return;
    const options = facet.querySelector('[data-hs-term-options]');
    const status = facet.querySelector('[data-hs-term-status]');
    let timer, controller;
    const filter = (query, allowed) => {
      let visible = 0;
      options.querySelectorAll('.hs-check').forEach((label) => {
        const checkbox = label.querySelector('input');
        const matched = allowed ? allowed.has(checkbox.value) : normalize(label.textContent).includes(query);
        label.hidden = !matched && !checkbox.checked;
        if (matched) visible += 1;
      });
      return visible;
    };
    input.addEventListener('input', () => {
      clearTimeout(timer); controller?.abort();
      const query = normalize(input.value); const count = filter(query);
      status.textContent = count ? '' : words.noOptions;
      if (facet.dataset.more !== '1' || Array.from(query).length < 2) {
        if (!query && facet.dataset.more === '1') status.textContent = words.moreOptions;
        return;
      }
      timer = setTimeout(async () => {
        controller = new AbortController(); const current = controller;
        status.textContent = words.loading;
        try {
          const data = await json(endpoint(config.termsUrl, { taxonomy: facet.dataset.taxonomy, q: input.value.trim() }), current.signal);
          if (current.signal.aborted) return;
          const allowed = new Set();
          (Array.isArray(data.terms) ? data.terms : []).forEach((term) => {
            allowed.add(String(term.value));
            const found = [...options.querySelectorAll('input')].some((checkbox) => checkbox.value === String(term.value));
            if (found) return;
            const label = node('label', 'hs-check'); const checkbox = node('input'); checkbox.type = 'checkbox'; checkbox.name = facet.dataset.inputName; checkbox.value = term.value;
            label.dataset.hsRemote = '1'; label.append(checkbox, node('span', '', term.label)); options.append(label);
          });
          filter(query, allowed);
          // Keep selected options, bound additional unselected DOM entries.
          const removable = [...options.querySelectorAll('[data-hs-remote]')].filter((label) => !label.querySelector('input').checked && label.hidden);
          while (options.children.length > 200 && removable.length) removable.shift().remove();
          status.textContent = !allowed.size ? words.noOptions : data.more ? words.moreOptions : '';
        } catch (error) { if (error.name !== 'AbortError') status.textContent = words.error; }
      }, 300);
    });
  });
  initFacets();

  const filterUrl = (form) => {
    const url = new URL(form.action, location.href); const values = new Map();
    for (const [rawKey, rawValue] of new FormData(form)) {
      let key = rawKey.replace(/\[\]$/, ''); const value = String(rawValue).trim(); if (!value) continue;
      if (key.startsWith('hs_attr_')) { key = `filter_${key.slice(8)}`; url.searchParams.set(`query_type_${key.slice(7)}`, 'or'); }
      if (!values.has(key)) values.set(key, []); values.get(key).push(value);
    }
    for (const [key, list] of values) url.searchParams.set(key, [...new Set(list)].join(','));
    return url;
  };
  let catalogController;
  const loadCatalog = async (target, options = {}) => {
    const url = new URL(target, location.href);
    if (url.origin !== location.origin) { location.assign(url.href); return; }
    window.HamrahNavigation?.closeFilters();
    const shell = document.querySelector('[data-hs-shop-shell]'); if (!shell) { location.assign(url.href); return; }
    catalogController?.abort(); catalogController = new AbortController(); const current = catalogController;
    const active = document.activeElement;
    const focused = active?.matches('.hs-filter-form input') ? { name: active.name, value: active.value } : null;
    shell.setAttribute('aria-busy', 'true'); shell.classList.add('is-loading');
    const status = document.querySelector('[data-hs-shop-status]'); if (status) status.textContent = words.loading;
    try {
      const response = await fetch(url, { signal: current.signal, credentials: 'same-origin', headers: { Accept: 'text/html', 'X-Hamrah-Catalog': '1' } });
      if (!response.ok) throw new Error('catalog');
      const html = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (current.signal.aborted) return;
      const replacement = html.querySelector('[data-hs-shop-shell]');
      if (!replacement) { location.assign(url.href); return; }
      shell.replaceWith(replacement); document.title = html.title;
      if (options.history !== false) history.pushState({ hamrahCatalog: true }, '', url);
      initFacets(replacement); syncWishlist();
      if (status) status.textContent = words.updated;
      const results = replacement.querySelector('[data-hs-catalog-results]');
      if (options.scroll !== false) results?.scrollIntoView({ block: 'start', behavior: 'instant' });
      let focus = focused && options.scroll === false ? [...replacement.querySelectorAll('.hs-filter-form input')].find((input) => input.name === focused.name && input.value === focused.value) : null;
      if (!focus && options.scroll !== false) { focus = results?.querySelector('h1') || results; if (focus) focus.setAttribute('tabindex', '-1'); }
      focus?.focus({ preventScroll: true });
      if (window.jQuery) window.jQuery(document.body).trigger('hamrah_catalog_updated');
    } catch (error) {
      if (error.name !== 'AbortError') { toast(words.error); if (status) status.textContent = words.error; }
    } finally {
      if (catalogController === current) {
        document.querySelector('[data-hs-shop-shell]')?.removeAttribute('aria-busy');
        document.querySelector('[data-hs-shop-shell]')?.classList.remove('is-loading');
      }
    }
  };
  const sortUrl = (form) => {
    const url = new URL(location.href); url.searchParams.set('orderby', new FormData(form).get('orderby') || 'menu_order');
    url.searchParams.delete('paged'); url.searchParams.delete('product-page');
    // WooCommerce can put pagination in a pretty URL rather than a query string.
    url.pathname = url.pathname.replace(/\/page\/\d+\/?$/, '/'); return url;
  };
  document.addEventListener('submit', (event) => {
    if (!document.querySelector('[data-hs-shop-shell]')) return;
    if (event.target.matches('.hs-filter-form')) { event.preventDefault(); loadCatalog(filterUrl(event.target)); }
    else if (event.target.matches('.woocommerce-ordering')) { event.preventDefault(); loadCatalog(sortUrl(event.target), { scroll: false }); }
  }, true);
  let filterTimer;
  document.addEventListener('change', (event) => {
    if (!document.querySelector('[data-hs-shop-shell]')) return;
    if (event.target.matches('.woocommerce-ordering select')) {
      event.preventDefault(); event.stopImmediatePropagation(); loadCatalog(sortUrl(event.target.form), { scroll: false }); return;
    }
    if (event.target.matches('.hs-filter-form input[type="checkbox"]') && matchMedia('(min-width: 901px)').matches) {
      clearTimeout(filterTimer); const form = event.target.form;
      filterTimer = setTimeout(() => loadCatalog(filterUrl(form), { scroll: false }), 350);
    }
  }, true);
  document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const link = event.target.closest('[data-hs-catalog-link], .woocommerce-pagination a.page-numbers');
    if (link && document.querySelector('[data-hs-shop-shell]')) { event.preventDefault(); loadCatalog(link.href); }
  });
  window.addEventListener('popstate', () => { if (document.querySelector('[data-hs-shop-shell]')) loadCatalog(location.href, { history: false, scroll: false }); });
})();
