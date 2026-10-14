'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');
const root = path.resolve(__dirname, '..');
const seed = execFileSync('php', [path.join(__dirname, 'image-delivery-runtime.php'), '--entity-seed'], { encoding: 'utf8' });
const css = fs.readFileSync(path.join(root, 'style.css'), 'utf8');
const properties = {
    '.lunara-entity-grid': ['display','gridTemplateColumns','gap','marginTop'],
    '.lunara-entity-hero-inner': ['position','zIndex','display','gap','alignItems','padding'],
    '.lunara-entity-hero-poster': ['flexBasis','aspectRatio','overflow','width'],
    '.lunara-entity-body > section': ['marginTop'],
    '.lunara-entity-awards': ['padding','borderTopWidth','borderTopColor','borderRadius'],
    '.lunara-entity-award': ['display','flexWrap','alignItems','gap','padding'],
    '.lunara-entity-award-state': ['marginLeft','padding'],
};
const markup = '<main style="width:100%"><div class="lunara-entity-hero-inner"><div class="lunara-entity-hero-poster"></div></div><div class="lunara-entity-body"><div></div><section class="lunara-entity-awards"><div class="lunara-entity-award"><span class="lunara-entity-award-state" style="width:100px">Won</span></div></section></div><div class="lunara-entity-grid"><div>Film</div><div>Person</div></div></main>';
(async () => {
    const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe','/usr/bin/google-chrome'].find(fs.existsSync);
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        for (const width of [320,390,768,1440]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 } });
            await page.setContent('<style>*{box-sizing:border-box}body{margin:0}</style>' + seed + markup);
            const measure = () => page.evaluate(properties => Object.fromEntries(Object.entries(properties).map(([selector, props]) => {
                const css = getComputedStyle(document.querySelector(selector));
                return [selector, Object.fromEntries(props.map(prop => [prop, css[prop]]))];
            })), properties);
            const initial = await measure();
            // Remove the seed to test the final stylesheet's own declarations.
            await page.locator('#lunara-entity-geometry-css').evaluate(el => el.remove());
            await page.addStyleTag({ content: css });
            assert.deepEqual(await measure(), initial, `Dossier seed and final geometry agree at ${width}px`);
            assert.equal(initial['.lunara-entity-awards'].borderRadius, '0px', 'First paint is square too');
            await page.close();
        }
    } finally { await browser.close(); }
    console.log('Entity seed/final computed geometry: four viewport sizes passed.');
})().catch(error => { console.error(error); process.exit(1); });

