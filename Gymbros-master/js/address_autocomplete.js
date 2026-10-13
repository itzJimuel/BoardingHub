/**
 * Philippine address autocomplete for the registration form.
 * Province -> City/Municipality -> Barangay suggestions from the PSGC API
 * (https://psgc.gitlab.io/api). Fields stay free-text, so typing manually
 * still works when the API is unreachable.
 */
(function () {
  const API = 'https://psgc.gitlab.io/api';
  const NCR_CODE = '130000000';
  const CACHE_PREFIX = 'bh_psgc_v1_';
  const MAX_ITEMS = 200;

  const state = {
    provinceCode: null,
    cityCode: null,
    provinces: null,
    cities: null,
    barangays: {}
  };

  // ---------- helpers ----------
  const normalize = (s) => String(s || '')
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .toLowerCase().replace(/\s+/g, ' ').trim();

  // Server-side validation rejects parentheses, e.g. "Barangay 1 (Pob.)"
  const cleanName = (s) => String(s || '')
    .replace(/\s*\(([^)]*)\)/g, ' - $1')
    .replace(/[<>{}[\]$%^&*+|~=`]/g, '')
    .replace(/\s+/g, ' ').trim();

  const escapeHtml = (s) => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function readCache(key) {
    try {
      const raw = localStorage.getItem(CACHE_PREFIX + key);
      return raw ? JSON.parse(raw) : null;
    } catch (e) { return null; }
  }

  function writeCache(key, data) {
    try { localStorage.setItem(CACHE_PREFIX + key, JSON.stringify(data)); } catch (e) { /* storage full or blocked */ }
  }

  async function fetchJson(path, cacheKey) {
    const cached = cacheKey && readCache(cacheKey);
    if (cached) return cached;
    const res = await fetch(API + path);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    if (cacheKey) writeCache(cacheKey, data);
    return data;
  }

  async function loadProvinces() {
    if (state.provinces) return state.provinces;
    const data = await fetchJson('/provinces/', 'provinces');
    const list = data.map(p => ({ code: p.code, name: cleanName(p.name) }));
    list.push({ code: NCR_CODE, name: 'Metro Manila' });
    list.sort((a, b) => a.name.localeCompare(b.name));
    state.provinces = list;
    return list;
  }

  async function loadCities() {
    if (state.cities) return state.cities;
    const [data, provinces] = await Promise.all([
      fetchJson('/cities-municipalities/', 'cities'),
      loadProvinces()
    ]);
    const provName = {};
    provinces.forEach(p => { provName[p.code] = p.name; });
    state.cities = data.map(c => {
      const provinceCode = c.provinceCode || (c.regionCode === NCR_CODE ? NCR_CODE : null);
      return {
        code: c.code,
        name: cleanName(c.name),
        provinceCode,
        provinceName: provinceCode ? provName[provinceCode] || '' : '',
        isCity: !!c.isCity
      };
    }).sort((a, b) => a.name.localeCompare(b.name));
    return state.cities;
  }

  async function loadBarangays(cityCode) {
    if (state.barangays[cityCode]) return state.barangays[cityCode];
    const data = await fetchJson('/cities-municipalities/' + cityCode + '/barangays/', 'brgy_' + cityCode);
    const list = data.map(b => ({ code: b.code, name: cleanName(b.name) }))
      .sort((a, b) => a.name.localeCompare(b.name));
    state.barangays[cityCode] = list;
    return list;
  }

  // ---------- combobox ----------
  function createCombobox(input, opts) {
    const wrap = input.closest('.input-with-icon') || input.parentNode;
    wrap.classList.add('ac-wrap');
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');

    const list = document.createElement('ul');
    list.className = 'ac-list';
    list.id = 'ac-list-' + (input.name || input.id);
    list.setAttribute('role', 'listbox');
    input.setAttribute('aria-controls', list.id);
    wrap.appendChild(list);

    let items = [];
    let active = -1;
    let open = false;
    let requestId = 0;

    function setOpen(v) {
      open = v;
      list.classList.toggle('open', v);
      input.setAttribute('aria-expanded', v ? 'true' : 'false');
      if (!v) active = -1;
    }

    function renderMessage(html, cls) {
      list.innerHTML = '<li class="ac-msg ' + (cls || '') + '">' + html + '</li>';
      items = [];
      setOpen(true);
    }

    function highlight(name, q) {
      if (!q) return escapeHtml(name);
      const i = normalize(name).indexOf(q);
      if (i < 0) return escapeHtml(name);
      return escapeHtml(name.slice(0, i)) + '<mark>' + escapeHtml(name.slice(i, i + q.length)) + '</mark>' + escapeHtml(name.slice(i + q.length));
    }

    function render(all) {
      const q = normalize(input.value);
      let matches = q
        ? all.filter(it => normalize(it.name).includes(q) || (it.sub && normalize(it.sub).includes(q)))
        : all.slice();
      if (q) {
        // Prefer names that start with the query
        matches.sort((a, b) => (normalize(b.name).startsWith(q) ? 1 : 0) - (normalize(a.name).startsWith(q) ? 1 : 0));
      }
      const total = matches.length;
      matches = matches.slice(0, MAX_ITEMS);
      items = matches;
      active = -1;

      if (!total) {
        renderMessage('<i class="fas fa-info-circle"></i> No match found. You can keep typing it manually.');
        return;
      }

      list.innerHTML = matches.map((it, i) =>
        '<li class="ac-item" role="option" id="' + list.id + '-' + i + '" data-index="' + i + '">' +
          '<i class="fas ' + (opts.icon || 'fa-map-marker-alt') + ' ac-ico"></i>' +
          '<span class="ac-text"><span class="ac-name">' + highlight(it.name, q) + '</span>' +
          (it.sub ? '<span class="ac-sub">' + escapeHtml(it.sub) + '</span>' : '') +
          '</span></li>'
      ).join('') +
        (total > MAX_ITEMS ? '<li class="ac-msg">Showing ' + MAX_ITEMS + ' of ' + total + '. Keep typing to narrow it down.</li>' : '');
      setOpen(true);
    }

    async function refresh() {
      const id = ++requestId;
      const pre = opts.precheck && opts.precheck();
      if (pre) { renderMessage(pre, 'ac-hint'); return; }
      if (!opts.isReady()) renderMessage('<i class="fas fa-spinner fa-spin"></i> Loading suggestions...');
      try {
        const data = await opts.source();
        if (id !== requestId || document.activeElement !== input) return;
        render(data);
      } catch (e) {
        if (id !== requestId) return;
        renderMessage(e.hint || '<i class="fas fa-wifi"></i> Suggestions unavailable right now. Please type it manually.', 'ac-hint');
      }
    }

    function moveActive(delta) {
      if (!open || !items.length) return;
      const nodes = list.querySelectorAll('.ac-item');
      if (active >= 0 && nodes[active]) nodes[active].classList.remove('active');
      active = (active + delta + items.length) % items.length;
      const node = nodes[active];
      node.classList.add('active');
      input.setAttribute('aria-activedescendant', node.id);
      node.scrollIntoView({ block: 'nearest' });
    }

    function choose(index) {
      const it = items[index];
      if (!it) return;
      input.value = it.name;
      setOpen(false);
      opts.onSelect(it);
      input.dispatchEvent(new Event('input', { bubbles: true }));
      if (typeof window.validateAddress === 'function') window.validateAddress(input, opts.label);
    }

    input.addEventListener('focus', refresh);
    input.addEventListener('input', (e) => {
      if (!e.isTrusted) return;
      if (opts.onType) opts.onType();
      refresh();
    });
    input.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown') { e.preventDefault(); open ? moveActive(1) : refresh(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); moveActive(-1); }
      else if (e.key === 'Enter' && open && active >= 0) { e.preventDefault(); choose(active); }
      else if (e.key === 'Escape' && open) { e.preventDefault(); setOpen(false); }
      else if (e.key === 'Tab' && open && active >= 0) { choose(active); }
    });
    input.addEventListener('blur', () => setTimeout(() => setOpen(false), 120));

    // mousedown keeps focus in the input so blur validation doesn't fire first
    list.addEventListener('mousedown', (e) => {
      e.preventDefault();
      const li = e.target.closest('.ac-item');
      if (li) choose(Number(li.dataset.index));
    });

    return { close: () => setOpen(false) };
  }

  // ---------- wiring ----------
  function init() {
    const field = (key, name) => document.querySelector('[data-address="' + key + '"]') || document.querySelector('input[name="' + name + '"]');
    const provinceEl = field('province', 'province');
    const cityEl = field('city', 'city_municipality');
    const brgyEl = field('barangay', 'barangay');
    const countryEl = field('country', 'country');
    if (!provinceEl || !cityEl || !brgyEl) return;

    // Resolve codes from values already in the form (e.g. after a failed submit)
    async function syncFromValues() {
      try {
        if (!state.provinceCode && provinceEl.value.trim()) {
          const provinces = await loadProvinces();
          const p = provinces.find(x => normalize(x.name) === normalize(provinceEl.value));
          if (p) state.provinceCode = p.code;
        }
        if (!state.cityCode && cityEl.value.trim()) {
          const cities = await loadCities();
          const c = cities.find(x => normalize(x.name) === normalize(cityEl.value) &&
            (!state.provinceCode || x.provinceCode === state.provinceCode));
          if (c) state.cityCode = c.code;
        }
      } catch (e) { /* offline: leave as free text */ }
    }

    createCombobox(provinceEl, {
      label: 'Province',
      icon: 'fa-globe-asia',
      isReady: () => !!state.provinces,
      source: loadProvinces,
      onType: () => { state.provinceCode = null; },
      onSelect: (p) => {
        if (state.provinceCode !== p.code) {
          state.provinceCode = p.code;
          // City no longer belongs to this province -> clear it and the barangay
          const cities = state.cities || [];
          const current = cities.find(c => c.code === state.cityCode);
          if (!current || current.provinceCode !== p.code) {
            state.cityCode = null;
            if (cityEl.value) { cityEl.value = ''; }
            if (brgyEl.value) { brgyEl.value = ''; }
          }
        }
      }
    });

    createCombobox(cityEl, {
      label: 'City/Municipality',
      icon: 'fa-city',
      isReady: () => !!state.cities,
      source: async () => {
        await syncFromValues();
        const cities = await loadCities();
        const scoped = state.provinceCode ? cities.filter(c => c.provinceCode === state.provinceCode) : cities;
        return scoped.map(c => ({ ...c, sub: state.provinceCode ? (c.isCity ? 'City' : 'Municipality') : c.provinceName }));
      },
      onType: () => { state.cityCode = null; },
      onSelect: (c) => {
        if (state.cityCode !== c.code) {
          state.cityCode = c.code;
          if (brgyEl.value) brgyEl.value = '';
        }
        if (c.provinceCode && c.provinceName && state.provinceCode !== c.provinceCode) {
          state.provinceCode = c.provinceCode;
          provinceEl.value = c.provinceName;
          if (typeof window.validateAddress === 'function') window.validateAddress(provinceEl, 'Province');
        }
        loadBarangays(c.code).catch(() => {}); // warm up the next list
      }
    });

    createCombobox(brgyEl, {
      label: 'Barangay',
      icon: 'fa-map-marked-alt',
      isReady: () => !!(state.cityCode && state.barangays[state.cityCode]),
      precheck: () => {
        if (state.cityCode || cityEl.value.trim()) return null;
        return '<i class="fas fa-hand-point-up"></i> Choose your City/Municipality first to see its barangays.';
      },
      source: async () => {
        await syncFromValues();
        if (!state.cityCode) {
          const err = new Error('unknown city');
          err.hint = '<i class="fas fa-hand-point-up"></i> Pick your City/Municipality from its suggestion list to see barangays.';
          throw err;
        }
        return loadBarangays(state.cityCode);
      },
      onSelect: () => {}
    });

    if (countryEl) {
      if (!countryEl.value.trim()) countryEl.value = 'Philippines';
      createCombobox(countryEl, {
        label: 'Country',
        icon: 'fa-flag',
        isReady: () => true,
        source: async () => [{ name: 'Philippines' }],
        onSelect: () => {}
      });
    }

    // Preload in the background so the first dropdown opens instantly
    loadProvinces().then(loadCities).catch(() => {});
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
