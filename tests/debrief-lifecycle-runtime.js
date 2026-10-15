'use strict';
// Real DOM/resource ownership across repeated mounts of the same page root.
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright-core');
const root = path.resolve(__dirname, '..');
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'].find(fs.existsSync);
let checks = 0;
function assert(value, message) { checks++; if (!value) throw new Error(message); }
// Chromium can retain an empty style attribute after its CSSStyleDeclaration
// has been touched. Compare semantic markup while retaining every actual style.
function semanticMarkup(value) { return value.replace(/ style=""/g, ''); }
function cards() { return ['theme', 'counter', 'career'].map(role => `<article class="lunara-pair-card lunara-pair-card--${role}"><div class="lunara-pair-card-poster">${role} film</div><a href="#${role}">Read film</a></article>`).join(''); }
const html = `<!doctype html><style>
body{margin:0}main{padding:30px}.lunara-debrief-orbit-stage{height:180px;position:relative}[data-orbit-slide]{position:absolute;inset:0;display:flex;gap:30px}[data-orbit-slide]:not(.is-active){visibility:hidden}.lunara-debrief-orbit-poster{width:80px;height:100px}.lunara-pair-cards{position:relative;padding-left:30px}.lunara-pair-cards-grid{display:flex;gap:30px}.lunara-pair-card{width:180px;min-height:150px}.lunara-pair-card-poster{height:100px}.lunara-pair-thread{position:absolute}
</style><main id="main"><section><a data-orbit-caption href="#original">Original caption</a><div data-debrief-orbit data-orbit-hold="90000"><div class="lunara-debrief-orbit-stage">${[0,1].map(i=>`<div data-orbit-slide class="${i===0?'is-active is-drawn':''}" data-review-title="Review ${i}" data-review-url="#review-${i}">${['centre','echo','counter','context'].map(role=>`<a href="#film-${i}-${role}" data-orbit-node="${role}"><div class="lunara-debrief-orbit-poster">Film ${i} ${role}</div></a>`).join('')}<span class="lunara-debrief-orbit-line" data-from="centre" data-to="echo"></span></div>`).join('')}</div><div data-orbit-controls hidden><button data-orbit-go="0" aria-current="true">First</button><button data-orbit-go="1">Second</button><button data-orbit-toggle aria-pressed="false">Pause</button></div></div></section><section class="lunara-review-single-debrief-section"><div class="lunara-pair-cards"><h2 class="lunara-pair-cards-head">Pair It With</h2><div class="lunara-pair-cards-grid">${cards()}</div></div></section></main><aside class="lunara-review-single-debrief-section"><div id="outside" class="lunara-pair-cards"><div class="lunara-pair-cards-grid">${cards()}</div></div></aside>`;
async function script(page, name) { await page.addScriptTag({content:fs.readFileSync(path.join(root,'assets/js',name),'utf8')}); }
(async()=>{
    const browser = await chromium.launch({headless:true,executablePath});
    try {
        for (const reducedMotion of ['no-preference','reduce']) {
            const page = await browser.newPage({viewport:{width:390,height:844},reducedMotion});
            await page.setContent(html);
            const original = await page.locator('#main').innerHTML();
            // Install Playwright's own action listeners before measuring the
            // application's resources; do not count the browser test driver.
            await page.locator('body').click({position:{x:5,y:5}});
            await page.evaluate(()=>{
                const listeners=[]; const observers=new Set(); const frames=new Set();
                const add=EventTarget.prototype.addEventListener, remove=EventTarget.prototype.removeEventListener;
                EventTarget.prototype.addEventListener=function(type,fn,options){ listeners.push({target:this,type,fn}); return add.call(this,type,fn,options); };
                EventTarget.prototype.removeEventListener=function(type,fn,options){ const i=listeners.findIndex(x=>x.target===this&&x.type===type&&x.fn===fn); if(i>=0)listeners.splice(i,1); return remove.call(this,type,fn,options); };
                for(const name of ['ResizeObserver','IntersectionObserver']){
                    const Native=window[name]; window[name]=class extends Native{constructor(fn){super(fn);observers.add(this);}disconnect(){observers.delete(this);super.disconnect();}};
                }
                const request=window.requestAnimationFrame.bind(window), cancel=window.cancelAnimationFrame.bind(window);
                window.requestAnimationFrame=fn=>{let id=request(time=>{frames.delete(id);fn(time);});frames.add(id);return id;};
                window.cancelAnimationFrame=id=>{frames.delete(id);cancel(id);};
                window.resources=()=>{
                    // Playwright can reinstall its injected hit-target checker
                    // after the instrumented listener methods change. Exclude
                    // only those identified test-driver callbacks, not app events.
                    const owned=listeners.filter(x=>x.type!=='__playwright_global_listeners_check__'&&!String(x.fn).includes('this._hitTargetInterceptor'));
                    return {listeners:owned.length,observers:observers.size,frames:frames.size,types:owned.map(x=>x.type+':'+String(x.fn).slice(0,70))};
                };
            });
            await script(page,'lunara-page-lifecycle.js');
            await script(page,'lunara-debrief-orbit.js');
            await script(page,'lunara-pair-thread.js');
            for(let cycle=0;cycle<3;cycle++){
                if(cycle)await page.evaluate(()=>window.LunaraApp.mount(document.querySelector('#main')));
                await page.waitForFunction(()=>document.querySelector('[data-debrief-orbit]').lunaraOrbit && document.querySelector('#main .lunara-pair-cards').lunaraPairThread);
                assert(await page.locator('#main .lunara-pair-thread').count()===1,`${reducedMotion}: one generated thread on mount ${cycle}`);
                assert(await page.evaluate(()=>!document.querySelector('#outside').lunaraPairThread),`${reducedMotion}: scope excludes outside content`);
                await page.locator('[data-orbit-go="1"]').click();
                assert(await page.evaluate(()=>document.querySelector('[data-debrief-orbit]').lunaraOrbit.index)===1,`${reducedMotion}: chosen film changes`);
                await page.locator('[data-orbit-go="1"]').press('ArrowLeft');
                assert(await page.evaluate(()=>document.querySelector('[data-debrief-orbit]').lunaraOrbit.index)===0,`${reducedMotion}: keyboard remains functional`);
                await page.evaluate(()=>window.LunaraApp.mount(document.querySelector('#main')));
                assert(await page.locator('#main .lunara-pair-thread').count()===1,`${reducedMotion}: same active root does not duplicate markup`);
                await page.evaluate(()=>window.LunaraApp.destroy('test-remount'));
                const resources=await page.evaluate(()=>window.resources());
                assert(resources.listeners===0&&resources.observers===0&&resources.frames===0,`${reducedMotion}: no retained resource after destroy ${cycle}: ${JSON.stringify(resources)}`);
                const restored=await page.locator('#main').innerHTML();
                assert(semanticMarkup(restored)===semanticMarkup(original),`${reducedMotion}: restore server markup/attributes after destroy ${cycle}`);
                assert(await page.evaluate(()=>!document.querySelector('[data-debrief-orbit]').lunaraOrbit&&!document.querySelector('#main .lunara-pair-cards').lunaraPairThread),`${reducedMotion}: dispose instance handles`);
            }
            await page.close();
        }
        const standalone=await browser.newPage({reducedMotion:'reduce'});
        await standalone.setContent(html);
        await script(standalone,'lunara-debrief-orbit.js');await script(standalone,'lunara-pair-thread.js');
        assert(await standalone.evaluate(()=>!!document.querySelector('[data-debrief-orbit]').lunaraOrbit && !!document.querySelector('#main .lunara-pair-cards').lunaraPairThread),'Standalone documents still initialize without the application bus');
        await standalone.close();
    }finally{await browser.close();}
    console.log(`Debrief lifecycle: ${checks} assertions passed.`);
})().catch(error=>{console.error(error);process.exitCode=1;});
