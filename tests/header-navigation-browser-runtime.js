const assertSquareSurfaces = require('./tools/assert-square-surfaces.cjs');
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright-core');
const root = path.resolve(__dirname, '..');
const fixture = JSON.parse(execFileSync('php', [path.join(__dirname, 'header-navigation-runtime.php'), '--fixture'], {encoding:'utf8'}));
(async () => {
    const executablePath=process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe','/usr/bin/chromium','/usr/bin/chromium-browser','/usr/bin/google-chrome'].find(fs.existsSync);
    const browser = await chromium.launch({headless:true, executablePath});
    let checks = 0;
    try {
        for (const scripts of [true, false]) for (const width of [320,390,768,1021,1280,1440]) {
            const context = await browser.newContext({viewport:{width,height:900}, javaScriptEnabled:scripts, reducedMotion:'reduce'});
            const page = await context.newPage();
            await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box}body{margin:0;background:#09151e;color:#f4efe3}main{padding:20px}</style>${fixture.css}</head><body class="lunara-header-takeover">${fixture.html}<main><h1>A complete article headline</h1><p>Readable content below the shared header.</p></main>${scripts?'<script>'+fs.readFileSync(path.join(root,'assets/js/lunara-header.js'),'utf8')+'</script>':''}</body></html>`);
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth <= innerWidth),true,`No overflow ${width}, JS ${scripts}`); ++checks;
            await assertSquareSurfaces(page, `Header ${width}, JS ${scripts}`);
            const composition = await page.evaluate(() => {
                const box = selector => { const r=document.querySelector(selector).getBoundingClientRect(); return {left:r.left,right:r.right,center:(r.left+r.right)/2,width:r.width}; };
                return {width:innerWidth,brand:box('.lunara-header-brand'),nav:box('.lunara-header-nav'),search:box('.lunara-header-search'),menu:box('.lunara-header-burger')};
            });
            const centered = width<=1020 ? composition.brand : composition.nav;
            assert.ok(Math.abs(centered.center-width/2)<=1, `Centered ${width<=1020?'wordmark':'navigation'} at ${width}, JS ${scripts}`); ++checks;
            if (!scripts) { assert.ok(Math.abs(composition.nav.center-width/2)<=1, `Centered no-JavaScript navigation at ${width}`); ++checks; }
            if (width<=1020) {
                assert.ok(composition.search.right<=composition.brand.left+1, `Mobile search clears the centered wordmark at ${width}`); ++checks;
                if(scripts) { assert.ok(composition.brand.right<=composition.menu.left+1, `Mobile menu clears the centered wordmark at ${width}`); ++checks; }
            } else if(scripts) {
                assert.ok(composition.brand.right<=composition.nav.left+1&&composition.nav.right<=composition.search.left+1, `Desktop navigation clears both side groups at ${width}`); ++checks;
            }
            const search = page.locator('[data-lunara-search-open]');
            const box = await search.boundingBox();
            assert.ok(box.width >=44 && box.height >=44, 'Search touch target'); ++checks;
            assert.equal(await search.getAttribute('href'), 'https://lunara.test/search/'); ++checks;
            if (!scripts || width>1020) {
                assert.equal(await page.locator('.lunara-header-nav').isVisible(), true); ++checks;
                assert.equal(await page.locator('.lunara-header-nav a[aria-current]').textContent(),'Reviews'); ++checks;
            }
            if (scripts && width<1020) {
                const opener=page.locator('[data-lunara-nav-open]');
                await opener.focus(); await page.keyboard.press('Enter');
                await page.locator('#lunara-offcanvas').waitFor({state:'visible'});
                assert.equal(await opener.getAttribute('aria-expanded'),'true'); ++checks;
                assert.equal(await page.locator('.lunara-offcanvas-nav a').count(),4); ++checks;
                await assertSquareSurfaces(page, `Open menu ${width}`);
                const closer=page.locator('.lunara-offcanvas-close');
                for(const control of [opener,closer]) {const target=await control.boundingBox();assert.ok(target.width>=44&&target.height>=44,'Navigation touch target');++checks;}
                await closer.focus(); await page.keyboard.press('Shift+Tab');
                assert.equal(await page.locator('.lunara-offcanvas-nav a').last().evaluate(e=>e===document.activeElement),true,'Focus wraps backwards'); ++checks;
                await page.keyboard.press('Tab');
                assert.equal(await closer.evaluate(e=>e===document.activeElement),true,'Focus wraps forwards'); ++checks;
                await page.keyboard.press('Escape');
                await page.locator('#lunara-offcanvas').waitFor({state:'hidden'});
                assert.equal(await opener.evaluate(e=>e===document.activeElement),true); ++checks;
                await opener.click();
                await page.evaluate(() => window.dispatchEvent(new CustomEvent('lunara:page:teardown')));
                assert.equal(await page.locator('#lunara-offcanvas').evaluate(el=>el.hidden&&!el.classList.contains('is-open')),true,'Navigation closes the persistent menu immediately'); ++checks;
                await page.evaluate(() => new Promise(resolve => requestAnimationFrame(resolve)));
                assert.equal(await opener.getAttribute('aria-expanded'),'false','No pending frame reopens the outgoing menu'); ++checks;
            }
            if(scripts&&width>1020) {
                // Exercise the real logo box and the longer five-destination menu,
                // alongside the renderer's text-wordmark / four-link fixture.
                const fullMenu=await page.evaluate(() => {
                    const brand=document.querySelector('.lunara-header-brand');
                    const logo=document.createElement("img");logo.className="lunara-header-logo";logo.width=420;logo.height=236;logo.alt="Lunara Film";logo.src="data:image/svg+xml,"+encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="420" height="236"></svg>');brand.replaceChildren(logo);
                    const nav=document.querySelector('.lunara-header-nav');
                    const item=document.createElement('li'),link=document.createElement('a');link.href='/lunara-debrief/';link.textContent='Lunara Debrief';item.append(link);nav.append(item);
                    const b=brand.getBoundingClientRect(),n=nav.getBoundingClientRect(),s=document.querySelector('.lunara-header-search').getBoundingClientRect();
                    return {brand:{left:b.left,right:b.right,width:b.width},nav:{left:n.left,right:n.right},search:{left:s.left},width:innerWidth,scroll:document.documentElement.scrollWidth};
                });
                assert.ok(fullMenu.scroll<=width&&Math.abs((fullMenu.nav.left+fullMenu.nav.right)/2-width/2)<=1&&fullMenu.brand.right<=fullMenu.nav.left+1&&fullMenu.nav.right<=fullMenu.search.left+1, `Logo and five-section menu stay centered without collisions at ${width}`);++checks;
            }
            if(process.env.LUNARA_HEADER_ARTIFACTS) {
                fs.mkdirSync(process.env.LUNARA_HEADER_ARTIFACTS,{recursive:true});
                await page.screenshot({path:path.join(process.env.LUNARA_HEADER_ARTIFACTS,`header-${width}-${scripts?'js':'no-js'}.png`),fullPage:true});
            }
            await context.close();
        }
        console.log(`Header browser runtime passed: ${checks} checks.`);
    } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
