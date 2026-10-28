/* Comportamiento de interfaz; los formularios conservan sus endpoints originales. */
(function () {
  'use strict';
  var previousFocus;
  var menu = document.getElementById('menu-lateral');
  var overlay = document.querySelector('.velo');
  function toggleMenu(open) {
    if (!menu) return;
    document.body.classList.toggle('menu-abierto', open);
    document.querySelectorAll('.abrir-menu').forEach(function (b) { b.setAttribute('aria-expanded', String(open)); });
    overlay.hidden = !open;
    if (open) { previousFocus = document.activeElement; menu.querySelector('a').focus(); }
    else if (previousFocus) previousFocus.focus();
  }
  document.querySelectorAll('.abrir-menu').forEach(function (b) { b.addEventListener('click', function () { toggleMenu(!document.body.classList.contains('menu-abierto')); }); });
  if (overlay) overlay.addEventListener('click', function () { toggleMenu(false); });
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
  window.matchMedia('(min-width: 761px)').addEventListener('change', function (e) { if (e.matches) toggleMenu(false); });

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
    button.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">visibility</span>';
    button.addEventListener('click', function () {
      var show = input.type === 'password'; input.type = show ? 'text' : 'password';
      input.classList.toggle('password-visible', show);
      button.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
      button.setAttribute('aria-pressed', String(show));
      button.firstElementChild.textContent = show ? 'visibility_off' : 'visibility';
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
    if (!table || table.classList.contains('matriz')) return;
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
})();
