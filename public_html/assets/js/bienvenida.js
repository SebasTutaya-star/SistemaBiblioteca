(function () {
  'use strict';
  var root=document.documentElement, screen=document.querySelector('.welcome-screen');
  if (!root.classList.contains('welcome-pending') || !screen) { root.classList.remove('welcome-pending'); return; }
  var reduced=window.matchMedia('(prefers-reduced-motion: reduce)'), closed=false, timer, exitTimer;
  var previous=document.activeElement;
  var siblings=Array.from(document.body.children).filter(function (el) { return el!==screen && !el.matches('script,svg'); });
  var oldInert=siblings.map(function (el) { var old=el.inert; el.inert=true; return old; });
  screen.focus({preventScroll:true});
  function finish() {
    clearTimeout(timer); clearTimeout(exitTimer);
    root.classList.remove('welcome-pending'); screen.classList.remove('welcome-leaving','welcome-ready');
    siblings.forEach(function (el,i) { el.inert=oldInert[i]; });
    var target=previous && previous!==document.body && document.contains(previous) ? previous : document.querySelector('main h1');
    if(target) { var hadTabindex=target.hasAttribute('tabindex'); if(!hadTabindex)target.setAttribute('tabindex','-1'); target.focus({preventScroll:true}); if(!hadTabindex)target.addEventListener('blur',function(){target.removeAttribute('tabindex');},{once:true}); }
  }
  function close() {
    if(closed)return; closed=true; clearTimeout(timer);
    screen.classList.add('welcome-ready');
    if(reduced.matches){finish();return;}
    // Revela el panel bajo la salida del escudo, sin modificar su distribución.
    root.classList.add('welcome-revealing'); screen.classList.add('welcome-leaving');
    exitTimer=setTimeout(function(){root.classList.remove('welcome-revealing');finish();},220);
  }
  screen.querySelector('.welcome-continue').addEventListener('click',close);
  screen.addEventListener('keydown',function(event){
    if(event.key==='Escape'){event.preventDefault();close();}
    if(event.key==='Tab'){event.preventDefault();screen.querySelector('button').focus();}
  });
  var image=screen.querySelector('img');
  var imageReady=image.complete ? Promise.resolve() : new Promise(function(resolve){image.addEventListener('load',resolve,{once:true});image.addEventListener('error',resolve,{once:true});});
  var minimum=new Promise(function(resolve){setTimeout(resolve,reduced.matches?650:1100);});
  Promise.all([imageReady,minimum]).then(close);
  timer=setTimeout(close,2500);
  window.addEventListener('pagehide',function(){closed=true;root.classList.remove('welcome-revealing');finish();},{once:true});
})();
