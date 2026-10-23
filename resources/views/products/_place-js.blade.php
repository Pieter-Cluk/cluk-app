<script>
(() => {
    // Subcategories follow the category; attributes show only for the subcategories they apply to.
    const cat = document.querySelector('.js-cat'), sub = document.querySelector('.js-sub');
    if (!cat || !sub) return;
    const attrs = document.querySelectorAll('.js-attr');
    const filterSubs = () => {
        sub.querySelectorAll('option[data-cat]').forEach(o => { o.hidden = cat.value !== '' && o.dataset.cat !== cat.value; });
        if (sub.selectedOptions[0]?.hidden) sub.value = '';
    };
    const showAttrs = () => attrs.forEach(el => {
        const on = sub.value !== '' && el.dataset.subs.split(',').includes(sub.value);
        el.hidden = !on;
        el.querySelectorAll('select, input').forEach(i => { i.disabled = !on; });
    });
    cat.addEventListener('change', () => { filterSubs(); showAttrs(); });
    sub.addEventListener('change', () => { const o = sub.selectedOptions[0]; if (o?.dataset.cat) cat.value = o.dataset.cat; filterSubs(); showAttrs(); });
    filterSubs(); showAttrs();
})();
</script>
