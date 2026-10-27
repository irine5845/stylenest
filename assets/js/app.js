/* StyleNest storefront scripts. Everything degrades gracefully: forms still work without JavaScript. */
(function () {
  'use strict';
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  /* ---------- toasts ---------- */
  function toast(message, opts = {}) {
    const area = $('#toasts'); if (!area) return;
    const t = document.createElement('div');
    t.className = 'toast' + (opts.error ? ' err' : '');
    t.innerHTML = esc(message) + (opts.link ? ' <a href="' + esc(opts.link) + '">' + esc(opts.linkText || 'View') + '</a>' : '');
    area.appendChild(t);
    setTimeout(() => t.remove(), opts.error ? 6000 : 4500);
  }
  function setBagCount(n) {
    $$('[data-bag-count]').forEach((el) => { el.textContent = n; el.hidden = !n; });
  }

  /* ---------- header menu & filters ---------- */
  const menu = $('[data-menu]');
  if (menu) menu.addEventListener('click', () => {
    const open = $('#site-nav').classList.toggle('open');
    menu.setAttribute('aria-expanded', open);
  });
  const ft = $('[data-filters]');
  if (ft) ft.addEventListener('click', () => {
    const open = $('#filters').classList.toggle('open');
    ft.setAttribute('aria-expanded', open);
  });

  /* ---------- quantity steppers ---------- */
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-qty]'); if (!b) return;
    const input = $('input[name=qty]', b.parentElement); if (!input) return;
    const min = parseInt(input.min || '1', 10), max = parseInt(input.max || '10', 10);
    const next = Math.max(min, Math.min(max, (parseInt(input.value, 10) || min) + parseInt(b.dataset.qty, 10)));
    input.value = next;
    input.dispatchEvent(new Event('change', {bubbles: true}));
  });
  $$('[data-cart-line]').forEach((form) => {
    let timer;
    $('input[name=qty]', form).addEventListener('change', () => { clearTimeout(timer); timer = setTimeout(() => form.submit(), 600); });
  });

  /* ---------- size & colour picker ---------- */
  function initPicker(form) {
    const variants = JSON.parse(form.dataset.variants || '[]');
    const sizeIn  = $$('input[name=size]', form), colorIn = $$('input[name=color]', form);
    const stockEl = $('[data-stock]', form), nameEl = $('[data-color-name]', form), qty = $('input[name=qty]', form);
    const checked = (list) => { const c = list.find((i) => i.checked); return c ? c.value : null; };
    const hasStock = (size, color) => variants.some((v) => v.stock > 0 && (!size || v.size === size) && (!color || v.color === color));

    function refresh() {
      let size = checked(sizeIn), color = checked(colorIn);
      sizeIn.forEach((i) => { i.disabled = !hasStock(i.value, color); if (i.disabled && i.checked) i.checked = false; });
      size = checked(sizeIn);
      colorIn.forEach((i) => { i.disabled = !hasStock(size, i.value); if (i.disabled && i.checked) i.checked = false; });
      color = checked(colorIn);
      if (nameEl) nameEl.textContent = color ? '(' + color + ')' : '';
      const v = size && color ? variants.find((x) => x.size === size && x.color === color) : null;
      stockEl.className = 'stock';
      if (v) {
        const max = Math.min(v.stock, 10);
        if (qty) { qty.max = max; if (+qty.value > max) qty.value = max; }
        if (v.stock <= 0)      { stockEl.textContent = 'Sold out in this size and colour.'; stockEl.classList.add('out'); }
        else if (v.stock <= 5) { stockEl.textContent = 'Only ' + v.stock + ' left.'; stockEl.classList.add('low'); }
        else                   { stockEl.textContent = 'In stock.'; stockEl.classList.add('in'); }
      } else {
        stockEl.textContent = size || color ? 'Choose ' + (!size ? 'a size' : 'a colour') + ' to see availability.' : '';
      }
    }
    form.addEventListener('change', refresh);
    // pre-select the first colour that has stock when there are several
    if (colorIn.length > 1 && !checked(colorIn)) { const first = colorIn.find((i) => hasStock(null, i.value)); if (first) first.checked = true; }
    refresh();

    form.addEventListener('submit', async (e) => {
      if (!window.fetch) return;           // fall back to a normal form post
      e.preventDefault();
      const btn = $('button[type=submit]', form); btn.disabled = true;
      try {
        const res = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.SN.csrf}});
        const data = await res.json();
        if (data.ok) {
          setBagCount(data.count);
          toast(data.message || 'Added to your bag.', {link: data.cartUrl, linkText: 'View bag'});
          const dlg = form.closest('dialog'); if (dlg) dlg.close();
        } else toast(data.message || 'Could not add that item.', {error: true});
      } catch (err) { toast('Something went wrong. Please try again.', {error: true}); }
      btn.disabled = false;
    });
  }
  $$('[data-picker]').forEach(initPicker);

  /* ---------- gallery ---------- */
  $$('[data-thumb]').forEach((b) => b.addEventListener('click', () => {
    const img = $('#main-img'); img.src = b.dataset.thumb; img.alt = b.dataset.alt || '';
    $$('[data-thumb]').forEach((x) => x.setAttribute('aria-current', x === b ? 'true' : 'false'));
  }));

  /* ---------- quick add dialog ---------- */
  const dlg = $('#quick');
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-quick]');
    if (!b || !dlg || !dlg.showModal) return;
    b.disabled = true;
    try {
      const res = await fetch(window.SN.base + '/api/product.php?id=' + encodeURIComponent(b.dataset.quick));
      const p = await res.json(); if (!p.ok) throw new Error();
      const colors = Object.entries(p.colors), many = colors.length > 1;
      const sizes = p.sizes;
      dlg.innerHTML =
        '<div class="quick-in"><div class="main-img"><img src="' + esc(p.image) + '" alt=""></div><div>' +
        '<button class="icon-btn quick-close" type="button" aria-label="Close" data-close>&times;</button>' +
        '<h2>' + esc(p.name) + '</h2><div class="price">' + esc(p.price) + '</div>' +
        '<form class="buy-form" method="post" action="' + esc(p.action) + '" data-picker data-variants="' + esc(JSON.stringify(p.variants)) + '">' +
        '<input type="hidden" name="csrf_token" value="' + esc(p.csrf) + '"><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="' + p.id + '">' +
        '<fieldset class="opt" style="border:0;padding:0;margin:1rem 0"><legend class="label">Colour <span data-color-name></span></legend><div class="colors">' +
        colors.map(([n, h]) => '<label class="color-opt" title="' + esc(n) + '"><input type="radio" name="color" value="' + esc(n) + '" required' + (colors.length === 1 ? ' checked' : '') + '><span style="background:' + esc(h) + '"></span><span class="sr">' + esc(n) + '</span></label>').join('') +
        '</div></fieldset>' +
        '<fieldset class="opt" style="border:0;padding:0;margin:1rem 0"><legend class="label">Size</legend><div class="sizes">' +
        sizes.map((s) => '<label class="size-opt"><input type="radio" name="size" value="' + esc(s) + '" required' + (sizes.length === 1 ? ' checked' : '') + '><span>' + esc(s) + '</span></label>').join('') +
        '</div></fieldset><div class="stock" data-stock aria-live="polite"></div>' +
        '<div class="buy-row"><div class="qty"><button type="button" data-qty="-1" aria-label="Decrease quantity">&minus;</button><input type="number" name="qty" value="1" min="1" max="10" aria-label="Quantity"><button type="button" data-qty="1" aria-label="Increase quantity">+</button></div>' +
        '<button class="btn" type="submit">Add to bag</button></div></form>' +
        '<p style="margin:1rem 0 0"><a href="' + esc(p.url) + '">View full details</a></p></div></div>';
      initPicker($('[data-picker]', dlg));
      dlg.showModal();
    } catch (err) { toast('Could not load that product. Please try again.', {error: true}); }
    b.disabled = false;
  });
  if (dlg) {
    dlg.addEventListener('click', (e) => { if (e.target === dlg || e.target.closest('[data-close]')) dlg.close(); });
  }

  /* ---------- checkout ---------- */
  const co = $('[data-checkout]');
  if (co) {
    const fmt = (n) => 'KSh ' + Math.round(n).toLocaleString('en-US');
    const county = $('#county'), feeEl = $('[data-fee]', co), totalEl = $('[data-total]', co);
    const sub = +co.dataset.subtotal, free = +co.dataset.free;
    function fee() {
      if (free > 0 && sub >= free) return 0;
      return county.value.toLowerCase() === co.dataset.local.toLowerCase() ? +co.dataset.localFee : +co.dataset.otherFee;
    }
    function update() { const f = fee(); feeEl.textContent = f ? fmt(f) : 'Free'; totalEl.textContent = fmt(sub + f); }
    county.addEventListener('change', update); update();

    const mp = $('[data-mpesa-field]', co);
    const togglePay = () => { const m = $('input[name=payment_method]:checked', co); mp.hidden = !(m && m.value === 'mpesa'); };
    $$('input[name=payment_method]', co).forEach((r) => r.addEventListener('change', togglePay)); togglePay();

    const saved = $('[data-saved]', co);
    if (saved) saved.addEventListener('change', () => {
      if (!saved.value) return;
      const a = JSON.parse(saved.value);
      $('#name').value = a.name; $('#phone').value = a.phone; $('#address').value = a.address; $('#town').value = a.town; county.value = a.county; update();
    });
  }

  /* ---------- M-Pesa payment polling ---------- */
  const pay = $('[data-pay]');
  if (pay && pay.dataset.state === 'pending') {
    let tries = 0;
    const note = $('[data-pay-note]', pay);
    const tick = async () => {
      tries++;
      try {
        const r = await fetch(pay.dataset.statusUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const d = await r.json();
        if (d.paid) { window.location.href = pay.dataset.doneUrl; return; }
        if (d.status && d.status !== 'pending') { window.location.reload(); return; }
      } catch (e) { /* keep trying */ }
      if (tries === 20 && note) note.textContent = 'Still waiting. If you did not get a prompt, check your number and use the retry option.';
      if (tries < 90) setTimeout(tick, 3000);
      else if (note) note.textContent = 'We have not received confirmation yet. Refresh this page, or contact us with your order number.';
    };
    setTimeout(tick, 3000);
  }
})();
