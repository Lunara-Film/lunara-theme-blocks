'use strict';
// Focused component simulations for the annotated defects. Real renderer record
// membership and the full footer are exercised in their existing template suites.
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const {chromium}=require('playwright-core');
const root=path.resolve(__dirname,'..');
const read=f=>fs.readFileSync(path.join(root,f),'utf8');
const common=['style.css','assets/css/lunara-shell.css','assets/css/lunara-review-components.css','assets/css/lunara-public-guardrails.css'].map(read).join('\n');
const art='<svg xmlns="http://www.w3.org/2000/svg" width="200" height="300"><rect width="200" height="300" fill="#243745"/></svg>';
const poster=`<img src="data:image/svg+xml,${encodeURIComponent(art)}" alt="Film poster">`;
const pair=['Theme Echo','Counter Program','Career Context'].map((role,i)=>`<article class="lunara-pair-card"><div class="lunara-pair-card-poster">${poster}</div><div class="lunara-pair-card-body"><p class="lunara-pair-card-role">${role}</p><h3 class="lunara-pair-card-title">Film ${i+1}</h3><p class="lunara-pair-card-note">${'A complete critical argument with meaningful detail. '.repeat(i+2)}</p><div class="lunara-pair-card-chips"><a href="#review" class="lunara-pair-card-chip lunara-pair-card-chip--review">Read Lunara Review</a><a href="#film" class="lunara-pair-card-chip">IMDb</a></div></div></article>`).join('');
const home=`<main id="primary" class="lunara-front-page"><div class="lunara-home-cinematic-front-door is-native-fallback"><section class="lunara-home-hero lunara-home-curated-hero lunara-cinematic-hero"><div class="lunara-cinematic-hero-track"><div class="lunara-cinematic-hero-slide"><a href="#story" class="lunara-cinematic-hero-link"><div class="lunara-cinematic-hero-bg">${poster}</div><div class="lunara-cinematic-hero-shell"><div class="lunara-cinematic-hero-content"><h1 class="lunara-cinematic-hero-title">A film headline that needs room to breathe</h1><p class="lunara-cinematic-hero-excerpt">A short introduction with a complete thought.</p><span class="lunara-cinematic-hero-cta">Read the story</span></div></div></a></div></div></section></div><section class="lunara-home-section lunara-home-slot-dispatch"><h2 class="lunara-home-section-title">Fresh movement from the Lunara Journal</h2></section><section class="lunara-pairing-desk-section"><h2 class="lunara-home-section-title">The Lunara Method</h2><div class="lunara-pair-cards"><div class="lunara-pair-cards-grid" data-count="3">${pair}</div></div></section></main>`;
const research=`<main id="primary" class="lunara-oscars-portal"><section class="lunara-home-section lunara-oscars-portal-research"><h2 class="lunara-home-section-title">Research Mode</h2><div class="lunara-oscars-research-shell is-landing-view"><div class="aat-container aat-database-landing-shell"><div class="aat-header aat-database-landing-header"><h2>The Lunara Oscar Ledger</h2><p class="aat-subtitle">Every complete record.</p></div><section class="aat-hub-section aat-ceremony-gallery-section"><h2>Poster Highlights</h2><p class="aat-hub-copy">Six films from the latest ceremony.</p><div class="aat-filmography-grid"><article class="aat-filmography-card"><div class="aat-filmography-poster-wrap">${poster}</div><h3 class="aat-filmography-title"><a href="#film">A long and complete film title</a></h3></article></div></section></div></div></section></main>`;
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.LUNARA_BROWSER_EXECUTABLE||['C:/Program Files/Google/Chrome/Application/chrome.exe','/usr/bin/google-chrome'].find(fs.existsSync)});
 let checks=0;
 try{
  for(const width of [390,690,1187,1440])for(const route of ['home','oscars']){
   const page=await browser.newPage({viewport:{width,height:900},javaScriptEnabled:false,reducedMotion:'reduce'});
   await page.route('**/*',r=>r.abort());
   const routeCss=(route==='home'?['assets/css/lunara-home-modules.css','assets/css/lunara-cinematic-home.css','assets/css/lunara-home-carousels.css']:['assets/css/lunara-oscars-portal.css']).map(read).join('\n');
   await page.setContent(`<style>*,*::before,*::after{box-sizing:border-box}body{margin:0}img{max-width:100%}</style><style>${common}\n${routeCss}</style><body class="${route==='home'?'home':'lunara-oscars-portal-page'}">${route==='home'?home:research}</body>`);
   const data=await page.evaluate(route=>{
    const rect=e=>{const r=e.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height,b:r.bottom}};
    const q=s=>document.querySelector(s),style=s=>getComputedStyle(q(s));
    const base={overflow:document.documentElement.scrollWidth-innerWidth,heads:[...document.querySelectorAll('.lunara-home-section-title')].map(e=>{const s=getComputedStyle(e);return [s.fontFamily,s.fontSize,s.fontWeight,s.lineHeight].join('|')})};
    if(route==='home')return {...base,hero:rect(q('.lunara-home-cinematic-front-door')),track:rect(q('.lunara-cinematic-hero-track')),posters:[...document.querySelectorAll('.lunara-pair-card-poster')].map(rect),roles:[...document.querySelectorAll('.lunara-pair-card-role')].map(e=>e.textContent),targets:[...document.querySelectorAll('.lunara-pair-card-chip')].map(rect),rules:[...document.querySelectorAll('.lunara-pair-card-chips')].map(e=>parseFloat(getComputedStyle(e).borderTopWidth))};
    return {...base,shell:rect(q('.aat-container')),head:rect(q('.aat-hub-section > h2')),film:rect(q('.aat-filmography-card')),caption:rect(q('.aat-filmography-title')),headingFace:style('.aat-hub-section > h2').fontFamily,sectionFace:style('.lunara-home-section-title').fontFamily,bodyWeight:style('.aat-subtitle').fontWeight,frame:style('.lunara-oscars-portal-research').borderTopWidth,line:getComputedStyle(q('.aat-header'),'::after').content};
   },route);
   assert(data.overflow<=1,`${route}/${width}: no document overflow`);checks++;
   if(route==='home'){
    assert(new Set(data.heads).size===1,`${width}: shared section typography`);
    assert(data.hero.b-data.track.b<=12,`${width}: no unused band below hero: ${JSON.stringify(data)}`);
    assert(data.posters.every(r=>r.w<=260.1&&Math.abs(r.w/r.h-2/3)<.01),`${width}: bounded portrait artwork`);
    assert(data.targets.every(r=>r.h>=44),`${width}: source links remain touchable`);
    assert(data.rules.every(w=>w>0),`${width}: each argument has an attached source rail`);
    assert.deepEqual(data.roles,['Theme Echo','Counter Program','Career Context']);checks+=6;
   }else{
    assert.equal(data.headingFace,data.sectionFace,`${width}: embedded heading inherits house display role`);
    assert.equal(data.bodyWeight,'400',`${width}: description uses reading weight`);
    assert.equal(data.frame,'0px',`${width}: research is one canvas`);
    assert(['none','normal'].includes(data.line),`${width}: decorative command line retired`);
    assert(Math.abs(data.film.x+data.film.w/2-data.caption.x-data.caption.w/2)<=1,`${width}: film title centered beneath artwork`);
    assert(data.head.x-data.shell.x>=19&&data.shell.x+data.shell.w-data.head.x-data.head.w>=19,`${width}: heading has reading gutters`);checks+=6;
   }
   await page.close();
  }
 }finally{await browser.close()}
 console.log(`Annotated editorial layout: ${checks} checks passed across phone, tablet and desktop.`);
})().catch(e=>{console.error(e);process.exit(1)});
