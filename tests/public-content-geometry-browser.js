'use strict';
const assert = require('node:assert/strict'), fs = require('node:fs'), path = require('node:path');
const {execFileSync} = require('node:child_process'), {chromium} = require('playwright-core');
const square = require('./tools/assert-square-surfaces.cjs');
const root = path.resolve(__dirname,'..');
const content = execFileSync('php',[path.join(__dirname,'public-content-geometry-runtime.php'),'--fixture'],{encoding:'utf8'});
const base = fs.readFileSync(path.join(root,'assets/css/lunara-base.css'),'utf8');
const theme = JSON.parse(fs.readFileSync(path.join(root,'theme.json'),'utf8'));
(async()=>{
    const executablePath=process.env.LUNARA_BROWSER_EXECUTABLE||['C:/Program Files/Google/Chrome/Application/chrome.exe','/usr/bin/google-chrome'].find(fs.existsSync);
    const browser=await chromium.launch({headless:true,executablePath});
    try {
        const page=await browser.newPage();
        for(const width of [320,768,1440]) for(const route of ['page','single','archive','search','error404']) {
            await page.setViewportSize({width,height:900});
            // WordPress default pill/round rules deliberately precede the actual
            // scaffold; this fixture tests the integration, not a WP deployment.
            await page.setContent(`<style>.wp-block-button__link{border-radius:9999px}.wp-block-image.is-style-rounded img{border-radius:9999px}</style><style>${base}</style><body class="${route}"><main class="entry-content">${content}<div class="wp-block-button"><a class="wp-block-button__link" href="#new">New button with no saved style</a></div><form class="wp-block-search"><input class="wp-block-search__input"><button class="wp-block-search__button">Search</button></form></main></body>`);
            await square(page,`${route} ${width}`);
            await page.locator('.wp-block-search__button').focus();
            await square(page,`Focused ${route} ${width}`);
        }
        assert.equal(theme.settings.border.radius,false,'New content editor defaults disallow rounded frames');
        assert.equal(theme.styles.elements.button.border.radius,'0','Global button default is square');
        assert.ok(Object.values(theme.styles.blocks).every(block=>block.border.radius==='0'),'Every declared structural block defaults to square');
        console.log('Publication content geometry: 15 route/viewport cases, focused controls and editor defaults passed.');
    } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
