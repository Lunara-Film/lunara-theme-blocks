'use strict';
// CSS policy uses the browser parser. Unknown new public stylesheets are covered
// automatically; a new curved component needs an explicit design-contract change.
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const { chromium } = require('playwright-core');
const root = path.resolve(__dirname, '..');
const privateStyles = new Set(['admin.css','lunara-carousel-admin.css','lunara-control-desk.css','lunara-customizer-controls.css','lunara-editor-controls.css','lunara-homepage-editor.css','lunara-site-studio.css','lunara-site-studio-carousels.css']);
const files = fs.readdirSync(path.join(root, 'assets/css')).filter(file => file.endsWith('.css') && !privateStyles.has(file)).map(file => 'assets/css/' + file);
if (fs.existsSync(path.join(root,'style.css'))) files.push('style.css');
const allowed = ['.lunara-debrief-seal','.lunara-debrief-seal::before','.lunara-debrief-seal::after','.lunara-debrief-seal-inner','.lunara-debrief-orbit-plate-mark','.lunara-pair-thread-origin, .lunara-pair-thread-node'];
(async () => {
    const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || process.env.CHROME_PATH || ['C:/Program Files/Google/Chrome/Application/chrome.exe','/usr/bin/google-chrome'].find(fs.existsSync);
    const browser = await chromium.launch({headless:true, ...(executablePath ? {executablePath} : {})});
    try {
        const page = await browser.newPage();
        const inspect = css => page.evaluate(({css,allowed}) => {
            const sheet = new CSSStyleSheet(); sheet.replaceSync(css); const errors = [];
            function walk(rules) { for (const rule of rules) {
                if (rule.style) for (const property of rule.style) {
                    if (!/^border(?:-[a-z]+)*-radius$/.test(property) && !/^--.*radius/.test(property)) continue;
                    const value = rule.style.getPropertyValue(property).trim();
                    if (/^(?:0(?:px|rem|em|%)?\s*)+$/.test(value)) continue;
                    if (allowed.includes(rule.selectorText) && property.startsWith('border-') && value === '50%') continue;
                    errors.push(`${rule.selectorText || rule.cssText.split('{')[0]} ${property}: ${value}`);
                }
                if (rule.cssRules) walk(rule.cssRules);
            }}
            walk(sheet.cssRules); return errors;
        }, {css,allowed});
        for (const file of files) assert.deepEqual(await inspect(fs.readFileSync(path.join(root,file),'utf8')), [], file);
        assert.equal((await inspect('@media(max-width:600px){.new-component:hover{border-start-start-radius:12px}}')).length,1,'New responsive/state curves fail policy');
        assert.equal((await inspect('.new-component{--surface-radius:999px}')).length,1,'New rounded tokens fail policy');
        assert.equal((await inspect('.new-component{border-radius:0}')).length,0,'Square future components pass');
        console.log(`Public geometry policy: ${files.length} stylesheets and three mutation cases passed.`);
    } finally { await browser.close(); }
})().catch(error => {console.error(error);process.exit(1);});
