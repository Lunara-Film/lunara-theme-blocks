'use strict';
// Narrow Theme-owned contract: the real early seed and late Studio emitter
// must honor saved artwork choices in the shell cascade. This deliberately
// does not copy a companion plugin's CSS or pretend to boot WordPress.
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const assert = require('node:assert/strict');
const {chromium} = require('playwright-core');
const root = path.resolve(__dirname, '..');
const browserPath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/google-chrome'].find(fs.existsSync);
const styles = ['style.css', 'assets/css/lunara-shell-non-portal.css', 'assets/css/oscars.css', 'assets/css/lunara-oscars-ledger.css', 'assets/css/lunara-public-guardrails.css', 'assets/css/lunara-oscars-late-guardrails.css'].map(file => fs.readFileSync(path.join(root, file), 'utf8'));
const cases = [
  {name:'default', mods:{}, fit:'cover', focus:'50% 50%', aspect:2/3},
  {name:'archival', mods:{lunara_oscars_profile_media_treatment:'archival-fit', lunara_oscars_title_image_focus:'center-top', lunara_oscars_profile_media_width:400, lunara_oscars_profile_media_height:600}, fit:'contain', focus:'50% 0%', aspect:2/3},
  {name:'cinematic', mods:{lunara_oscars_profile_media_treatment:'cinematic-crop', lunara_oscars_title_image_focus:'right-center', lunara_oscars_profile_media_width:320, lunara_oscars_profile_media_height:500}, fit:'cover', focus:'100% 50%', aspect:4/5}
];
(async () => {
  let checks = 0;
  const check = (value, message) => { checks++; assert.ok(value, message); };
  const browser = await chromium.launch({headless:true, ...(browserPath ? {executablePath:browserPath} : {})});
  try {
    for (const spec of cases) {
      const head = JSON.parse(execFileSync('php', [path.join(__dirname, 'fixtures/oscars-ledger-overlay-harness.php'), '--fixture', Buffer.from(JSON.stringify(spec.mods)).toString('base64')], {encoding:'utf8'}));
      for (const width of [390,1440]) {
        const page = await browser.newPage({viewport:{width,height:900}, reducedMotion:'reduce', javaScriptEnabled:false});
        await page.route('**/*', route => route.abort());
        const html = `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">${head.vars}${head.critical}<style>${styles.slice(0,4).join('\n')}</style>${head.studio}<style>${styles.slice(4).join('\n')}</style></head><body class="aat-shell-page"><main id="main"><article class="aat-container aat-profile-file"><section class="aat-entity-hero"><div class="aat-entity-poster-wrap"><img width="400" height="600" class="aat-entity-poster" alt="Chosen artwork"></div><div class="aat-entity-summary"><h1>One saved film identity</h1><a class="aat-btn" href="#history">Oscar history</a></div></section><div id="history">Complete history remains available.</div></article><section class="aat-container"><div class="aat-filmography-poster-wrap"><img width="120" height="180" alt="Ceremony or category artwork"></div></section></main></body></html>`;
        await page.setContent(html);
        const snapshot = () => {
          const image = document.querySelector('.aat-entity-poster');
          const media = image.parentElement;
          const s = getComputedStyle(image), box = media.getBoundingClientRect();
          return {fit:s.objectFit, focus:s.objectPosition, aspect:getComputedStyle(media).aspectRatio, width:box.width, height:box.height, background:getComputedStyle(media).backgroundImage, overflow:document.documentElement.scrollWidth};
        };
        const before = await page.evaluate(snapshot);
        const label = `${spec.name}/${width}`;
        check(before.fit === spec.fit, `${label}: saved fit ${before.fit}`);
        check(before.focus === spec.focus, `${label}: saved focus ${before.focus}`);
        const ratio = before.aspect.split('/').map(Number);
        check(Math.abs(ratio[0]/ratio[1]-spec.aspect)<0.001, `${label}: saved aspect ${before.aspect}`);
        check(before.background === 'none', `${label}: late Studio cannot restore a decorative gradient`);
        check(before.overflow <= width + 1, `${label}: no document overflow`);
        check(await page.locator('.aat-filmography-poster-wrap').evaluate(el=>getComputedStyle(el).animationName==='none'), `${label}: no Ledger shimmer without JavaScript under reduced motion`);
        await page.locator('.aat-btn').focus();
        check(await page.locator('.aat-btn').evaluate(el => getComputedStyle(el).outlineStyle !== 'none'), `${label}: focused action is visible`);
        await page.evaluate(() => document.getElementById('lunara-oscars-ledger-critical-css').remove());
        const after = await page.evaluate(snapshot);
        // Companion plugin geometry is absent from this narrow Theme test.
        // Exact dimensional withdrawal belongs to the combined renderer gate.
        check(after.fit===before.fit && after.focus===before.focus && after.aspect===before.aspect, `${label}: withdrawing the structural seed preserves saved media semantics`);
        check(await page.locator('.aat-btn').evaluate(el => el === document.activeElement), `${label}: CSS withdrawal preserves focus`);
        await page.close();
      }
    }
    console.log(`Oscars Theme integration: ${checks} checks passed (actual early/late emitters, saved artwork, shell cascade).`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
