'use strict';
const fs=require('fs'),path=require('path'),{chromium}=require('playwright-core');
let checks=0;function check(ok,message){checks++;if(!ok){throw Error(message);}}
(async()=>{
 const executablePath=process.env.LUNARA_BROWSER_EXECUTABLE||['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe','/usr/bin/google-chrome'].find(fs.existsSync);
 const browser=await chromium.launch({headless:true,executablePath});const page=await browser.newPage({viewport:{width:390,height:850}});page.setDefaultTimeout(10000);
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 const script=fs.readFileSync(path.join(__dirname,'../assets/js/lunara-pairing-review-editor.js'),'utf8');
 let release,entered;const held=new Promise(r=>{release=r;}),started=new Promise(r=>{entered=r;});let fail=false;
 const html='<input id="post_ID" type="hidden" value="30"><label>Theme Echo<input id="lunara_theme_echo" value="Film | tt1234567"></label><div class="acf-input" data-key="field_lunara_review_counter_program_movie"><label>Counter Program<select><option value="20">Reviewed film</option><option value="21">Other film</option></select></label></div><div id="block"></div>';
 await page.route('https://example.test/**',async route=>{
  const url=new URL(route.request().url());if(url.pathname==='/editor'){return route.fulfill({contentType:'text/html',body:html});}
  if(url.pathname.endsWith('/lookup')){
   check(url.searchParams.get('review_id')==='30','Current review excluded in editor requests.');
   const tt=url.searchParams.get('imdb')||'',movie=url.searchParams.get('movie_id');
   if(tt==='tt3333333'){entered();await held;}
   if(fail){return route.fulfill({status:503,json:{}});}
   const status=tt.includes('tt1234567')||movie==='20'||tt==='tt3333333'?'reviewed':tt||movie==='21'?'unreviewed':'unidentified';
   return route.fulfill({json:{status,url:status==='reviewed'?'https://example.test/reviews/10/':''}});
  }
  return route.fulfill({status:404,body:''});
 });
 try{
  await page.goto('https://example.test/editor');
  await page.evaluate(()=>{window.wp={apiFetch:async({path})=>{const r=await fetch(path);if(!r.ok){throw Error();}return r.json();},url:{addQueryArgs:(p,args)=>{const u=new URL(p,location.origin);Object.entries(args).forEach(([k,v])=>u.searchParams.set(k,v));return u.href;}}};});
  await page.addScriptTag({content:script});
  await page.waitForFunction(()=>document.querySelectorAll('.lunara-pairing-review-status a').length===2);
  check(await page.locator('.lunara-pairing-review-status').count()===2,'Legacy input and canonical film selector both receive indicators.');
  await page.selectOption('select','21');await page.waitForFunction(()=>document.querySelector('.acf-input .lunara-pairing-review-status').textContent.includes('No published'));
  check(await page.locator('.acf-input a').count()===0,'Changing film removes the old review link.');
  await page.fill('#lunara_theme_echo','tt3333333');await started;await page.fill('#lunara_theme_echo','tt2222222');
  await page.waitForFunction(()=>document.querySelector('label .lunara-pairing-review-status').textContent.includes('No published'));release();
  await page.waitForTimeout(80);check(await page.locator('label .lunara-pairing-review-status a').count()===0,'Late response cannot restore a stale film review.');
  fail=true;await page.fill('#lunara_theme_echo','tt4444444');await page.getByRole('button',{name:'Retry',exact:true}).waitFor();
  check((await page.locator('label .lunara-pairing-review-status').textContent()).includes('could not be checked'),'Failed lookup is not presented as no review.');
  fail=false;await page.getByRole('button',{name:'Retry',exact:true}).click();await page.waitForFunction(()=>document.querySelector('label .lunara-pairing-review-status').textContent.includes('No published'));
  await page.fill('#lunara_theme_echo','');await page.waitForFunction(()=>document.querySelector('label .lunara-pairing-review-status').textContent.includes('Choose a film'));
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Private indicators fit phone width.');
  const blockSource=fs.readFileSync(path.join(__dirname,'../assets/js/lunara-pairing-showcase.js'),'utf8');
  const component=blockSource.slice(blockSource.indexOf('function ReviewAvailability('),blockSource.indexOf('function PairingEditor('));
  await page.evaluate(source=>{
   const effects=[];const element={useRef:()=>({current:null})},useEffect=fn=>effects.push(fn),__=value=>value;
   const el=(tag,props)=>{const node=document.createElement(tag);props.ref.current=node;return node;};
   window.wp.data={select:()=>({getCurrentPostId:()=>30})};
   const render=eval('('+source.trim()+')');document.getElementById('block').appendChild(render({imdb:'tt1234567'}));
   window.cancelBlock=effects[0]();
  },component);
  await page.locator('#block a').waitFor();check(await page.locator('#block a').getAttribute('target')==='_blank','Indicator link preserves unsaved editor in its original tab.');
  check(errors.length===0,'No browser exceptions: '+errors.join(', '));
  console.log('Pairing editor browser: '+checks+' checks passed.');
 }finally{release();await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
