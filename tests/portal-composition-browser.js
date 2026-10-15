'use strict';
// Component simulation using the actual portal cascade and PHP structural seed.
// Public-content previews complement this; neither substitutes for deployment acceptance.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright-core');
const root = path.resolve(__dirname, '..');
const css = ['style.css','assets/css/lunara-shell.css','assets/css/lunara-public-guardrails.css','assets/css/lunara-oscars-portal.css'].map(f=>fs.readFileSync(path.join(root,f),'utf8')).join('\n');
const seed = execFileSync('php',[path.join(__dirname,'oscars-portal-studio-runtime.php'),'--emit-portal-critical'],{encoding:'utf8'}).split('\n').slice(1).join('\n');
(async()=>{
    const browser = await chromium.launch({headless:true,executablePath:process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe','/usr/bin/google-chrome'].find(fs.existsSync)});
    let checks=0;
    try {
        for(const width of [390,768,1440]) {
            const page=await browser.newPage({viewport:{width,height:1000},reducedMotion:'reduce',javaScriptEnabled:false});
            await page.route('**/*',r=>r.abort());
            await page.setContent(`<!doctype html><meta name="viewport" content="width=device-width"><style>${css}</style><style id="seed">${seed}</style><body class="lunara-oscars-portal-page"><main id="primary" class="lunara-oscars-portal"><section class="lunara-home-section lunara-oscars-portal-hero lunara-oscars-portal-slot-hero"><div class="lunara-oscars-portal-hero-grid"><div class="lunara-oscars-portal-copy"><h1 class="lunara-home-hero-title">Search every nominee, winner, and race in Oscar history.</h1><div class="lunara-oscars-portal-actions"><a href="#record">Latest ceremony</a><a href="#record">Open full ledger</a></div></div><a id="record" href="#history" class="lunara-oscars-portal-feature-card"><div class="lunara-oscars-portal-feature-poster"></div><div class="lunara-oscars-portal-feature-copy"><h2>A complete long film title with an equally complete award record</h2><p>All evidence and destinations remain readable.</p></div></a></div></section></main>`);
            for(const phase of ['seed','settled']) {
                if(phase==='settled')await page.evaluate(()=>document.getElementById('seed').remove());
                const result=await page.evaluate(()=>{
                    const hero=document.querySelector('.lunara-oscars-portal-hero'),box=hero.getBoundingClientRect(),axis=box.x+box.width/2;
                    return {overflow:document.documentElement.scrollWidth-innerWidth,centers:['h1','.lunara-oscars-portal-feature-card'].map(s=>{const b=document.querySelector(s).getBoundingClientRect();return Math.abs(b.x+b.width/2-axis)}),shafts:['::before','::after'].map(p=>getComputedStyle(hero,p).content),targets:[...document.querySelectorAll('.lunara-oscars-portal-actions a')].map(e=>e.getBoundingClientRect().height)};
                });
                assert(result.overflow<=1,`${width}/${phase}: no horizontal overflow`);
                assert(result.centers.every(n=>n<=1),`${width}/${phase}: shared centered axis ${JSON.stringify(result)}`);
                assert(result.shafts.every(s=>s==='none'||s==='normal'),`${width}/${phase}: no decorative shafts`);
                assert(result.targets.every(n=>n>=44),`${width}/${phase}: usable actions`);
                checks+=4;
            }
            await page.close();
        }
    } finally {await browser.close();}
    console.log(`Portal composition: ${checks} checks passed at phone, tablet and desktop, with and without the structural seed.`);
})().catch(e=>{console.error(e);process.exit(1)});
