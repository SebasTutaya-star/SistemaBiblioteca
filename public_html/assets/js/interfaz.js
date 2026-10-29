/* Comportamiento de interfaz; los formularios conservan sus endpoints originales. */
(function () {
  'use strict';
  function icon(name) {
    return '<svg class="material-symbols-outlined" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#ui-' + name + '"></use></svg>';
  }
  var previousFocus;
  var menu = document.getElementById('menu-lateral');
  var overlay = document.querySelector('.velo');
  function toggleMenu(open) {
    if (!menu) return;
    document.body.classList.toggle('menu-abierto', open);
    menu.inert = window.matchMedia('(max-width: 760px)').matches && !open;
    document.querySelectorAll('.abrir-menu').forEach(function (b) { b.setAttribute('aria-expanded', String(open)); });
    overlay.hidden = !open;
    if (open) { previousFocus = document.activeElement; menu.querySelector('a').focus(); }
    else if (previousFocus) previousFocus.focus();
  }
  document.querySelectorAll('.abrir-menu').forEach(function (b) { b.addEventListener('click', function () { toggleMenu(!document.body.classList.contains('menu-abierto')); }); });
  if (overlay) overlay.addEventListener('click', function () { toggleMenu(false); });
  if (menu) menu.inert = window.matchMedia('(max-width: 760px)').matches;
  document.addEventListener('keydown', function (ev) {
    if (!document.body.classList.contains('menu-abierto')) return;
    if (ev.key === 'Escape') toggleMenu(false);
    if (ev.key === 'Tab') {
      var focusables = menu.querySelectorAll('a,button');
      var first = focusables[0], last = focusables[focusables.length - 1];
      if (ev.shiftKey && document.activeElement === first) { ev.preventDefault(); last.focus(); }
      else if (!ev.shiftKey && document.activeElement === last) { ev.preventDefault(); first.focus(); }
    }
  });
  window.matchMedia('(min-width: 761px)').addEventListener('change', function () { toggleMenu(false); });

  document.querySelectorAll('input[type="password"]').forEach(function (input, i) {
    if (!input.id) input.id = 'clave-visual-' + i;
    var box = input.parentElement;
    if (!box.classList.contains('campo-icono')) {
      box = document.createElement('span'); box.className = 'campo-icono';
      input.parentNode.insertBefore(box, input); box.appendChild(input);
    }
    var button = document.createElement('button');
    button.type = 'button'; button.className = 'password-toggle';
    button.setAttribute('aria-controls', input.id); button.setAttribute('aria-label', 'Mostrar contraseña');
    button.setAttribute('aria-pressed', 'false');
    button.innerHTML = icon('visibility');
    button.addEventListener('click', function () {
      var show = input.type === 'password'; input.type = show ? 'text' : 'password';
      input.classList.toggle('password-visible', show);
      button.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
      button.setAttribute('aria-pressed', String(show));
      button.innerHTML = icon(show ? 'visibility_off' : 'visibility');
    });
    box.appendChild(button);
  });

  document.querySelectorAll('[data-rol-acceso]').forEach(function (button) {
    button.addEventListener('click', function () {
      document.querySelectorAll('[data-rol-acceso]').forEach(function (b) { b.setAttribute('aria-pressed', String(b === button)); b.classList.toggle('activo', b === button); });
      var staff = button.dataset.rolAcceso === 'personal';
      var notice = document.getElementById('nota-acceso');
      if (notice) notice.textContent = staff ? 'Ingrese con el correo registrado de su cuenta. El sistema reconocerá automáticamente sus permisos de docente, bibliotecaria, auxiliar o administrador.' : 'Su contraseña inicial es su número de DNI. Ingrese con el correo registrado en la biblioteca y actualice su clave al acceder por primera vez.';
      var email = document.querySelector('input[name="correo"]');
      if (email) email.placeholder = staff ? 'nombre@iesanjuan.edu.pe' : 'estudiante@iesanjuan.edu.pe';
    });
  });
  var remember = document.getElementById('recordar-correo');
  if (remember) {
    var email = document.querySelector('input[name="correo"]');
    try { var saved = localStorage.getItem('biblioteca-correo'); if (saved && !email.value) { email.value = saved; remember.checked = true; } } catch (_) { /* Almacenamiento opcional. */ }
    email.form.addEventListener('submit', function () { try { if (remember.checked) localStorage.setItem('biblioteca-correo', email.value); else localStorage.removeItem('biblioteca-correo'); } catch (_) {} });
    remember.addEventListener('change', function () { if (!remember.checked) { try { localStorage.removeItem('biblioteca-correo'); } catch (_) {} } });
  }
  document.querySelectorAll('.tabla-envoltura').forEach(function (wrapper) {
    var table = wrapper.querySelector('table');
    if (!table) return;
    if (table.classList.contains('matriz')) {
      wrapper.tabIndex = 0; wrapper.setAttribute('role', 'region');
      wrapper.setAttribute('aria-label', 'Tabla de permisos. Desplácese horizontalmente para ver todos los roles.');
      return;
    }
    var headers = Array.from(table.querySelectorAll('thead th')).map(function (h) { return h.textContent.trim(); });
    table.querySelectorAll('tbody tr').forEach(function (tr) { Array.from(tr.children).forEach(function (td, i) { td.dataset.label = headers[i] || ''; }); });
    wrapper.classList.add('tabla-responsive');
  });
  document.querySelectorAll('input[type="search"], select').forEach(function (el) {
    if (!el.labels.length && !el.hasAttribute('aria-label')) el.setAttribute('aria-label', el.placeholder || ({area:'Área curricular',nivel:'Nivel educativo',campo:'Campo de búsqueda',filtro:'Estado del préstamo',rol:'Rol del usuario',estado:'Estado del registro'})[el.name] || 'Seleccionar filtro');
  });
  document.querySelectorAll('[data-tabs]').forEach(function (container) {
    var buttons = Array.from(container.querySelectorAll('[data-panel]'));
    function select(button, focus) {
      buttons.forEach(function (b) { var active = b === button; b.setAttribute('aria-selected', String(active)); b.tabIndex = active ? 0 : -1; var panel = document.getElementById(b.dataset.panel); if (panel) panel.hidden = !active; });
      if (focus) button.focus();
    }
    buttons.forEach(function (b, i) {
      b.addEventListener('click', function () { select(b, false); });
      b.addEventListener('keydown', function (ev) { if (ev.key === 'ArrowRight' || ev.key === 'ArrowLeft') { ev.preventDefault(); select(buttons[(i + (ev.key === 'ArrowRight' ? 1 : buttons.length - 1)) % buttons.length], true); } });
    });
    var initial = buttons.find(function (b) { return '#' + b.dataset.panel === location.hash; }) || buttons[0];
    if (initial) select(initial, false);
    window.addEventListener('hashchange', function () { var match = buttons.find(function (b) { return '#' + b.dataset.panel === location.hash; }); if (match) select(match, false); });
  });
  // La ficha conserva una URL real; se abre como diálogo solo dentro del catálogo.
  if (document.body.dataset.pagina === 'catalogo' && typeof HTMLDialogElement !== 'undefined') {
    var detail = document.createElement('dialog'); detail.className = 'ficha-dialog';
    detail.setAttribute('aria-label', 'Ficha bibliográfica del material');
    detail.innerHTML = '<div class="dialog-barra"><strong>Ficha bibliográfica</strong><button type="button" class="icon-btn" aria-label="Cerrar ficha">' + icon('close') + '</button></div><div class="dialog-contenido"></div>';
    document.body.appendChild(detail);
    detail.querySelector('button').addEventListener('click', function () { detail.close(); });
    detail.addEventListener('click', function (event) { if (event.target === detail) { var rect = detail.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) detail.close(); } });
    document.querySelectorAll('.contenido a[href*="p=material_ver"]').forEach(function (link) {
      link.addEventListener('click', function (ev) {
        if (ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.altKey) return;
        ev.preventDefault();
        var content = detail.querySelector('.dialog-contenido');
        content.textContent = 'Cargando ficha…'; detail.showModal();
        fetch(link.href, {credentials: 'same-origin'}).then(function (response) { if (!response.ok) throw new Error('Ficha no disponible'); return response.text(); }).then(function (html) {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var main = doc.querySelector('main.contenido');
          if (!main) throw new Error('Sesión expirada');
          var breadcrumb = main.querySelector('.ruta-contexto'); if (breadcrumb) breadcrumb.remove();
          content.replaceChildren.apply(content, Array.from(main.childNodes));
          content.querySelectorAll('.tabla-envoltura').forEach(function (w) { w.classList.remove('tabla-responsive'); });
        }).catch(function () { detail.close(); window.location.assign(link.href); });
      });
    });
  }
  var loan = document.getElementById('form-prestamo');
  if (loan) {
    var step = loan.querySelector('[name="confirmar"]') ? 2 : 0;
    var reader = loan.querySelector('[data-tipo="lector"]');
    var material = loan.querySelector('[data-tipo="material"]');
    var dates = loan.querySelector('.wizard-fechas');
    var summary = loan.querySelector('.wizard-resumen');
    var next = loan.querySelector('.wizard-siguiente');
    var back = loan.querySelector('.wizard-atras');
    var submit = loan.querySelector('.botonera button:not([type="button"])');
    function showStep() {
      reader.hidden = step !== 0; material.hidden = step !== 1; dates.hidden = step !== 1;
      summary.hidden = step !== 2; back.hidden = step === 0; next.hidden = step === 2; submit.hidden = step !== 2;
      loan.querySelectorAll('.paso').forEach(function (el, i) { el.classList.toggle('activo', i <= step); if (i === step) el.setAttribute('aria-current', 'step'); else el.removeAttribute('aria-current'); });
      next.innerHTML = (step === 0 ? 'Continuar al paso 2' : 'Revisar préstamo') + ' ' + icon('arrow_forward');
      document.getElementById('resumen-lector').textContent = reader.querySelector('input[type="search"]').value;
      document.getElementById('resumen-material').textContent = material.querySelector('input[type="search"]').value;
      document.getElementById('resumen-fecha').textContent = loan.querySelector('[name="fecha_vencimiento"]').value;
    }
    function advance() {
      var selected = step === 0 ? reader : material;
      var search = selected.querySelector('input[type="search"]');
      if (!selected.querySelector('.selector-id').value) { search.setCustomValidity('Seleccione un resultado de la búsqueda para continuar.'); search.reportValidity(); return; }
      search.setCustomValidity('');
      if (step === 1) { var date = loan.querySelector('[name="fecha_vencimiento"]'); if (!date.reportValidity()) return; }
      step = Math.min(2, step + 1); showStep();
      (step === 1 ? material.querySelector('input[type="search"]') : submit).focus();
    }
    [reader, material].forEach(function (el) { el.querySelector('input[type="search"]').addEventListener('input', function () { this.setCustomValidity(''); }); });
    next.addEventListener('click', advance);
    back.addEventListener('click', function () { step = Math.max(0, step - 1); showStep(); (step === 0 ? reader : material).querySelector('input[type="search"]').focus(); });
    loan.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' && step < 2 && ev.target.tagName === 'INPUT' && !ev.defaultPrevented) { ev.preventDefault(); advance(); } });
    loan.addEventListener('submit', function (ev) { if (step < 2) { ev.preventDefault(); advance(); } });
    loan.classList.add('wizard-activo'); showStep();
  }
})();
