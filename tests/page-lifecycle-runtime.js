'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright-core');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/lunara-page-lifecycle.js'), 'utf8');
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', '/usr/bin/chromium', '/usr/bin/google-chrome'].find(fs.existsSync);
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        const page = await browser.newPage();
        await page.setContent('<main id="main"><h1>First document</h1></main>');
        await page.addScriptTag({ content: source });
        const result = await page.evaluate(async () => {
            let checks = 0;
            function assert(value, message) { checks++; if (!value) throw Error(message); }
            const tick = () => new Promise(resolve => setTimeout(resolve, 35));
            const app = window.LunaraApp;
            const first = document.querySelector('#main');
            let mounts = 0, disposals = 0, clicks = 0, timers = 0, observations = 0, signals = [];
            const unregister = app.register('resources', (root, context) => {
                mounts++; signals.push(context.signal);
                assert(root === app.current.root, 'Mount sees the current root');
                context.on(window, 'fixture-click', () => clicks++);
                context.timeout(() => timers++, 20);
                context.interval(() => timers++, 20);
                context.frame(() => timers++);
                context.observe({ disconnect() { observations++; } });
                context.cleanup(() => disposals++);
            });
            await app.mount(first); await Promise.resolve();
            assert(mounts === 1, 'Late registration mounts once on the existing document');
            app.register('resources', () => { throw Error('duplicate must not execute'); });
            await app.mount(first);
            assert(mounts === 1, 'Duplicate asset/duplicate mount does not duplicate resources');
            window.dispatchEvent(new Event('fixture-click'));
            assert(clicks === 1, 'Current component receives events');
            await app.destroy('test'); await tick();
            window.dispatchEvent(new Event('fixture-click'));
            assert(signals[0].aborted && clicks === 1, 'Teardown aborts and removes listeners');
            assert(timers === 0, 'Timeout, interval and frame callbacks are canceled');
            assert(observations === 1 && disposals === 1, 'Observer and disposal run once');
            await app.destroy('again');
            assert(disposals === 1, 'Repeated teardown is harmless');
            unregister();

            let resolveMount, asyncCleanup = 0, lateContext, errors = 0;
            window.addEventListener('lunara:page:error', () => errors++);
            const unasync = app.register('async', (_root, context) => {
                lateContext = context;
                return new Promise(resolve => { resolveMount = resolve; });
            });
            const pending = app.mount(first);
            await Promise.resolve();
            await app.destroy();
            resolveMount(async () => { await Promise.resolve(); asyncCleanup++; });
            await pending;
            assert(lateContext.signal.aborted && asyncCleanup === 1, 'A pending asynchronous mount cleans up when it resolves after teardown');
            lateContext.cleanup(async () => { throw Error('expected cleanup failure'); });
            await tick();
            assert(errors === 1, 'Late async cleanup rejection is contained and reported');
            unasync();

            let finishCleanup;
            const stop = app.register('concurrent', () => () => new Promise(resolve => { finishCleanup = resolve; }));
            await app.mount(first);
            const second = document.createElement('main'), third = document.createElement('main');
            const replaceSecond = app.mount(second);
            await Promise.resolve();
            const replaceThird = app.mount(third);
            await replaceThird;
            finishCleanup(); await replaceSecond;
            assert(app.current.root === third, 'A stale asynchronous teardown cannot replace a newer root');
            stop();
            return { checks, asyncCleanup, errors };
        });
        console.log(JSON.stringify({ test: 'page-lifecycle-runtime', ...result }));
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
