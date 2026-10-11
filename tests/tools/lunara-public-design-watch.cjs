'use strict';
// Read-only, bounded public acceptance. No crawling, writes, cache purges or retries.
const fs = require('node:fs'), path = require('node:path');
const { chromium } = require('playwright-core');

async function inspectPage(page) {
    return page.evaluate(() => {
        const visible = node => { const s=getComputedStyle(node); return node.getClientRects().length && s.display!=='none' && s.visibility!=='hidden'; };
        const flatSelectors = 'body,#main-container,main#main,.lunara-footer-nav-col,.lunara-journal-archive-card,.lunara-pair-card,.lunara-oscar-pick-card,.lunara-home-news-card';
        const surfaces = [...document.querySelectorAll(flatSelectors)].filter(visible).flatMap(node => [null,'::before','::after'].flatMap(pseudo => {
            const s=getComputedStyle(node,pseudo);
            if(pseudo && (s.content==='none'||s.content==='normal'||s.display==='none')) return [];
            return /radial-gradient|repeating-linear-gradient/.test(s.backgroundImage) || s.textShadow!=='none'
                ? [{node:node.tagName+'.'+node.className,pseudo,background:s.backgroundImage,textShadow:s.textShadow}] : [];
        }));
        const metadata = [...document.querySelectorAll('.lunara-footer-nav-col a,.lunara-journal-card-provenance,.lunara-journal-archive-desk-label,.lunara-oscar-pick-card-meta,.lunara-search-overlay-hint')].filter(visible).filter(node => parseFloat(getComputedStyle(node).fontSize)<13).map(node=>({text:node.textContent.trim(),size:getComputedStyle(node).fontSize}));
        return {title:document.title,build:document.querySelector('meta[name="lunara-build"]')?.content||null,
            width:innerWidth,scrollWidth:document.documentElement.scrollWidth,
            mainCount:document.querySelectorAll('main').length,
            themeVersions:[...document.querySelectorAll('link[rel="stylesheet"]')].filter(node=>/lunara.*(?:style|shell|home|journal).*\.css/.test(node.href)).map(node=>({url:node.href,version:new URL(node.href).searchParams.get('ver')})),
            surfaces,metadata};
    });
}
function failuresFor(result, expectedVersion='') {
    const failures=[];
    if(result.status!==200) failures.push('Unexpected HTTP status '+result.status);
    if(!result.build) failures.push('Missing public build marker');
    if(expectedVersion && result.build?.split('+')[0]!==expectedVersion) failures.push('Expected theme '+expectedVersion+', received '+result.build);
    const buildVersion=result.build?.split('+')[0];
    const staleAssets=(result.themeVersions||[]).filter(asset=>/^\d+\.\d+\.\d+(?:[+.-]|$)/.test(asset.version||'')&&asset.version.split('+')[0]!==buildVersion);
    if(staleAssets.length) failures.push('Theme stylesheet versions disagree with the rendered build');
    if(result.mainCount!==1) failures.push('Expected one main landmark');
    if(result.scrollWidth>result.width+1) failures.push('Document overflows the viewport');
    if(result.surfaces.length) failures.push('Retired ornamental surface treatment returned');
    if(result.metadata.length) failures.push('Functional metadata fell below the 13px house floor');
    if(result.pageErrors?.length) failures.push('Unhandled page JavaScript error');
    return failures;
}
async function run() {
    const origin=process.env.LUNARA_WATCH_ORIGIN||'https://lunarafilm.com';
    const expectedVersion=process.env.LUNARA_EXPECTED_THEME_VERSION||'';
    const out=path.resolve(process.env.LUNARA_WATCH_OUT||'test-results/public-design-watch');
    fs.mkdirSync(out,{recursive:true});
    const routes=['/','/journal/','/reviews/','/debrief/','/about/','/film/','/talent/','/oscars/','/oscars/categories/','/oscars/ceremonies/','/search/?q=unlikely-lunara-no-result-20261011'];
    const browser=await chromium.launch({headless:true,...(process.env.LUNARA_BROWSER_EXECUTABLE?{executablePath:process.env.LUNARA_BROWSER_EXECUTABLE}:{channel:'chrome'})});
    const results=[]; let health;
    try {
        for(const route of routes) for(const width of [390,1440]) {
            const page=await browser.newPage({viewport:{width,height:1000},reducedMotion:'reduce'}),pageErrors=[];
            page.on('pageerror',error=>pageErrors.push(error.message));
            const url=new URL(route,origin).href;
            try {
                const response=await page.goto(url,{waitUntil:'load',timeout:45000});
                await page.evaluate(()=>Promise.race([document.fonts.ready,new Promise(resolve=>setTimeout(resolve,3000))]));
                const result={url,width,status:response.status(),pageErrors,...await inspectPage(page)};
                result.failures=failuresFor(result,expectedVersion);
                if(result.failures.length) {result.screenshot=path.join(out,'failure-'+results.length+'.png');await page.screenshot({path:result.screenshot,fullPage:true,animations:'disabled'});}
                results.push(result);
            } catch(error) {results.push({url,width,failures:[error.message]});}
            finally {await page.close();}
        }
        const context=await browser.newContext();
        try {
            const response=await context.request.get(new URL('/wp-json/lunara-ledger/v1/status',origin).href,{timeout:30000});
            const data=await response.json();
            health={status:response.status(),pluginVersion:data.plugin_version,dataset:data.dataset,
                passed:response.ok()&&Number(data.dataset?.nominations)>0};
        } catch(error) {health={passed:false,error:error.message};}
        finally {await context.close();}
    } finally {await browser.close();}
    const builds=[...new Set(results.map(result=>result.build).filter(Boolean))];
    const coherence=builds.length===1?[]:['Representative routes served different build markers: '+builds.join(', ')];
    const report={capturedAt:new Date().toISOString(),expectedVersion:expectedVersion||null,coherence,health,results};
    fs.writeFileSync(path.join(out,'report.json'),JSON.stringify(report,null,2));
    const failed=results.filter(result=>result.failures.length);
    console.log(JSON.stringify({cases:results.length,failed:failed.length,coherence,report:path.join(out,'report.json')},null,2));
    process.exitCode=failed.length||coherence.length||!health.passed?1:0;
}
module.exports={inspectPage,failuresFor};
if(require.main===module)run().catch(error=>{console.error(error);process.exitCode=1;});
