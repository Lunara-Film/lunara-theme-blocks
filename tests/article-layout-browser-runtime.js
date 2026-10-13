'use strict';
/* Real Review/Journal template geometry; JavaScript is disabled throughout. */
const fs = require('fs'), path = require('path'), { execFileSync } = require('child_process');
const { chromium } = require('playwright-core');
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'].find(fs.existsSync);
const root = path.resolve(__dirname, '..');
const reviewOnly=process.env.LUNARA_ARTICLE_SCOPE==='review';
const selectedArticle=process.env.LUNARA_ARTICLE_CASE;
const fixtures = JSON.parse(execFileSync('php', [path.join(__dirname, 'article-layout-runtime.php')], { encoding: 'utf8' }));
const sharedCss = ['style.css', 'assets/css/lunara-shell.css', 'assets/css/lunara-review-components.css', 'assets/css/lunara-public-guardrails.css'].map(file => fs.readFileSync(path.join(root, file), 'utf8')).join('\n');
const journalCss = fs.readFileSync(path.join(root, 'assets/css/lunara-journal-single.css'), 'utf8');
const reviewCss = fs.readFileSync(path.join(root, 'assets/css/lunara-review-single.css'), 'utf8');
const debriefCss = fs.readFileSync(path.join(root, 'assets/css/lunara-debrief-method.css'), 'utf8');
const debriefMethod = execFileSync('php', [path.join(__dirname, 'debrief-method-runtime.php'), '--fixture'], { encoding: 'utf8' });
const pairFixtures = JSON.parse(execFileSync('php', ['-r', `ob_start();require '${path.join(__dirname, 'fixtures/debrief-public-renderer-harness.php').replace(/\\/g, '/')}';ob_end_clean();$canonical['expected_notes']=array_column(lunara_test_record(201)['pairings'],'editorial_reason');$poster_fallback['expected_notes']=array_column(lunara_test_record(206)['pairings'],'editorial_reason');echo json_encode(array($canonical, $poster_fallback));`], { encoding: 'utf8' }));
// Route each real canonical/missing-art fragment through the unchanged Review
// template, so pairing geometry includes the actual Debrief section wrapper.
const pairingTemplates=pairFixtures.map(parts=>JSON.parse(execFileSync('php',['-r',`$GLOBALS['article_pair_parts']=json_decode(stream_get_contents(STDIN),true);function lunara_get_review_debrief_render_parts($id){return $GLOBALS['article_pair_parts'];}require '${path.join(__dirname,'article-layout-runtime.php').replace(/\\/g,'/')}';`],{encoding:'utf8',input:JSON.stringify(parts)}))['review-normal']);
let assertions = 0;
const assert = (condition, message, value) => { assertions++; if (!condition) throw new Error(message + (value ? '\n' + JSON.stringify(value, null, 2) : '')); };
const metrics = [];
const expectedRoleNames = ['Theme Echo', 'Counter Program', 'Career Context'];
// Inspect the rendered glyph ranges, not CSS source or a shortened string fixture.
const fullHeadingMetrics = (page, selector) => page.locator(selector).evaluateAll(elements => elements.map(el => {
    const box=el.getBoundingClientRect(), style=getComputedStyle(el), range=document.createRange();range.selectNodeContents(el);
    const ranges=[...range.getClientRects()], clips=[];
    for(let ancestor=el;ancestor&&ancestor!==document.body;ancestor=ancestor.parentElement){const css=getComputedStyle(ancestor);if(['hidden','clip'].includes(css.overflowX)||['hidden','clip'].includes(css.overflowY)){const r=ancestor.getBoundingClientRect();clips.push({left:r.left,right:r.right,top:r.top,bottom:r.bottom,x:['hidden','clip'].includes(css.overflowX),y:['hidden','clip'].includes(css.overflowY)});}}
    const parent=el.closest('.lunara-pair-card-body,.lunara-debrief-move') || el.parentElement;
    const parentBox=parent.getBoundingClientRect();
    return {text:el.textContent.trim().replace(/\s+/g,' '),visible:box.width>0&&box.height>0&&style.visibility!=='hidden'&&Number(style.opacity)>0,clamp:style.webkitLineClamp,align:style.textAlign,fontSize:parseFloat(style.fontSize),centerDelta:Math.abs(box.left+box.width/2-parentBox.left-parentBox.width/2),fits:ranges.length>0&&ranges.every(r=>r.left>=box.left-1&&r.right<=box.right+1&&clips.every(clip=>(!clip.x||(r.left>=clip.left-1&&r.right<=clip.right+1))&&(!clip.y||(r.top>=clip.top-1&&r.bottom<=clip.bottom+1))))&&el.scrollWidth<=el.clientWidth+1};
}));
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        for (const width of [320, 390, 640, 641, 768, 1440]) {
            for (const [name, fixture] of Object.entries(fixtures)) {
                if ((reviewOnly&&fixture.type==='journal')||(selectedArticle&&!selectedArticle.split(',').includes(name))||(fixture.type==='journal'&&[640,641].includes(width))) continue;
                const page = await browser.newPage({ viewport: { width, height: 1000 }, javaScriptEnabled: false });
                let journalStyleRequests = 0;
                await page.route('**/*', route => {
                    if (route.request().url().includes('/assets/css/lunara-journal-single.css?ver=')) { journalStyleRequests++; return route.fulfill({ contentType: 'text/css', body: journalCss }); }
                    const portrait = route.request().url().endsWith('/poster.svg');
                    const hardCropped = route.request().url().endsWith('/hard-cropped.svg');
                    const w = portrait ? 1000 : 1920, h = portrait ? 1500 : 1080;
                    return route.fulfill({ contentType: 'image/svg+xml', body: `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}"><defs><linearGradient id="sky"><stop stop-color="${hardCropped ? '#9f3355' : '#8ca8bb'}"/><stop offset="1" stop-color="#173342"/></linearGradient></defs><rect width="${w}" height="${h}" fill="url(#sky)"/><rect x="${w * .06}" y="${h * .1}" width="${w * .22}" height="${h * .72}" fill="#dac49a"/><rect x="${w * .64}" y="${h * .26}" width="${w * .26}" height="${h * .7}" fill="#091824"/><path d="M0 ${h * .83}L${w} ${h * .83}" stroke="#c9a961" stroke-width="12"/></svg>` });
                });
                // Blocksy supplies border-box and the outer main shell. Header
                // behavior has its own fixture; this neutral stand-in is68px.
                await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}.fixture-header{height:68px;padding:20px 16px;background:#09151e;color:#dbb761;font:20px Georgia}</style><style>${sharedCss}</style>${fixture.type === 'review' ? '<style>' + reviewCss + '</style>' : ''}${fixture.authority}</head><body class="single single-${fixture.type}"><div id="main-container"><header class="fixture-header">Lunara Film</header><main id="main">${fixture.html}</main></div></body></html>`, { waitUntil: 'load' });
                const data = await page.evaluate(() => {
                    const rect = node => { if (!node) return null; const r = node.getBoundingClientRect(); return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width, height: r.height }; };
                    const title = document.querySelector('h1'), content = document.querySelector('.lunara-review-single-content');
                    const hero = document.querySelector('.lunara-journal-cinematic-hero-frame,.lunara-review-single-cinematic-hero');
                    const img = hero && hero.querySelector('img');
                    const reading = [...content.querySelectorAll('h2,p')];
                    const body=document.querySelector('.lunara-review-single-body'), grid=body.querySelector('.lunara-review-single-body-grid'), rail=document.querySelector('.lunara-review-single-rail-sticky');
                    const reviewLayout=document.body.classList.contains('single-review')?{body:rect(body),grid:rect(grid),columns:getComputedStyle(grid).gridTemplateColumns,rowGap:parseFloat(getComputedStyle(grid).rowGap),bodyPaddingTop:parseFloat(getComputedStyle(body).paddingTop),bodyBefore:getComputedStyle(body,'::before').display,heroMarginBottom:parseFloat(getComputedStyle(document.querySelector('.lunara-review-single-hero')).marginBottom),emptyParagraphs:[...content.querySelectorAll('p:empty')].map(el=>({display:getComputedStyle(el).display,height:el.getBoundingClientRect().height})),studio:document.querySelectorAll('#lunara-review-single-studio-css').length,railColumns:rail?getComputedStyle(rail).gridTemplateColumns:'',railGap:rail?parseFloat(getComputedStyle(rail).gap):null,hiddenRailCards:rail?[...rail.children].filter(el=>getComputedStyle(el).display==='none').length:0,railCards:rail?[...rail.children].filter(el=>el.getBoundingClientRect().height>0).map(rect):[]}:null;
                    const sections=[...document.querySelector('article.lunara-review-single').children].filter(node=>node.getBoundingClientRect().height>0).map(rect);
                    const sectionGaps=sections.slice(1).map((box,index)=>box.top-sections[index].bottom);
                    const clipping = [title, ...reading].flatMap(node => {
                        const bounds = rect(node); const range = document.createRange(); range.selectNodeContents(node);
                        return [...range.getClientRects()].filter(r => r.left < bounds.left - 1 || r.right > bounds.right + 1 || (['hidden','clip'].includes(getComputedStyle(node).overflowY) && r.bottom > bounds.bottom + 1)).map(r => ({ text: node.textContent.slice(0, 80), range: { left: r.left, right: r.right, bottom: r.bottom }, bounds }));
                    });
                    return { reviewLayout, document: document.documentElement.scrollWidth, viewport: innerWidth, h1Count: document.querySelectorAll('h1').length, title: rect(title), titleAlign: getComputedStyle(title).textAlign, rail: rect(document.querySelector('.lunara-review-single-rail')), railLinks: [...document.querySelectorAll('.lunara-review-single-rail a[href]')].map(el=>({href:el.href,visible:el.getClientRects().length>0})), adjacent: [...document.querySelectorAll('.lunara-journal-adjacent-link')].map(rect), subheadings: reading.filter(el=>el.tagName==='H2').map(el=>({box:rect(el),align:getComputedStyle(el).textAlign})), titleText: title.textContent, titleSize: parseFloat(getComputedStyle(title).fontSize), metadata: document.querySelector('.lunara-review-single-meta').textContent, duplicateMetaDots: [...document.querySelectorAll('.lunara-review-single-meta > span')].some(node=>!['none','normal'].includes(getComputedStyle(node,'::after').content)), markers: [...document.querySelectorAll('[data-lunara-site-studio-section]')].map(node=>node.dataset.lunaraSiteStudioSection), galleryControls: [...document.querySelectorAll('.lunara-journal-carousel-btn')].map(rect), content: rect(content), firstParagraph: rect(content.querySelector('p')), paragraphSize: parseFloat(getComputedStyle(content.querySelector('p')).fontSize), hero: rect(hero), image: rect(img), imageFit: img ? getComputedStyle(img).objectFit : '', imagePosition: img ? getComputedStyle(img).objectPosition : '', imageSource: img ? {size:img.dataset.fixtureImageSize,src:img.currentSrc,srcset:img.getAttribute('srcset'),sizes:img.getAttribute('sizes'),loading:img.loading,priority:img.getAttribute('fetchpriority')} : null, natural: img ? { width: img.naturalWidth, height: img.naturalHeight } : null, clipping, sectionGaps, bodyText: content.textContent, linkCount: content.querySelectorAll('a[href]').length };
                });
                const label = `${name}@${width}`;
                assert(journalStyleRequests === (fixture.type === 'journal' ? 1 : 0), `${label}: route loads exactly one versioned Journal stylesheet before layout checks`, journalStyleRequests);
                if (fixture.type === 'journal') assert(fixture.authority.length < 850 && !fixture.authority.includes('!important') && fixture.authority.indexOf('<link ') < fixture.authority.indexOf('<style '), `${label}: HTML carries only the synchronous link and private presentation variables`, fixture.authority);
                assert(data.document <= width + 1, `${label}: no document overflow`, data);
                assert(await page.locator('main').count() === 1 && await page.locator('main > div.lunara-review-single-page,main > div.lunara-journal-single-page').count() === 1, `${label}: one shared main landmark and neutral article wrapper`);
                assert(data.h1Count === 1 && data.titleText === fixture.title, `${label}: one complete server-rendered article heading`, data);
                assert(data.clipping.length === 0, `${label}: full headlines, paragraphs and long links remain readable`, data.clipping);
                assert(data.bodyText.includes('The ending rewards another look.') && data.linkCount > 0, `${label}: complete article and navigation survive without JavaScript`);
                const centered = box => box && Math.abs(box.left + box.width / 2 - width / 2) <= 1;
                assert(centered(data.title) && data.titleAlign === 'center' && centered(data.content), `${label}: heading and readable column share the viewport center`, data);
                assert(data.subheadings.every(heading=>centered(heading.box)&&heading.align==='center'), `${label}: article subheadings stay balanced inside the reading column`, data.subheadings);
                if (fixture.type === 'review') {
                    const layout=data.reviewLayout, expectedCards=fixture.scenario==='with-two-rail'?2:fixture.scenario==='with-rail'?1:0;
                    assert(layout.studio===1, `${label}: the actual late Review Studio authority participates without Additional CSS`, layout);
                    assert(centered(layout.body)&&Math.abs(layout.grid.width-Math.min(760,width-(width<=760?40:48)))<=1&&layout.columns.trim().split(/\s+/).length===1&&layout.rowGap===40, `${label}: one centered 760px reading grid keeps its fixed 40px row gap`, layout);
                    assert(layout.heroMarginBottom===0&&layout.bodyPaddingTop===0&&layout.bodyBefore==='none'&&layout.emptyParagraphs.length===1&&layout.emptyParagraphs.every(p=>p.display==='none'&&p.height===0), `${label}: no stacked opening gap, decorative pseudo-element or empty paragraph chamber`, layout);
                    assert(layout.railCards.length===expectedCards, `${label}: the real template renders exactly the available record cards`, layout);
                    if(expectedCards) {
                        assert(centered(data.rail)&&data.rail.top>=data.content.bottom&&data.railLinks.some(link=>link.visible&&link.href.endsWith('/tt0100001/')), `${label}: record destinations remain visible below the reading column`, data);
                        assert(layout.railColumns.trim().split(/\s+/).length===(width<=640?1:2)&&layout.railGap===12, `${label}: rail changes from two columns to one exactly at640px`, layout);
                        if(expectedCards===1) assert(layout.hiddenRailCards>=2&&centered(layout.railCards[0])&&Math.abs(layout.railCards[0].width-data.rail.width)<=1, `${label}: one visible record spans the centered rail despite hidden Where/Details siblings`, layout);
                        if(expectedCards===2) assert(width<=640?layout.railCards[1].top>=layout.railCards[0].bottom:Math.abs(layout.railCards[1].top-layout.railCards[0].top)<=1, `${label}: both record cards follow the expected visual reading order`, layout);
                    } else assert(data.rail===null, `${label}: no empty rail is emitted`, layout);
                }
                if (data.adjacent.length === 1) assert(centered(data.adjacent[0]), `${label}: one adjacent story centers without a vacant companion cell`, data.adjacent);
                assert(data.paragraphSize >= 16, `${label}: phone body text stays at least16px`, data);
                assert(!data.duplicateMetaDots, `${label}: metadata uses one separator between items`, data);
                if (width <= 640) {
                    assert(data.content.width >= width - 48 && data.content.left >= 20 && data.content.right <= width - 20, `${label}: consistent useful reading width and 20px phone gutters`, data);
                    assert(data.title.width >= width - 48, `${label}: heading uses available phone width`, data);
                    assert(data.sectionGaps.every(gap=>gap>=-1&&gap<=40), `${label}: phone sections have one spacing rhythm without stacked margins`, data);
                }
                if (fixture.art === 'missing') {
                    assert(!data.hero && !data.image, `${label}: missing artwork leaves no empty media frame`, data);
                } else {
                    assert(centered(data.hero) && centered(data.image), `${label}: article artwork shares the page center`, data);
                    assert(data.natural.width > 0 && data.image.width > 0 && data.image.left >= -1 && data.image.right <= width + 1, `${label}: artwork loads within the viewport`, data);
                    if (fixture.type === 'journal') {
                        assert(data.imageSource.size === 'full' && !data.imageSource.src.includes('hard-cropped') && !!data.imageSource.srcset && data.imageSource.sizes.includes('1080px') && data.imageSource.loading === 'eager' && data.imageSource.priority === 'high', `${label}: public framing uses the complete responsive source and keeps priority loading`, data.imageSource);
                        if (fixture.art === 'poster') assert(Math.abs(data.natural.width / data.natural.height - 2 / 3) < .01, `${label}: full-fit and focal controls never receive the hard-cropped derivative`, data);
                        assert(Math.abs(data.hero.width / data.hero.height - 16 / 9) < .015 && data.imagePosition === `${fixture.mods.lunara_journal_single_image_position_x ?? 50}% ${fixture.mods.lunara_journal_single_image_position_y ?? 50}%` && data.imageFit === (fixture.mods.lunara_journal_single_image_fit || 'cover'), `${label}: landscape hero keeps its frame and neutral focal point`, data);
                    } else {
                        assert(Math.abs(data.image.width / data.image.height - data.natural.width / data.natural.height) < .02, `${label}: Review artwork keeps its complete native proportion`, data);
                    }
                    if (fixture.scenario === 'normal' && width <= 640) assert(data.hero.top + Math.min(data.hero.height, 160) <= 844, `${label}: the first phone viewport includes useful artwork below the opening`, data);
                }
                if(fixture.type==='journal') {
                    assert(data.markers.includes('hero')&&data.markers.includes('article'), `${label}: real article sections expose shared preview markers`, data);
                    if(data.galleryControls.length) assert(data.markers.includes('gallery')&&data.galleryControls.every(box=>box.width>=44&&box.height>=44), `${label}: gallery controls have reachable44px targets and a real preview marker`, data);
                    if(fixture.private) assert(fixture.public_unchanged&&data.metadata.includes('6 min read')&&!data.metadata.includes('September 13')&&data.metadata.includes('Dalton Johnson')===fixture.mods.lunara_journal_show_byline, `${label}: private provider filters reach actual metadata without public writes`, data);
                }
                if (process.env.LUNARA_ARTICLE_ARTIFACT_DIR && ((fixture.scenario === 'normal' && [390, 1440].includes(width)) || (width === 390 && fixture.scenario !== 'normal'))) {
                    fs.mkdirSync(process.env.LUNARA_ARTICLE_ARTIFACT_DIR, { recursive: true });
                    await page.screenshot({ path: path.join(process.env.LUNARA_ARTICLE_ARTIFACT_DIR, label.replace('@', '-') + '.png'), fullPage: true });
                }
                metrics.push({ case: label, contentWidth: data.content.width, titleHeight: data.title.height, titleSize: data.titleSize, hero: data.hero, paragraphSize: data.paragraphSize });
                await page.close();
            }
        }
        if(!reviewOnly&&!selectedArticle) for(const width of [320,390,768,1440]) {
            const normal=metrics.find(row=>row.case===`journal-normal@${width}`),small=metrics.find(row=>row.case===`journal-framed-small@${width}`),large=metrics.find(row=>row.case===`journal-framed-large@${width}`);
            assert(small.titleSize<=normal.titleSize&&normal.titleSize<large.titleSize, `Journal headline size affects actual private rendering at${width}px while preserving a readable phone floor`, {small,normal,large});
        }
        // Compatibility only: the current Review template emits record cards,
        // not these historical Browse Reviews / Director Archive actions.
        for(const width of [390,640,641,1440]) {
            const page=await browser.newPage({viewport:{width,height:1000},javaScriptEnabled:false});
            await page.setContent(`<style>*,*::before,*::after{box-sizing:border-box}body{margin:0}</style><style>${sharedCss}\n${reviewCss}</style>${fixtures['review-normal'].authority}<body class="single-review"><main><article class="lunara-review-single"><aside class="lunara-review-single-rail"><div class="lunara-review-single-rail-sticky"><div class="lunara-review-single-rail-actions"><a class="lunara-btn lunara-btn-ghost" href="/reviews/">Browse Reviews</a><a class="lunara-btn lunara-btn-ghost" href="/director/sample/">Director Archive</a></div></div></aside></article></main></body>`);
            const actions=await page.locator('.lunara-review-single-rail-actions').evaluate(el=>({columns:getComputedStyle(el).gridTemplateColumns,gap:parseFloat(getComputedStyle(el).gap),buttons:[...el.querySelectorAll('a')].map(a=>{const r=a.getBoundingClientRect();return{top:r.top,bottom:r.bottom,width:r.width,height:r.height,text:a.textContent,href:a.getAttribute('href'),fits:a.scrollWidth<=a.clientWidth+1};})}));
            assert(actions.columns.trim().split(/\s+/).length===(width<=640?1:2)&&actions.gap===12&&actions.buttons.length===2&&actions.buttons.every(b=>b.width>0&&b.height>=44&&b.fits&&b.href), `Legacy Review actions@${width}: both full destinations fit in2/1columns with12px gaps`, actions);
            assert(width<=640?Math.abs(actions.buttons[1].top-actions.buttons[0].bottom-12)<=1:Math.abs(actions.buttons[1].top-actions.buttons[0].top)<=1, `Legacy Review actions@${width}: rendered anchors switch rows at640px`, actions);
            await page.close();
        }
        // The same production pairing renderer serves Review and Method. Exercise
        // its canonical and missing-poster outputs with complete arguments, no JS.
        for (const width of [320, 640, 641, 768, 1440]) {
            for (const [index, fixture] of pairFixtures.entries()) {
                const page = await browser.newPage({ viewport: { width, height: 1000 }, javaScriptEnabled: false });
                await page.route('**/*', route => route.fulfill({ contentType:'image/svg+xml', body:'<svg xmlns="http://www.w3.org/2000/svg" width="200" height="300"><rect width="200" height="300" fill="#183441"/></svg>' }));
                const pairMarkup=pairingTemplates[index].html.replace(/<img\b[^>]*>/g,tag=>/\bsrc=/.test(tag)?tag:tag.replace(/>$/, ' src="https://example.test/poster.svg">'));
                await page.setContent(`<style>*,*::before,*::after{box-sizing:border-box}body{margin:0}</style><style>${sharedCss}\n${reviewCss}</style>${fixtures['review-normal'].authority}<body class="single-review"><main>${pairMarkup}</main></body>`, {waitUntil:'load'});
                const pairing = await page.evaluate(() => {
                    const rect=el=>{const r=el.getBoundingClientRect();return{left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height};};
                    const section=document.querySelector('.lunara-pair-cards'), grid=document.querySelector('.lunara-pair-cards-grid');
                    return {section:rect(section),availableWidth:section.parentElement.clientWidth-parseFloat(getComputedStyle(section.parentElement).paddingLeft)-parseFloat(getComputedStyle(section.parentElement).paddingRight),columns:getComputedStyle(grid).gridTemplateColumns,gap:parseFloat(getComputedStyle(grid).gap),width:document.documentElement.scrollWidth,cards:[...document.querySelectorAll('.lunara-pair-card')].map(card=>{
                        const note=card.querySelector('.lunara-pair-card-note'),title=card.querySelector('.lunara-pair-card-title-link'),body=card.querySelector('.lunara-pair-card-body'),poster=card.querySelector('.lunara-pair-card-poster'),img=poster.querySelector('img'),range=document.createRange();range.selectNodeContents(note);
                        const copy=rect(note),style=getComputedStyle(body);
                        return {box:rect(card),body:rect(body),poster:rect(poster),padding:[style.paddingTop,style.paddingRight,style.paddingBottom,style.paddingLeft].map(parseFloat),noteSize:parseFloat(getComputedStyle(note).fontSize),noteText:note.textContent.trim(),titleClamp:getComputedStyle(title).webkitLineClamp,noteClamp:getComputedStyle(note).webkitLineClamp,textVisible:[...range.getClientRects()].every(r=>r.bottom<=copy.bottom+1&&r.right<=copy.right+1&&r.left>=copy.left-1),hasLink:!!card.querySelector('a[href]'),imageFit:img?getComputedStyle(img).objectFit:null,fallback:!!poster.querySelector('.lunara-pair-card-plate')};
                    })};
                });
                const pairs=pairing.cards, roles=await fullHeadingMetrics(page,'.lunara-pair-card-role');
                assert(roles.length===3&&roles.every((role,index)=>role.text===expectedRoleNames[index]&&role.visible&&role.fits&&role.clamp==='none'&&role.align==='center'&&role.centerDelta<=1&&role.fontSize>=13), `Pairings ${index}@${width}: full role names remain visible and centered within the argument lane`, roles);
                assert(pairing.width<=width+1&&Math.abs(pairing.section.left+pairing.section.width/2-width/2)<=1&&Math.abs(pairing.section.width-Math.min(860,pairing.availableWidth))<=1&&pairing.columns.trim().split(/\s+/).length===1&&pairing.gap===20, `Pairings ${index}@${width}: centered860px section contains one column of rows with20px gaps`, pairing);
                assert(pairs.length===3&&pairs.every((card,i)=>card.box.left>=(width<=640?19:0)&&card.box.right<=width-(width<=640?19:0)&&card.noteSize>=16&&card.textVisible&&card.hasLink&&card.titleClamp==='none'&&card.noteClamp==='none'&&card.noteText===fixture.expected_notes[i]), `Pairings ${index}@${width}: every complete argument and film destination remains readable`, pairs);
                assert(pairs.slice(1).every((card,i)=>Math.abs(card.box.top-pairs[i].box.bottom-20)<=1)&&pairs.every(card=>card.padding.every((value,i)=>value===[20,28,20,28][i])), `Pairings ${index}@${width}: rows retain their20px rhythm and20px/28px copy inset`, pairs);
                if(width<=640) assert(pairs.every(card=>Math.abs(card.poster.width-card.box.width)<=1&&card.poster.height<=340&&card.body.top>=card.poster.bottom-1&&(!card.imageFit||card.imageFit==='cover')), `Pairings ${index}@${width}: phones stack full-width posters with a340px cap above the entire argument`, pairs);
                else assert(pairs.every(card=>Math.abs(card.poster.width-170)<=1&&Math.abs(card.poster.width/card.poster.height-2/3)<.01&&card.poster.right<=card.body.left+1&&Math.abs(card.poster.top-card.body.top)<=1), `Pairings ${index}@${width}: desktop rows place170px native-ratio posters to the left of the copy`, pairs);
                if(index===1) assert(pairs.some(card=>card.fallback), `Pairings ${index}@${width}: missing optional artwork retains its real title plate`, pairs);
                await page.close();
            }
            if(reviewOnly||selectedArticle||[640,641].includes(width)) continue;
            const method=await browser.newPage({viewport:{width,height:1000},javaScriptEnabled:false});
            await method.route('**/*', route => route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="200" height="300"><rect width="200" height="300" fill="#183441"/></svg>'}));
            await method.setContent(`<style>body{margin:0}</style><style>${sharedCss}\n${debriefCss}</style>${debriefMethod}`, {waitUntil:'load'});
            const composition=await method.evaluate(()=>({width:document.documentElement.scrollWidth,centers:[...document.querySelectorAll('.lunara-debrief-page,.lunara-debrief-title,.lunara-debrief-thesis,.lunara-debrief-section-title,.lunara-debrief-why-body,.lunara-debrief-words-body,.lunara-debrief-moves-list,.lunara-debrief-orbit-slide,.lunara-debrief-recent-list,.lunara-debrief-next')].map(el=>({selector:el.className,center:el.getBoundingClientRect().left+el.getBoundingClientRect().width/2})),headingAlign:[...document.querySelectorAll('.lunara-debrief-title,.lunara-debrief-section-title')].map(el=>getComputedStyle(el).textAlign),moves:[...document.querySelectorAll('.lunara-debrief-move')].map(el=>({top:el.getBoundingClientRect().top,width:el.getBoundingClientRect().width,font:parseFloat(getComputedStyle(el.querySelector('.lunara-debrief-move-copy')).fontSize)})),title:document.querySelector('.lunara-debrief-title').textContent}));
            const methodRoles = await fullHeadingMetrics(method, '.lunara-debrief-move-name');
            assert(methodRoles.length===3&&methodRoles.every((role,index)=>role.text===expectedRoleNames[index]&&role.visible&&role.fits&&role.clamp==='none'&&role.align==='center'&&role.centerDelta<=1), `Method@${width}: full relationship headings remain visible, unclipped and centered`, methodRoles);
            const methodTitle = await fullHeadingMetrics(method, '.lunara-debrief-title');
            assert(methodTitle.length===1&&methodTitle[0].visible&&methodTitle[0].fits&&methodTitle[0].clamp==='none', `Method@${width}: the entire main heading remains visible`, methodTitle);
            assert(composition.width<=width+1&&composition.moves.length===3&&composition.moves.every(move=>move.font>=16)&&composition.title.length>0, `Method@${width}: all relationships and headline remain readable without JS`, composition);
            assert(composition.centers.every(item=>Math.abs(item.center-width/2)<=1)&&composition.headingAlign.every(align=>align==='center'), `Method@${width}: headings, reading and relationship groups share one center`, composition);
            if(width===1440) assert(composition.moves.every(move=>Math.abs(move.top-composition.moves[0].top)<1), 'Method uses three aligned editorial columns on desktop', composition);
            await method.close();
        }
        if (process.env.LUNARA_ARTICLE_ARTIFACT_DIR) fs.writeFileSync(path.join(process.env.LUNARA_ARTICLE_ARTIFACT_DIR, 'article-layout-metrics.json'), JSON.stringify(metrics, null, 2));
        process.stdout.write(`Article layout browser: ${metrics.length} actual-template scenarios, ${assertions} assertions passed.\n`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
