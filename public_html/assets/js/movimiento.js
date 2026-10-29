/* Movimiento progresivo: ninguna animación sustituye o retrasa una operación. */
(function () {
  'use strict';
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  var running = new WeakMap();
  var reveals = new WeakMap();
  function reveal(element) {
    if (!element || reduced.matches || !element.animate) return;
    var previous = reveals.get(element);
    if (previous) previous.cancel();
    var animation = element.animate([{opacity:.82},{opacity:1}], {duration:100,easing:'cubic-bezier(.2,.8,.2,1)'});
    reveals.set(element, animation);
    function finish() { if (reveals.get(element) === animation) reveals.delete(element); }
    animation.onfinish=finish; animation.oncancel=finish;
  }
  function change(element, update) {
    if (!element || reduced.matches || !element.animate) { update(); return; }
    var previous = running.get(element), before = element.getBoundingClientRect().height;
    if (previous) {
      running.delete(element);
      previous.animation.cancel();
      element.style.overflow = previous.overflow;
    }
    update();
    var after = element.getBoundingClientRect().height;
    if (Math.abs(before - after) < 1) { reveal(element); return; }
    var overflow = element.style.overflow;
    element.style.overflow = 'clip';
    var animation = element.animate([{height:before+'px'},{height:after+'px'}], {duration:120,easing:'cubic-bezier(.2,.7,.2,1)'});
    running.set(element, {animation:animation,overflow:overflow});
    reveal(element);
    function finish() { var current=running.get(element); if (current && current.animation === animation) { element.style.overflow = overflow; running.delete(element); } }
    animation.onfinish = finish; animation.oncancel = finish;
  }
  function closeDialog(dialog) {
    if (!dialog.open) return;
    if (reduced.matches || !dialog.animate) { dialog.close(); return; }
    if (dialog.dataset.closing) return;
    dialog.dataset.closing = 'true';
    var a = dialog.animate([{opacity:1},{opacity:0}],{duration:70,easing:'ease-out'});
    a.finished.then(function () { dialog.close(); delete dialog.dataset.closing; },function () { delete dialog.dataset.closing; });
  }
  window.uiMotion = {change:change,reveal:reveal,closeDialog:closeDialog};
  var resetTimer;
  function reset() {
    clearTimeout(resetTimer); document.body.classList.remove('navegando');
    document.querySelectorAll('form.enviando').forEach(function (form) { form.classList.remove('enviando'); form.removeAttribute('aria-busy'); });
  }
  function loading(kind) {
    try { sessionStorage.setItem('biblioteca-navigation',JSON.stringify({kind:kind || 'module',time:Date.now()})); } catch (_) {}
    document.body.classList.add('navegando'); clearTimeout(resetTimer); resetTimer=setTimeout(reset,6000);
  }
  document.addEventListener('click',function (event) {
    var a=event.target.closest('a[href]');
    if (!a || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey || a.target || a.hasAttribute('download')) return;
    var url=new URL(a.href,location.href);
    if (url.origin !== location.origin || url.searchParams.get('p') === 'plantilla' || url.searchParams.has('formato')) return;
    if (url.pathname===location.pathname && url.search===location.search) {
      if (url.hash) { var target=document.getElementById(decodeURIComponent(url.hash.slice(1))); if(target && !target.matches('[role=tabpanel]')) reveal(target); }
      return;
    }
    loading(a.closest('.tabs,.paginacion') ? 'filter' : 'module');
  });
  document.addEventListener('submit',function (event) {
    if (event.defaultPrevented || (event.submitter && event.submitter.name === 'formato')) return;
    var form=event.target;
    if (form.classList.contains('enviando')) { event.preventDefault(); return; }
    form.classList.add('enviando'); form.setAttribute('aria-busy','true'); loading(form.method.toLowerCase()==='get' ? 'filter' : 'submit');
  });
  // Incluye los filtros que utilizan el envío nativo mediante onchange.
  document.addEventListener('change',function (event) {
    var field=event.target;
    if (field.form && field.form.method.toLowerCase()==='get' && /\.submit\s*\(/.test(field.getAttribute('onchange') || '')) loading('filter');
    if (field.matches('input[type=file]')) reveal(field.closest('.zona-archivo') || field);
    if (field.matches('input[type=checkbox],input[type=radio]')) reveal(field.closest('.opcion') || field);
  },true);
  document.addEventListener('invalid',function (event) {
    if (reduced.matches || !event.target.animate) return;
    event.target.animate([{boxShadow:'0 0 0 0 #ba1a1a00'},{boxShadow:'0 0 0 4px #ba1a1a18'},{boxShadow:'0 0 0 0 #ba1a1a00'}],{duration:160});
  },true);
  document.querySelectorAll('details').forEach(function (el) {
    var summary=el.querySelector('summary');
    if(summary) summary.addEventListener('click',function (event) { event.preventDefault(); change(el,function () { el.open=!el.open; }); });
    el.addEventListener('toggle',function () { if(el.open) Array.from(el.children).filter(function (child) { return child.tagName !== 'SUMMARY'; }).forEach(reveal); });
  });
  window.addEventListener('pageshow',function (event) {
    reset();
    if(event.persisted) { document.documentElement.dataset.navigation='history'; reveal(document.querySelector('main')); }
  });
  window.addEventListener('pagehide',reset);
})();
