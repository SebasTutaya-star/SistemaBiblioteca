"""Flujos reales contra biblioteca_ui_test; nunca usar contra una base institucional."""
import json
import re
import sys
from datetime import date, timedelta
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'tools/.runtime/python'))
from playwright.sync_api import sync_playwright, expect
BASE='http://127.0.0.1:8080/index.php?p='
OUT=ROOT/'artifacts/qa'
OUT.mkdir(parents=True,exist_ok=True)
checks=[]

def go(page,route):
    response=page.goto(BASE+route)
    page.wait_for_load_state('networkidle')
    assert response.status==200,(route,response.status)
    assert 'Fatal error:' not in page.locator('body').inner_text()

def login(page,email,key):
    go(page,'login');page.locator('[name=correo]').fill(email);page.locator('[name=clave]').fill(key)
    page.get_by_role('button',name='Ingresar al Sistema').click();page.wait_for_load_state('networkidle')
    assert 'p=login' not in page.url

def record(name):
    checks.append(name); print('OK:',name,flush=True)

def loan(page,title):
    go(page,'prestamo_nuevo')
    reader=page.locator('[data-tipo=lector]')
    reader.locator('.selector-buscar').fill('Ana')
    reader.locator('.selector-resultados li').filter(has_text='Ana Cárdenas').click()
    page.locator('.wizard-siguiente').click()
    material=page.locator('[data-tipo=material]')
    material.locator('.selector-buscar').fill(title)
    material.locator('.selector-resultados li').filter(has_text=title).first.click()
    expect(page.locator('[name=fecha_vencimiento]')).not_to_have_value('')
    page.locator('.wizard-siguiente').click()
    expect(page.locator('.wizard-resumen')).to_be_visible()
    expect(page.locator('#resumen-material')).to_contain_text(title)
    page.get_by_role('button',name='Registrar préstamo',exact=True).click()
    page.wait_for_load_state('networkidle')
    assert 'p=prestamos' in page.url,page.locator('body').inner_text()
    go(page,'prestamos&q='+title)
    return page.locator('tbody tr').filter(has_text=title).first

try:
  with sync_playwright() as p:
    browser=p.chromium.launch(channel='chrome',headless=True)
    context=browser.new_context(viewport={'width':390,'height':844},accept_downloads=True)
    page=context.new_page()
    errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    login(page,'biblioteca@sanjuan.edu.pe','70000001')
    go(page,'catalogo&q=peru')
    assert 'peru' in page.locator('[name=q]').input_value();record('Búsqueda real de catálogo')

    go(page,'material_form')
    title='Validación UX UI '+date.today().strftime('%Y%m%d')
    page.locator('[name=titulo]').fill(title)
    page.locator('[name=autor]').fill('Equipo de pruebas')
    page.locator('[name=area]').fill('Comunicación')
    page.locator('[name=anio]').fill('2026')
    page.locator('#btn-sugerir').click()
    expect(page.locator('#f-codigo')).not_to_have_value('')
    page.locator('[name=cantidad_total]').fill('3')
    page.locator('[name=tipo_material_id]').select_option(label='Obra literaria')
    page.get_by_role('button',name='Guardar',exact=True).click();page.wait_for_load_state('networkidle')
    assert 'p=material_ver' in page.url,page.locator('body').inner_text()
    material_id=re.search(r'id=(\d+)',page.url).group(1)
    record('Sugerencia de código y registro de material')

    row=loan(page,title);record('Asistente completo de préstamo con búsquedas reales')
    row.get_by_role('link',name='Renovar',exact=True).click();page.wait_for_load_state('networkidle')
    page.locator('[name=fecha_nueva]').fill((date.today()+timedelta(days=14)).isoformat())
    page.locator('[name=justificacion]').fill('Ampliación de lectura para validación de interfaz')
    page.get_by_role('button',name='Autorizar ampliación').click();page.wait_for_load_state('networkidle')
    assert 'p=prestamos' in page.url;record('Renovación real del préstamo')
    go(page,'prestamos&q='+title)
    page.locator('tbody tr').filter(has_text=title).get_by_role('link',name='Devolver',exact=True).click()
    page.get_by_role('radio',name='En buen estado').check()
    page.get_by_role('button',name='Registrar',exact=True).click();page.wait_for_load_state('networkidle')
    assert 'p=prestamos' in page.url;record('Devolución en buen estado')

    row=loan(page,title)
    row.get_by_role('link',name='Devolver',exact=True).click()
    page.get_by_role('radio',name='El lector perdió el libro').check()
    page.get_by_role('button',name='Registrar',exact=True).click();page.wait_for_load_state('networkidle')
    go(page,'prestamos&filtro=perdidos&q='+title)
    page.get_by_role('link',name='Registrar reposición').click()
    page.screenshot(path=str(OUT/'bibliotecaria-reposicion-390.png'),full_page=True)
    page.get_by_role('button',name='Confirmar reposición').click();page.wait_for_load_state('networkidle')
    assert 'p=prestamos' in page.url;record('Pérdida y reposición del material')

    for action in ['deterioro','reparado']:
        go(page,'material_estado&id='+material_id)
        form=page.locator('form').filter(has=page.locator('select[name=accion]'))
        form.locator('[name=accion]').select_option(action)
        form.locator('[name=cantidad]').fill('1')
        form.get_by_role('button',name='Registrar cambio').click();page.wait_for_load_state('networkidle')
        assert 'Cambio registrado.' in page.locator('body').inner_text()
    record('Actualización de estados del inventario')

    go(page,'importar')
    page.locator('[name=archivo]').set_input_files({'name':'catalogo-qa.csv','mimeType':'text/csv','buffer':('titulo;cantidad;codigo\nImportación UX UI;2;QA-IMP-2026\n').encode()})
    page.get_by_role('button',name='Validar archivo').click();page.wait_for_load_state('networkidle')
    assert 'Filas válidas' in page.locator('body').inner_text()
    page.get_by_role('button',name='Importar 1 material(es)',exact=True).click();page.wait_for_load_state('networkidle')
    assert 'p=catalogo' in page.url;record('Importación CSV con validación y confirmación')

    for kind,magic in [('xlsx',b'PK'),('pdf',b'%PDF')]:
        go(page,'reportes')
        with page.expect_download() as download:
            page.locator('button[name=formato][value='+kind+']').click()
        file=download.value.path()
        assert Path(file).read_bytes().startswith(magic)
        record('Descarga real '+kind.upper())
    assert not errors,errors
    context.close()

    context=browser.new_context(viewport={'width':1440,'height':1000})
    page=context.new_page()
    # Cuenta demo de administrador: la suite visual cambia su clave inicial.
    login(page,'admin@iesanjuan.edu.pe','BibliotecaUI2026!')
    go(page,'usuario_form')
    for field,value in {'dni':'78901234','nombres':'Prueba','apellidos':'Interfaz','correo':'qa.interfaz@sanjuan.edu.pe'}.items():page.locator('[name='+field+']').fill(value)
    page.locator('[name=rol]').select_option('lector');page.locator('[name=tipo_lector]').select_option('estudiante')
    page.locator('[name=grado]').fill('3ro');page.locator('[name=seccion]').fill('A')
    page.get_by_role('button',name='Guardar',exact=True).click();page.wait_for_load_state('networkidle')
    assert 'p=usuarios' in page.url;record('Creación de usuario con selección de rol')
    go(page,'usuarios&q=78901234')
    page.on('dialog',lambda d:d.accept())
    page.get_by_role('button',name='Desactivar',exact=True).click();page.wait_for_load_state('networkidle')
    expect(page.locator('tbody')).to_contain_text('Inactivo')
    page.get_by_role('button',name='Activar',exact=True).click();page.wait_for_load_state('networkidle')
    expect(page.locator('tbody')).to_contain_text('Activo');record('Activación y desactivación de cuentas')
    page.get_by_role('button',name='Clave = DNI',exact=True).click();page.wait_for_load_state('networkidle')
    record('Restablecimiento administrativo de contraseña')
    go(page,'padron')
    page.locator('[name=archivo]').set_input_files({'name':'padron-qa.csv','mimeType':'text/csv','buffer':('dni;nombres;apellidos;correo;tipo;grado;seccion\n78901235;Lector;Validación;qa.padron@sanjuan.edu.pe;estudiante;4to;B\n').encode()})
    page.get_by_role('button',name='Validar archivo').click();page.wait_for_load_state('networkidle')
    page.get_by_role('button',name='Aplicar padrón (1 lectores)',exact=True).click();page.wait_for_load_state('networkidle')
    assert 'p=usuarios' in page.url;record('Importación de padrón escolar')
    context.close()
    context=browser.new_context();page=context.new_page()
    login(page,'qa.interfaz@sanjuan.edu.pe','78901234')
    assert 'p=cambiar_clave' in page.url
    page.locator('[name=actual]').fill('78901234');page.locator('[name=clave]').fill('LectorUI2026!');page.locator('[name=clave2]').fill('LectorUI2026!')
    page.get_by_role('button',name='Guardar',exact=True).click();page.wait_for_load_state('networkidle')
    assert 'p=inicio' in page.url;record('Cambio obligatorio de contraseña en primer ingreso')
    page.locator('.cerrar-sesion').click();page.wait_for_load_state('networkidle')
    assert 'p=login' in page.url;record('Cierre de sesión real')
    go(page,'recuperar');page.locator('[name=correo]').fill('no-existe-qa@invalid.example');page.get_by_role('button',name='Enviar enlace de recuperación').click();page.wait_for_load_state('networkidle')
    expect(page.locator('[role=status]')).to_contain_text('Si el correo está registrado')
    record('Recuperación con respuesta genérica sin envío externo')
    browser.close()
except Exception as e:
  (OUT/'flows.json').write_text(json.dumps({'passed':checks,'error':str(e)},ensure_ascii=False,indent=2),encoding='utf8')
  raise
(OUT/'flows.json').write_text(json.dumps({'passed':checks,'error':None},ensure_ascii=False,indent=2),encoding='utf8')
print('Flujos funcionales:',len(checks))
