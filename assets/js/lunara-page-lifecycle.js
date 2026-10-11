/* Public component ownership. The native document lifecycle remains the fallback. */
(function () {
    'use strict';
    if (window.LunaraApp && window.LunaraApp.version === 1) return;
    var modules = new Map();
    var page = null;
    var sequence = 0;
    var generation = 0;

    function emit(name, detail) { window.dispatchEvent(new CustomEvent(name, { detail: detail })); }
    function report(name, error) { emit('lunara:page:error', { component: name, error: error }); }
    function scope(state, name) {
        var controller = new AbortController();
        var disposers = [];
        var timeouts = new Set(), intervals = new Set(), frames = new Set();
        var context = Object.assign({}, state.detail, {
            signal: controller.signal,
            cleanup: function (fn) {
                if (typeof fn !== 'function') return fn;
                if (controller.signal.aborted) { Promise.resolve().then(fn).catch(function (error) { report(name, error); }); }
                else disposers.push(fn);
                return fn;
            },
            on: function (target, type, fn, options) {
                if (!target || controller.signal.aborted) return fn;
                target.addEventListener(type, fn, options);
                context.cleanup(function () { target.removeEventListener(type, fn, options); });
                return fn;
            },
            timeout: function (fn, ms) {
                if (controller.signal.aborted) return null;
                var id = window.setTimeout(function () { timeouts.delete(id); if (!controller.signal.aborted) fn(); }, ms);
                timeouts.add(id); return id;
            },
            interval: function (fn, ms) {
                if (controller.signal.aborted) return null;
                var id = window.setInterval(function () { if (!controller.signal.aborted) fn(); }, ms);
                intervals.add(id); return id;
            },
            frame: function (fn) {
                if (controller.signal.aborted) return null;
                var id = window.requestAnimationFrame(function (time) { frames.delete(id); if (!controller.signal.aborted) fn(time); });
                frames.add(id); return id;
            },
            observe: function (observer) {
                if (observer && typeof observer.disconnect === 'function') context.cleanup(function () { observer.disconnect(); });
                return observer;
            }
        });
        return {
            context: context,
            dispose: async function () {
                if (controller.signal.aborted) return;
                controller.abort();
                timeouts.forEach(function (id) { window.clearTimeout(id); });
                intervals.forEach(function (id) { window.clearInterval(id); });
                frames.forEach(function (id) { window.cancelAnimationFrame(id); });
                timeouts.clear(); intervals.clear(); frames.clear();
                for (var fn of disposers.reverse()) {
                    try { await fn(); } catch (error) { report(name, error); }
                }
                disposers.length = 0;
            }
        };
    }
    function mountModule(name, module, state) {
        if (state !== page || state.instances.has(name)) return Promise.resolve();
        var instance = scope(state, name);
        state.instances.set(name, instance);
        instance.pending = Promise.resolve().then(function () {
            if (instance.context.signal.aborted) return;
            return module(state.detail.root, instance.context);
        }).then(async function (cleanup) {
            if (typeof cleanup !== 'function') return;
            // An asynchronous mount can finish after its page was removed.
            if (instance.context.signal.aborted) await cleanup();
            else instance.context.cleanup(cleanup);
        }).catch(function (error) { report(name, error); return instance.dispose(); });
        return instance.pending;
    }
    var app = {
        version: 1,
        has: function (name) { return modules.has(name); },
        register: function (name, mount) {
            if (typeof name !== 'string' || !name || typeof mount !== 'function') throw new TypeError('A component name and mount function are required.');
            // Re-loading an already registered application asset is harmless.
            if (modules.has(name)) return function () {};
            modules.set(name, mount);
            if (page) mountModule(name, mount, page);
            return function () {
                if (modules.get(name) !== mount) return;
                modules.delete(name);
                if (page && page.instances.has(name)) {
                    var instance = page.instances.get(name);
                    page.instances.delete(name);
                    instance.dispose();
                }
            };
        },
        mount: async function (root, details) {
            root = root || document.querySelector('#main');
            if (!root) return;
            if (page && page.detail.root === root) return page.pending;
            var ticket = ++generation;
            if (page) await teardown('replace');
            if (ticket !== generation) return;
            var state = {
                detail: Object.assign({ root: root, url: window.location.href, title: document.title, reason: 'navigation', initial: false, visitId: ++sequence }, details || {}, { root: root }),
                instances: new Map(),
                pending: null
            };
            page = state;
            state.pending = Promise.all(Array.from(modules, function (entry) { return mountModule(entry[0], entry[1], state); })).then(function () {
                if (page === state) emit('lunara:page:mount', state.detail);
            });
            return state.pending;
        },
        destroy: async function (reason) {
            generation++;
            await teardown(reason);
        }
    };
    async function teardown(reason) {
            var old = page;
            if (!old) return;
            page = null;
            emit('lunara:page:teardown', Object.assign({}, old.detail, { reason: reason || 'navigation' }));
            await Promise.all(Array.from(old.instances.values()).reverse().map(function (instance) { return instance.dispose(); }));
            old.instances.clear();
    }
    Object.defineProperty(app, 'current', { get: function () { return page ? page.detail : null; } });
    window.LunaraApp = app;
    function start() { app.mount(document.querySelector('#main'), { reason: 'initial', initial: true }); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
}());
