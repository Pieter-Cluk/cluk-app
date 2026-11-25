// Search in the top bar: suggestions while typing, Enter opens the full result page.
(() => {
    const form = document.querySelector('.top-search');
    if (!form) return;
    const input = form.querySelector('input[name=q]');
    const box = form.querySelector('.top-suggest');
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let timer = null, ctrl = null, active = -1;

    const links = () => [...box.querySelectorAll('a')];
    const close = () => { box.hidden = true; active = -1; };
    const mark = i => { const l = links(); active = (i + l.length) % l.length; l.forEach((a, n) => a.classList.toggle('on', n === active)); l[active]?.scrollIntoView({ block: 'nearest' }); };

    const show = (groups, q) => {
        if (!groups.length) {
            box.innerHTML = `<p class="ts-empty">${esc(form.dataset.none || '')}</p>`;
        } else {
            box.innerHTML = groups.map(g => `<div class="ts-group"><b>${esc(g.label)}</b>${g.items.map(i =>
                `<a href="${esc(i.url)}"><span>${esc(i.title)}</span>${i.sub ? `<small>${esc(i.sub)}</small>` : ''}</a>`).join('')}</div>`).join('')
                + `<a class="ts-all" href="${esc(form.action)}?q=${encodeURIComponent(q)}">${esc(form.dataset.all || '')}</a>`;
        }
        box.hidden = false; active = -1;
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { close(); return; }
        timer = setTimeout(async () => {
            ctrl?.abort(); ctrl = new AbortController();
            try {
                const r = await fetch(form.dataset.suggest + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, signal: ctrl.signal });
                if (r.ok) show((await r.json()).groups || [], q);
            } catch (e) { /* typing on: an older request was stopped */ }
        }, 200);
    });
    input.addEventListener('keydown', e => {
        if (box.hidden) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); mark(active + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); mark(active - 1); }
        else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); links()[active].click(); }
        else if (e.key === 'Escape') close();
    });
    form.addEventListener('submit', e => { if (!input.value.trim()) e.preventDefault(); });
    document.addEventListener('click', e => { if (!form.contains(e.target)) close(); });
})();
