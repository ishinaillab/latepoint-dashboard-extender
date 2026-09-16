/* Browser interaction contract using synthetic server-rendered data. No site writes. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const php = process.env.PHP_BINARY || 'php';
const fixture = (...args) => execFileSync(php, [path.join(__dirname, 'browser-fixture.php'), ...args], { encoding: 'utf8', maxBuffer: 4 * 1024 * 1024 });
let checks = 0;
const check = (value, message) => { assert.ok(value, message); checks++; };
(async () => {
    const browser = await chromium.launch({ headless: true, ...(process.env.CHROME_BINARY ? { executablePath: process.env.CHROME_BINARY } : {}) });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        await page.setContent(fixture());
        await page.waitForSelector('[data-ishi-enhanced]');
        const primary = name => page.locator('[data-ishi-primary="' + name + '"]');
        const leaf = name => page.locator('button.latepoint-tab-trigger[data-ishi-view="' + name + '"]');
        const state = async (view, group) => {
            check(await page.locator('.latepoint-tab-content.active').count() === 1, 'One active leaf');
            check(await page.locator('.latepoint-tab-content.active').getAttribute('data-ishi-view') === view, 'Expected active view: ' + view);
            check(await primary(group).getAttribute('aria-selected') === 'true', 'Primary follows active view: ' + group);
            check(await page.locator('section[data-ishi-section]:visible').count() === 1, 'One visible section');
        };
        await state('appointments', 'appointments');
        await leaf('history').click(); await state('history', 'appointments');
        await leaf('appointments').click(); await state('appointments', 'appointments');
        await primary('press-ons').click(); await state('press-ons', 'press-ons');
        check(await page.locator('article[data-order-id]:visible').count() === 10, 'Ten cards retained');
        // Existing feature listeners still receive the same clicks; navigation does not stop propagation.
        await page.evaluate(() => {
            window.domainCalls = { messages: 0, conversation: 0, order: 0, booking: 0, profile: 0, address: 0 };
            document.querySelector('.latepoint-trigger-messages-tab').addEventListener('click', () => window.domainCalls.messages++);
            document.querySelectorAll('.lc-conversation').forEach(node => node.addEventListener('click', () => window.domainCalls.conversation++));
            document.querySelectorAll('[data-os-action]').forEach(node => {
                if ((node.dataset.osAction || '').includes('ishi_press_ons')) node.addEventListener('click', () => window.domainCalls.order++);
            });
            document.querySelector('.os_trigger_booking').addEventListener('click', () => window.domainCalls.booking++);
            document.querySelector('[data-ishi-ui="profile"] form').addEventListener('submit', event => { event.preventDefault(); window.domainCalls.profile++; });
            const address = document.querySelector('[data-ishi-ui="addresses"]');
            address.addEventListener('click', event => {
                if (event.target.dataset.address) address.innerHTML = '<form><label>City <input value="Manila"></label><button>Save ' + event.target.dataset.address + '</button></form>';
            });
            address.addEventListener('submit', event => {
                event.preventDefault(); window.domainCalls.address++;
                address.innerHTML = '<button type="button" data-address="billing">Edit billing</button><button type="button" data-address="shipping">Edit shipping</button>';
            });
        });
        await page.locator('[data-os-action*="ishi_press_ons"]').first().click();
        await primary('messages').click(); await state('messages', 'messages');
        await page.getByRole('button', { name: 'Conversation two' }).click();
        await primary('account').click(); await state('profile', 'account');
        await page.locator('[name="password_1"]').fill('fixture-only');
        await page.getByRole('button', { name: 'Save profile' }).click();
        await leaf('addresses').click(); await state('addresses', 'account');
        for (const type of ['billing', 'shipping']) {
            await page.getByRole('button', { name: 'Edit ' + type }).click();
            await page.getByRole('button', { name: 'Save ' + type }).click();
            await state('addresses', 'account');
        }
        await primary('appointments').click();
        await leaf('book').click(); await state('book', 'appointments');
        check(await leaf('book').evaluate(node => node === document.activeElement), 'Booking focus stays on selected submenu');
        check(await leaf('history').isVisible(), 'Booking does not hide its sibling submenus');
        await page.getByRole('button', { name: 'Open booking' }).click();
        await leaf('appointments').click(); await state('appointments', 'appointments');
        check(await leaf('appointments').evaluate(node => node === document.activeElement), 'Return focus is predictable');
        const calls = await page.evaluate(() => window.domainCalls);
        check(JSON.stringify(calls) === JSON.stringify({ messages: 1, conversation: 1, order: 1, booking: 1, profile: 1, address: 2 }), 'Feature events delivered once');
        // ARIA references must resolve uniquely within this document.
        check(await page.locator('[role="tab"]').evaluateAll(tabs => tabs.every(tab => {
            const target = document.getElementById(tab.getAttribute('aria-controls'));
            return target && target.getAttribute('aria-labelledby') === tab.id;
        })), 'Tab/panel ARIA relationships resolve');
        await primary('appointments').focus();
        await page.keyboard.press('ArrowRight');
        check(await primary('press-ons').evaluate(node => node === document.activeElement), 'Arrow key moves focus');
        await state('appointments', 'appointments');
        await page.keyboard.press('Space'); await state('press-ons', 'press-ons');
        await page.keyboard.press('End');
        check(await primary('account').evaluate(node => node === document.activeElement), 'End moves focus to Account');
        await page.keyboard.press('Enter'); await state('profile', 'account');
        check(await primary('account').evaluate(node => getComputedStyle(node).outlineStyle !== 'none'), 'Keyboard focus is visible');
        for (const width of [320, 390, 768, 1280]) {
            await page.setViewportSize({ width, height: 900 });
            for (const group of ['appointments', 'press-ons', 'messages', 'account']) {
                await primary(group).click();
                check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No horizontal page overflow: ' + width + '/' + group);
            }
            const boxes = await page.locator('[data-ishi-primary]').evaluateAll(nodes => nodes.map(node => ({ w: node.getBoundingClientRect().width, h: node.getBoundingClientRect().height })));
            check(boxes.every(box => box.w >= 44 && box.h >= 44), 'Primary touch targets at ' + width);
        }
        // Tabs have no URL/hash destinations for browser or theme smooth scrolling.
        check(await page.locator('.ishi-dashboard-primary a, .ishi-dashboard-secondary a').count() === 0, 'No hash anchors in dashboard navigation');
        check(await page.locator('.ishi-dashboard-primary button, .ishi-dashboard-secondary button').evaluateAll(nodes => nodes.every(n => n.type === 'button')), 'Navigation never submits surrounding forms');
        await page.evaluate(() => {
            const before = document.createElement('div'); before.style.height = '1000px'; document.body.prepend(before);
            const after = document.createElement('div'); after.style.height = '1200px'; document.body.append(after);
            window.scrollTo(0, 900);
        });
        for (const [group, view] of [['appointments','history'], ['appointments','book'], ['account','profile'], ['account','addresses'], ['press-ons','press-ons'], ['press-ons','custom-press-ons']]) {
            const y = await page.evaluate(() => scrollY);
            await primary(group).click(); await leaf(view).click();
            await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
            check(Math.abs(await page.evaluate(() => scrollY) - y) < 1, 'No page scrolling when switching to ' + view);
            check(await page.evaluate(() => location.hash === ''), 'Tab navigation leaves URL hash unchanged');
            const gap = await page.locator('[data-ishi-section="' + group + '"]').evaluate(section => {
                const nav = section.querySelector('.ishi-dashboard-secondary');
                const panel = section.querySelector('.latepoint-tab-content.active');
                return panel.getBoundingClientRect().top - nav.getBoundingClientRect().bottom;
            });
            check(Math.abs(gap - 40) < 0.5, 'Exactly 40px submenu/content gap: ' + view);
        }
        check(await leaf('custom-press-ons').evaluate(n => { const s = getComputedStyle(n); return s.fontSize === '12.8px' && s.fontWeight === '700' && s.minHeight === '40px' && Math.abs(parseFloat(s.lineHeight) - 15.36) < 0.1; }), 'Requested submenu typography');
        await state('custom-press-ons', 'press-ons');
        check(await page.locator('article[data-order-id]:visible').count() === 10, 'Custom orders use the same default page size');
        // Repeated initialization cannot double-deliver interactions.
        const messageCallsBefore = await page.evaluate(() => window.domainCalls.messages);
        await page.addScriptTag({ path: path.join(__dirname, '../public/javascripts/customer-dashboard.js') });
        await primary('messages').click();
        check(await page.evaluate(() => window.domainCalls.messages) === messageCallsBefore + 1, 'Duplicate script evaluation remains idempotent');
        await page.goto('about:blank'); await page.setContent(fixture('--page-two')); await page.waitForSelector('[data-ishi-enhanced]');
        await state('press-ons', 'press-ons');
        check(await page.locator('a[rel="prev"]:visible').count() === 1, 'Reloaded page two has Previous');
        await page.goto('about:blank'); await page.setContent(fixture('--custom-page-two')); await page.waitForSelector('[data-ishi-enhanced]');
        await state('custom-press-ons', 'press-ons');
        check(await page.locator('article[data-order-id]:visible').count() === 1, 'Custom page two counts only matching orders');
        await page.goto('about:blank'); await page.setContent(fixture('--double'));
        // setContent retains window globals; simulate a fragment initializer explicitly.
        await page.evaluate(() => window.ishiCustomerDashboard.initialize());
        check(await page.locator('[data-ishi-enhanced]').count() === 2, 'Two independent dashboard instances initialize');
        await page.locator('[data-ishi-primary="account"]').nth(1).click();
        check(await page.locator('.ishi-customer-dashboard').nth(0).locator('.latepoint-tab-content.active').getAttribute('data-ishi-view') === 'appointments', 'Second instance does not change first');
        // No-JS content is still server-rendered and navigable via anchors.
        const nojs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
        const fallback = await nojs.newPage(); await fallback.setContent(fixture());
        check(await fallback.locator('.latepoint-tab-content:visible').count() === 8, 'All eight views accessible before enhancement');
        await nojs.close();
        check(errors.length === 0, 'No new browser errors: ' + errors.join('; '));
        if (process.env.SCREENSHOT_DIR) {
            fs.mkdirSync(process.env.SCREENSHOT_DIR, { recursive: true });
            await page.goto('about:blank'); await page.setContent(fixture()); await page.waitForSelector('[data-ishi-enhanced]');
            for (const width of [390, 768, 1280]) {
                await page.setViewportSize({ width, height: 900 });
                await page.locator('[data-ishi-primary="press-ons"]').click();
                await state('press-ons', 'press-ons');
                await page.screenshot({ path: path.join(process.env.SCREENSHOT_DIR, 'dashboard-' + width + '.png'), fullPage: true });
            }
        }
        console.log('PASS: ' + checks + ' browser layout checks (synthetic feature handlers)');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
