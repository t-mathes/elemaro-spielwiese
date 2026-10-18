// Kleine Helfer für alle Seiten (ohne Inline-Skripte wegen CSP).
(() => {
  // Schalter senden ihr Formular sofort ab.
  document.querySelectorAll('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));

  // Firmenfeld nur bei "Unternehmen" zeigen.
  const company = document.querySelector('[data-company]');
  if (company) {
    const sync = () => { company.hidden = !document.querySelector('input[name=type][value=company]:checked'); };
    document.querySelectorAll('input[name=type]').forEach((r) => r.addEventListener('change', sync));
    sync();
  }

  // DNS-Formular: Prio-Feld nur bei MX, Platzhalter je Typ.
  const type = document.querySelector('[data-dns-type]');
  if (type) {
    const form = type.form;
    const hints = { A: '203.0.113.10', AAAA: '2001:db8::1', CNAME: 'ziel.beispiel.de', MX: 'mail.beispiel.de', TXT: 'v=spf1 include:… ~all' };
    const sync = () => {
      form.querySelector('.prio').hidden = type.value !== 'MX';
      form.querySelector('[name=value]').placeholder = hints[type.value] || '';
    };
    type.addEventListener('change', sync);
    sync();
  }
})();
