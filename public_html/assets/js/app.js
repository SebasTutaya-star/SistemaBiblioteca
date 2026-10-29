/* Comportamientos de la interfaz. El sistema funciona sin JavaScript;
   esto solo agrega comodidades (búsqueda en línea, sugerencia de códigos). */
(function () {
  'use strict';

  // Confirmación antes de acciones sensibles
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-confirmar]');
    if (b && !window.confirm(b.getAttribute('data-confirmar'))) ev.preventDefault();
  });

  // ---- Selectores con búsqueda (lector / material) en el préstamo ----
  document.querySelectorAll('.selector').forEach(function (caja) {
    var tipo = caja.dataset.tipo;
    var entrada = caja.querySelector('.selector-buscar');
    var oculto = caja.querySelector('.selector-id');
    var lista = caja.querySelector('.selector-resultados');
    var info = caja.querySelector('.selector-info');
    var temporizador, datos = [], marcado = -1, version = 0, controller;

    function cerrar() { lista.hidden = true; marcado = -1; }
    function elegir(it) {
      version++; clearTimeout(temporizador); if (controller) controller.abort();
      entrada.value = it.texto;
      oculto.value = it.id;
      info.textContent = it.info;
      cerrar();
      if (tipo === 'material' && it.vence) {
        var f = document.getElementById('f-vence');
        if (f) f.value = it.vence;
      }
      // Si cambia la selección, se anula una confirmación previa de único ejemplar
      var conf = document.querySelector('input[name="confirmar"]');
      if (conf) conf.remove();
    }
    function pintar() {
      lista.innerHTML = '';
      if (!datos.length) {
        var li = document.createElement('li');
        li.textContent = 'Sin resultados';
        lista.appendChild(li);
      }
      datos.forEach(function (it, i) {
        var li = document.createElement('li');
        if (i === marcado) li.className = 'sel';
        var t = document.createElement('div'); t.textContent = it.texto;
        var s = document.createElement('small'); s.textContent = it.info;
        li.appendChild(t); li.appendChild(s);
        li.addEventListener('mousedown', function (e) { e.preventDefault(); elegir(it); });
        lista.appendChild(li);
      });
      lista.hidden = false;
    }
    entrada.addEventListener('input', function () {
      version++; if (controller) controller.abort();
      oculto.value = '';
      info.textContent = '';
      clearTimeout(temporizador);
      var q = entrada.value.trim();
      if (q.length < 2) { cerrar(); return; }
      temporizador = setTimeout(function () {
        var solicitud = version; controller = new AbortController();
        fetch('index.php?p=api_buscar&tipo=' + tipo + '&q=' + encodeURIComponent(q), { credentials: 'same-origin', signal: controller.signal })
          .then(function (r) { return r.json(); })
          .then(function (d) { if (solicitud !== version) return; datos = d; marcado = d.length ? 0 : -1; pintar(); })
          .catch(function (error) { if (error.name !== 'AbortError' && solicitud === version) cerrar(); });
      }, 250);
    });
    entrada.addEventListener('keydown', function (e) {
      if (lista.hidden) return;
      if (e.key === 'ArrowDown') { marcado = Math.min(marcado + 1, datos.length - 1); pintar(); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { marcado = Math.max(marcado - 1, 0); pintar(); e.preventDefault(); }
      else if (e.key === 'Enter' && datos[marcado]) { elegir(datos[marcado]); e.preventDefault(); }
      else if (e.key === 'Escape') cerrar();
    });
    entrada.addEventListener('blur', function () { setTimeout(cerrar, 150); });
  });

  // ---- Código del material: sugerir y validar en línea (RF-06) ----
  var cod = document.getElementById('f-codigo');
  if (cod) {
    var estado = document.getElementById('codigo-estado');
    var validar = function () {
      var v = cod.value.trim();
      if (!v) return;
      fetch('index.php?p=api_codigo&codigo=' + encodeURIComponent(v) + '&id=' + (cod.dataset.id || 0), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          cod.value = d.codigo;
          estado.textContent = d.mensaje;
          estado.className = 'ayuda ' + (d.valido ? 'ok' : 'mal');
        });
    };
    cod.addEventListener('change', validar);
    document.getElementById('btn-sugerir').addEventListener('click', function () {
      var area = document.getElementById('f-area').value, titulo = document.getElementById('f-titulo').value,
          anio = document.getElementById('f-anio').value;
      if (!titulo) { estado.textContent = 'Escriba primero el título (y de preferencia área y año).'; estado.className = 'ayuda mal'; return; }
      fetch('index.php?p=api_codigo&accion=sugerir&area=' + encodeURIComponent(area) + '&titulo=' + encodeURIComponent(titulo) + '&anio=' + encodeURIComponent(anio), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) { cod.value = d.codigo; validar(); });
    });
  }

  // ---- Formulario de usuario: mostrar campos según el rol ----
  var rol = document.getElementById('f-rol');
  if (rol) {
    var tipo = document.getElementById('f-tipo');
    var ajustar = function (animate) {
      var update = function () {
        var lector = rol.value === 'lector';
        document.querySelectorAll('.solo-lector').forEach(function (el) { el.hidden = !lector; });
        document.querySelectorAll('.solo-estudiante').forEach(function (el) { el.hidden = !(lector && tipo.value === 'estudiante'); });
      };
      if (animate && window.uiMotion) window.uiMotion.change(rol.form, update); else update();
    };
    rol.addEventListener('change', ajustar);
    tipo.addEventListener('change', ajustar);
    ajustar();
  }
})();
