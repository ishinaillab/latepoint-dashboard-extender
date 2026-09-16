(function () {
    'use strict';
    // Repeated asset evaluation and AJAX fragments must not attach duplicate handlers.
    if (window.ishiCustomerDashboard) return;
    const selector = '.ishi-customer-dashboard[data-ishi-layout]';
    const initialized = new WeakSet();
    const owned = (root, query) => Array.from(root.querySelectorAll(query)).filter(node => node.closest(selector) === root);
    const set = (node, name, value) => {
        value = String(value);
        if (node.getAttribute(name) !== value) node.setAttribute(name, value);
    };
    const panels = root => owned(root, '.latepoint-tab-content[data-ishi-view]');
    const triggers = root => owned(root, '.latepoint-tab-trigger[data-ishi-view]');

    function synchronize(root) {
        const active = panels(root).find(panel => panel.classList.contains('active'));
        if (!active) return;
        const view = active.dataset.ishiView;
        const group = active.dataset.ishiGroup;
        const primary = owned(root, '[data-ishi-primary]');
        primary.forEach(control => {
            const selected = control.dataset.ishiPrimary === group;
            const section = owned(root, '[data-ishi-section]').find(node => node.dataset.ishiSection === control.dataset.ishiPrimary);
            set(control, 'role', 'tab');
            set(control, 'aria-controls', section.id);
            set(control, 'aria-selected', selected);
            set(control, 'tabindex', selected ? 0 : -1);
            set(section, 'role', 'tabpanel');
            set(section, 'aria-labelledby', control.id);
            set(section, 'tabindex', 0);
            section.hidden = !selected;
        });
        owned(root, '.ishi-dashboard-primary, .ishi-dashboard-secondary').forEach(nav => set(nav, 'role', 'tablist'));
        owned(root, '.ishi-dashboard-secondary').forEach(nav => {
            nav.hidden = group === 'appointments' && view === 'book' && nav.dataset.ishiSecondary === 'appointments';
            const links = Array.from(nav.querySelectorAll('.latepoint-tab-trigger'));
            // Hidden groups retain a usable default tab stop when next opened.
            links.forEach((link, index) => {
                const selected = link.dataset.ishiView === view || (link.dataset.ishiGroup !== group && index === 0);
                set(link, 'role', 'tab');
                const panel = panels(root).find(node => node.dataset.ishiView === link.dataset.ishiView);
                set(link, 'aria-controls', panel.id);
                set(link, 'aria-selected', selected);
                set(link, 'tabindex', selected ? 0 : -1);
                set(panel, 'role', 'tabpanel');
                set(panel, 'aria-labelledby', link.id);
                set(panel, 'tabindex', 0);
            });
        });
        panels(root).forEach(panel => { panel.hidden = panel !== active; });
        owned(root, '.ishi-dashboard-actions').forEach(actions => {
            const book = actions.querySelector('[data-ishi-view="book"]');
            const back = actions.querySelector('[data-ishi-return]');
            if (book) {
                book.hidden = view === 'book';
                const panel = panels(root).find(node => node.dataset.ishiView === 'book');
                set(book, 'aria-controls', panel.id);
                set(panel, 'role', 'region');
                set(panel, 'aria-label', book.getAttribute('aria-label'));
            }
            if (back) back.hidden = view !== 'book';
        });
        set(root, 'data-ishi-enhanced', 'true');
    }

    function activate(root, key) {
        const panel = panels(root).find(node => node.dataset.ishiView === key);
        const control = triggers(root).find(node => node.dataset.ishiView === key);
        if (!panel || !control) return false;
        // The native active leaf panel is the sole selection state. No URL router,
        // per-section memory or separate primary-selection store is maintained.
        panels(root).forEach(node => node.classList.toggle('active', node === panel));
        triggers(root).forEach(node => node.classList.toggle('active', node === control));
        synchronize(root);
        return true;
    }

    function initialize(scope = document) {
        const roots = Array.from(scope.querySelectorAll(selector));
        if (scope.matches && scope.matches(selector)) roots.unshift(scope);
        roots.forEach(root => {
            if (initialized.has(root)) return;
            initialized.add(root);
            synchronize(root);
            // Native/add-on code can still set the retained active leaf classes.
            new MutationObserver(records => {
                if (records.some(record => record.target.matches('.latepoint-tab-content[data-ishi-view]'))) synchronize(root);
            }).observe(root, { attributes: true, attributeFilter: ['class'], subtree: true });
        });
    }

    document.addEventListener('click', event => {
        const control = event.target.closest && event.target.closest('a[data-ishi-open-view], .latepoint-tab-trigger[data-ishi-view]');
        if (!control || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const root = control.closest(selector);
        if (!root) return;
        event.preventDefault();
        const key = control.dataset.ishiOpenView || control.dataset.ishiView;
        // Capture phase updates visibility before Pro's existing Messages click
        // handler runs. Do not stop propagation or duplicate any business handler.
        const wasBook = panels(root).some(panel => panel.dataset.ishiView === 'book' && panel.classList.contains('active'));
        if (control.hasAttribute('data-ishi-open-view')) {
            if (control.hasAttribute('data-ishi-primary') && control.getAttribute('aria-selected') === 'true') return;
            const native = triggers(root).find(node => node.dataset.ishiView === key);
            if (native) native.click();
        } else {
            activate(root, key);
        }
        if (control.hasAttribute('data-ishi-return') || (key === 'book' && !wasBook)) {
            const focus = key === 'book' ? root.querySelector('[data-ishi-return]') : triggers(root).find(node => node.dataset.ishiView === 'appointments');
            if (focus) focus.focus();
        }
    }, true);

    document.addEventListener('keydown', event => {
        const tab = event.target.closest && event.target.closest('[role="tab"]');
        if (!tab || !tab.closest(selector)) return;
        const list = tab.closest('[role="tablist"]');
        if (!list) return;
        const tabs = Array.from(list.querySelectorAll('[role="tab"]')).filter(node => node.closest('[role="tablist"]') === list);
        let index = tabs.indexOf(tab);
        const rtl = getComputedStyle(list).direction === 'rtl';
        if (event.key === 'ArrowRight') index += rtl ? -1 : 1;
        else if (event.key === 'ArrowLeft') index += rtl ? 1 : -1;
        else if (event.key === 'Home') index = 0;
        else if (event.key === 'End') index = tabs.length - 1;
        else if (event.key === ' ') { event.preventDefault(); tab.click(); return; }
        else return; // Enter retains the anchor's native click behavior.
        event.preventDefault();
        const target = tabs[(index + tabs.length) % tabs.length];
        tabs.forEach(node => set(node, 'tabindex', node === target ? 0 : -1));
        target.focus();
    });

    // Observe new markup only; ordinary form replacements do not rebuild navigation.
    new MutationObserver(records => {
        records.forEach(record => record.addedNodes.forEach(node => {
            if (node.nodeType === 1) initialize(node);
        }));
    }).observe(document.documentElement, { childList: true, subtree: true });
    window.ishiCustomerDashboard = { initialize };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => initialize());
    else initialize();
})();
