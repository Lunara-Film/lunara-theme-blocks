/* Private read-only indicators. Never write pairing fields or public content. */
(function () {
 'use strict';
 var api = window.wp.apiFetch, query = window.wp.url.addQueryArgs;
 var labels = {unidentified:'Choose a film with an IMDb ID to check for a Lunara review.',reviewed:'Lunara review available',unreviewed:'No published Lunara review for this film.'};
 function node(tag,text,parent) { var element=document.createElement(tag);element.textContent=text;parent.appendChild(element);return element; }
 function safeUrl(value) { try { var url=new URL(value);return url.origin===window.location.origin && !url.username && !url.password ? url.href : ''; } catch(error) { return ''; } }
 function render(parent,item) {
  if (!item || !Object.prototype.hasOwnProperty.call(labels,item.status) || typeof item.url!=='string') { throw new Error('Invalid review status'); }
  node('span',labels[item.status],parent);
  if (item.status==='reviewed') { var href=safeUrl(item.url);if(!href){throw new Error('Invalid review link');}node('span',' — ',parent);var link=node('a','Read Lunara Review',parent);link.href=href;link.target='_blank';link.rel='noopener noreferrer';link.setAttribute('aria-label','Read Lunara Review (opens in a new tab)'); }
 }
 function mount(parent,params,report) {
  var alive=true;
  parent.replaceChildren();parent.setAttribute('role','status');parent.setAttribute('aria-live','polite');
  function load() {
   parent.replaceChildren();node('span','Checking Lunara reviews…',parent);
   api({path:query('/lunara-pairing-editor/v1/'+(report?'report':'lookup'),params)}).then(function (payload) {
    if(!alive){return;}parent.replaceChildren();
    if(report){
     if(!payload || !Array.isArray(payload.rows) || payload.rows.length!==3){throw new Error('Invalid pairing report');}
     node('strong','Saved pairing films',parent);
     payload.rows.forEach(function (item) {var row=node('p','',parent);node('strong',item.label+': ',row);render(row,item);});
     var href=safeUrl(payload.edit_url);if(href){var link=node('a','Edit this review and its pairings',parent);link.href=href;link.setAttribute('data-workspace-navigation','');}
     node('p','This reports saved films. Changes to the pairings are made in the Review editor.',parent);
    }else{render(parent,payload);}
   }).catch(function () {if(!alive){return;}parent.replaceChildren();node('span','Review availability could not be checked. ',parent);var retry=node('button','Retry',parent);retry.type='button';retry.addEventListener('click',load);});
  }
  load();return function () {alive=false;};
 }
 window.LunaraPairingReviewEditor={mount:mount};
 var watched=new WeakSet();
 function bind(input,movie) {
  if(watched.has(input)){return;}watched.add(input);
  var parent=node('p','',input.closest('.acf-input') || input.parentElement),cancel=null,timer=null;
  parent.className='description lunara-pairing-review-status';
  function update() {
   if(cancel){cancel();}clearTimeout(timer);parent.replaceChildren();node('span','Checking Lunara reviews…',parent);
   timer=setTimeout(function () {
    var id=document.getElementById('post_ID'),params={review_id:id?Number(id.value)||0:0};
    params[movie?'movie_id':'imdb']=movie?Number(input.value)||0:input.value;
    cancel=mount(parent,params,false);
   },250);
  }
  if(movie && window.jQuery){window.jQuery(input).on('change.lunaraReviewStatus',update);}else{input.addEventListener('change',update);input.addEventListener('input',update);}
  update();
 }
 function scan() {
  ['theme_echo','counter_program','career_context'].forEach(function (role) {
   var legacy=document.getElementById('lunara_'+role);if(legacy){bind(legacy,false);}
   var movie=document.querySelector('[data-key="field_lunara_review_'+role+'_movie"] select');if(movie){bind(movie,true);}
  });
 }
 if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',scan);}else{scan();}
 if(window.acf && window.acf.addAction){window.acf.addAction('ready',scan);window.acf.addAction('append',scan);}
}());
