'use strict';
// Real Studio renderer/controller; transport is simulated, persistence is covered by the PHP owner test.
const { fixture } = require('./site-studio-browser-fixture');
const { chromium } = require('playwright-core');
const assert = require('node:assert/strict');
(async () => {
 const browser = await chromium.launch({ headless: true, executablePath: process.env.LUNARA_BROWSER_EXECUTABLE });
 try {
  for (const width of [390, 1440]) {
   const page = await browser.newPage({ viewport: { width, height: 1000 } });
   let saved, previous, submitted, calls = 0;
   page.on('dialog', dialog => dialog.accept());
   await page.route('https://example.test/**', async route => {
    const url = new URL(route.request().url());
    if (url.pathname.includes('/wp-admin/')) {
     let html = fixture('global-design');
     html = html.replace(/(<script[^>]* id="lunara-site-studio-state"[^>]*>)([\s\S]*?)(<\/script>)/, (_, a, value, b) => {
      saved = saved || JSON.parse(value); return a + JSON.stringify(saved) + b;
     });
     return route.fulfill({ contentType: 'text/html', body: html });
    }
    if (url.pathname.endsWith('/revisions')) return route.fulfill({ json: { revisions: [{ id: 'prior-pace', timestamp: '2026-10-15', action: 'save' }] } });
    if (url.pathname.includes('/surfaces/')) {
     calls++; submitted = route.request().postDataJSON();
     if (url.pathname.endsWith('/preview')) return route.fulfill({ json: { url: 'https://example.test/?lunara_global_design_preview=123e4567-e89b-42d3-a456-426614174111' } });
     if (url.pathname.endsWith('/save')) {
      previous = structuredClone(saved); saved.slider_seconds = submitted.state.slider_seconds;
      return route.fulfill({ json: { state: saved, changed_sections: ['slider_seconds'], revision_id: 'prior-pace', timestamp: '2026-10-15' } });
     }
     if (url.pathname.endsWith('/restore')) {
      saved = previous;
      return route.fulfill({ json: { state: saved, safety_revision_id: 'safety', timestamp: '2026-10-15' } });
     }
    }
    return route.fulfill({ contentType: 'text/html', body: '<!doctype html><body>Private preview fixture</body>' });
   });
   await page.goto('https://example.test/wp-admin/admin.php?page=lunara-site-studio&surface=global-design');
   await page.waitForSelector('[data-lunara-site-studio-ready="true"]');
   const field = page.locator('[data-field-path="slider_seconds"]');
   const state = expected => page.waitForFunction(value => document.querySelector('[data-lunara-site-studio]').dataset.workspaceState === value, expected);
   assert.equal(await field.inputValue(), '12');
   for (const invalid of ['7', '31', '12.5', '']) {
    await field.fill(invalid); const before = calls;
    await page.locator('[data-action="save"]').click();
    assert.equal(calls, before, 'Invalid pace must never reach the save endpoint.');
    assert.equal(await field.evaluate(node => node.validity.valid), false);
   }
   await field.fill('18'); await page.locator('[data-action="preview"]').click(); await state('preview-current');
   assert.equal(submitted.state.slider_seconds, 18); assert.equal(saved.slider_seconds, 12, 'Preview does not save.');
   await page.locator('[data-action="save"]').click(); await state('live-saved');
   await page.reload(); await page.waitForSelector('[data-lunara-site-studio-ready="true"]');
   assert.equal(await field.inputValue(), '18');
   await field.fill('24'); await page.locator('[data-action="discard"]').click(); assert.equal(await field.inputValue(), '18');
   await page.locator('[data-revision-history]').evaluate(node => node.open = true);
   await page.locator('[data-action="restore"]').first().click(); await state('restored');
   assert.equal(await field.inputValue(), '12');
   assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
   await page.close();
  }
  console.log('Shared pace Studio: mobile/desktop bounds, Preview, Apply, reload, Discard and History passed.');
 } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
