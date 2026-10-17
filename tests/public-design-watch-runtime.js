'use strict';
const assert=require('node:assert/strict');
const {chromium}=require('playwright-core');
const {inspectPage,failuresFor,ledgerFailuresFor}=require('./tools/lunara-public-design-watch.cjs');
(async()=>{
    const browser=await chromium.launch({headless:true,...(process.env.LUNARA_BROWSER_EXECUTABLE?{executablePath:process.env.LUNARA_BROWSER_EXECUTABLE}:{channel:'chrome'})});
    try {
        const page=await browser.newPage({viewport:{width:390,height:844}});
        await page.setContent('<!doctype html><meta name="lunara-build" content="candidate+fixture"><style>body{margin:20px;background:#0a1520;color:#fafbfc}.lunara-footer-curated-list a{font-size:14px}</style><main><h1>Lunara</h1><p>Film criticism.</p></main><footer><nav aria-label="Footer navigation"><ul class="lunara-footer-curated-list"><li><a href="/journal/">Journal</a></li></ul></nav></footer>');
        const baseline={status:200,pageErrors:[],...await inspectPage(page)};
        assert.deepEqual(failuresFor(baseline,'candidate'),[],'A readable flat candidate passes the same public probe.');
        await page.addStyleTag({content:'body{background-image:radial-gradient(#c9a961,transparent)}.lunara-footer-curated-list a{font-size:9px}main{width:700px}'});
        const regression=failuresFor({status:200,pageErrors:[],...await inspectPage(page)},'candidate');
        assert.equal(regression.length,3,'The browser probe catches actual ornament, microtype and overflow regressions together.');
        assert.equal(failuresFor({...baseline,build:'previous+fixture'},'candidate').length,1,'A successful HTTP response cannot hide a stale build.');
        assert.equal(failuresFor({...baseline,build:'1.2.3+fixture',themeVersions:[{url:'https://fixture.test/style.css',version:'1.2.2'}]}).length,1,'A stale versioned stylesheet cannot hide behind the current page marker.');
        assert.equal(failuresFor({...baseline,status:503,pageErrors:['fixture exception']}).length,2,'Availability and script failures remain independently actionable.');
        const ledger={status:200,pluginVersion:'2.3.4',dataset:{nominations:12}};
        assert.deepEqual(ledgerFailuresFor(ledger,'2.3.4'),[],'A healthy Ledger at the explicitly expected companion version passes.');
        assert.deepEqual(ledgerFailuresFor({...ledger,pluginVersion:'2.3.3'},'2.3.4'),['Expected Ledger 2.3.4, received 2.3.3'],'A healthy response with populated records must still reject a stale companion release.');
        await page.close();
        console.log('Public design watch: actual browser success and regression cases passed.');
    } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
