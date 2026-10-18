// Startseite: Domaincheck, Ergebnisliste und Warenkorb-Leiste.
(() => {
  const csrf = document.querySelector('meta[name=csrf-token]').content;
  const form = document.getElementById('check-form');
  const input = document.getElementById('name');
  const results = document.getElementById('results');
  const errorEl = document.getElementById('form-error');
  const bar = document.getElementById('cartbar');
  let currentName = '';
  let runId = 0;

  const el = (tag, cls, text) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  };

  const api = async (url, opts) => {
    const res = await fetch(url, opts);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || 'Etwas ist schiefgelaufen. Bitte versuche es erneut.');
    return data;
  };

  const postCart = (body) => api('/api/cart', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
    body: new URLSearchParams(body),
  });

  function renderCart(c) {
    bar.hidden = c.count === 0;
    document.getElementById('cart-count').textContent = c.count + (c.count === 1 ? ' Domain' : ' Domains');
    document.getElementById('cart-list').textContent = c.items.map((i) => i.display).join(', ');
    document.getElementById('cart-total').textContent = c.total;
    document.body.style.paddingBottom = c.count ? bar.offsetHeight + 'px' : '';
  }

  function row(r, i) {
    const li = el('li', 'res ' + r.status);
    li.style.setProperty('--i', i);
    const dn = el('div', 'dn', r.display);
    li.append(dn);
    if (r.status === 'free') {
      dn.append(el('small', '', 'Verlängerung ' + r.renew + ' / Jahr'));
      li.append(el('span', 'tag ok', 'Frei'), el('span', 'price', r.price));
      const btn = el('button', 'btn btn-primary');
      btn.type = 'button';
      const setState = (inCart) => {
        btn.textContent = inCart ? '✓ Im Warenkorb' : 'Auswählen';
        btn.classList.toggle('in-cart', inCart);
        btn.classList.toggle('btn-primary', !inCart);
        btn.dataset.inCart = inCart ? '1' : '';
      };
      setState(r.in_cart);
      btn.addEventListener('click', async () => {
        btn.disabled = true;
        try {
          const adding = !btn.dataset.inCart;
          renderCart(await postCart(adding ? { action: 'add', name: r.name, tld: r.tld } : { action: 'remove', fqdn: r.fqdn }));
          setState(adding);
        } catch (e) { errorEl.textContent = e.message; errorEl.hidden = false; }
        btn.disabled = false;
      });
      li.append(btn);
    } else {
      li.append(el('span', 'tag', r.status === 'taken' ? 'Vergeben' : 'Nicht prüfbar'));
    }
    return li;
  }

  function show(data) {
    results.replaceChildren();
    const free = data.results.filter((r) => r.status === 'free');
    const other = data.results.filter((r) => r.status !== 'free');
    const head = el('div', 'res-head');
    head.append(el('h2', '', free.length ? free.length + ' von ' + data.results.length + ' Endungen sind frei' : 'Leider ist „' + data.display + '“ überall vergeben'));
    results.append(head);
    if (free.length) {
      const ul = el('ul', 'res-list');
      free.forEach((r, i) => ul.append(row(r, i)));
      results.append(ul);
    }
    if (other.length) {
      results.append(el('p', 'res-more', free.length ? 'Nicht verfügbar' : 'Probiere eine Variante des Namens – z. B. mit Stadt oder Branche.'));
      const ul = el('ul', 'res-list');
      other.forEach((r, i) => ul.append(row(r, free.length + i)));
      results.append(ul);
    }
  }

  async function check(raw) {
    const id = ++runId;
    errorEl.hidden = true;
    results.replaceChildren(...Array.from({ length: 5 }, () => el('div', 'skeleton')));
    results.firstChild.style.marginBottom = '10px';
    try {
      const data = await api('/api/check?name=' + encodeURIComponent(raw));
      if (id !== runId) return;
      currentName = data.name;
      input.value = data.display;
      history.replaceState(null, '', '/?name=' + encodeURIComponent(data.display));
      show(data);
      results.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (e) {
      if (id !== runId) return;
      results.replaceChildren();
      errorEl.textContent = e.message;
      errorEl.hidden = false;
    }
  }

  form.addEventListener('submit', (ev) => {
    ev.preventDefault();
    if (!input.value.trim()) { errorEl.textContent = 'Bitte gib zuerst einen Namen ein.'; errorEl.hidden = false; input.focus(); return; }
    check(input.value);
  });

  api('/api/cart').then(renderCart).catch(() => {});
  if (input.value.trim()) check(input.value);
})();
