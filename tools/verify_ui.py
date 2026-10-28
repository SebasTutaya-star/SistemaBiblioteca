"""QA con navegador real. Ejecutar SOLO contra la base ficticia biblioteca_ui_test.

python tools/verify_ui.py --base http://127.0.0.1:8080
Las escrituras funcionales usan las cuentas y materiales ficticios del SQL demo.
"""
import argparse
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'tools/.runtime/python'))
from playwright.sync_api import sync_playwright

parser = argparse.ArgumentParser()
parser.add_argument('--base', default='http://127.0.0.1:8080')
parser.add_argument('--functional', action='store_true')
args = parser.parse_args()
if args.base != 'http://127.0.0.1:8080':
    raise SystemExit('Esta suite solo opera sobre la instancia local de pruebas.')
OUT = ROOT / 'artifacts/qa'
OUT.mkdir(parents=True, exist_ok=True)
results, failures, errors = [], [], []

def goto(page, route):
    response = page.goto(args.base + '/index.php?p=' + route)
    page.wait_for_load_state('networkidle')
    return response

def login(page, email, password):
    goto(page, 'login')
    page.locator('[name="correo"]').fill(email)
    page.locator('[name="clave"]').fill(password)
    page.get_by_role('button', name='Ingresar al Sistema').click()
    page.wait_for_load_state('networkidle')
    assert 'p=login' not in page.url, page.locator('body').inner_text()

def check_screen(page, role, route, width):
    page.set_viewport_size({'width': width, 'height': 1000 if width > 760 else 844})
    response = goto(page, route)
    body = page.locator('body').inner_text()
    assert response.status == 200, f'HTTP {response.status}'
    assert not any(s in body for s in ['Fatal error:', 'Warning:', 'Parse error:', 'Deprecated:']), body[:1000]
    assert page.locator('h1').count(), 'Falta título principal'
    overflow = page.evaluate('document.documentElement.scrollWidth > innerWidth + 1')
    assert not overflow, f'Desbordamiento a {width}px: ' + str(page.evaluate('document.documentElement.scrollWidth'))
    assert page.locator('a[href="#"],a[href=""]').count() == 0, 'Enlace sin destino'
    assert page.evaluate("getComputedStyle(document.documentElement).getPropertyValue('--primario').trim()") == '#1e3a8a'
    if width in [1440,390]:
        page.screenshot(path=str(OUT / f'{role}-{route.split("&")[0]}-{width}.png'), full_page=True)
    results.append({'role': role, 'route': route, 'width': width, 'status': 'pass'})

with sync_playwright() as p:
    browser = p.chromium.launch(channel='chrome', headless=True)
    public = browser.new_page()
    public.on('pageerror', lambda err: errors.append(str(err)))
    for route in ['login','recuperar','restablecer']:
        for width in [1920,1440,1024,768,390,320]:
            try: check_screen(public,'public',route,width)
            except Exception as e: failures.append({'role':'public','route':route,'width':width,'error':str(e)})
    goto(public,'login')
    public.get_by_role('button',name='Mostrar contraseña').click()
    assert public.locator('[name="clave"]').get_attribute('type') == 'text'
    public.get_by_role('button',name='Ocultar contraseña').click()
    public.get_by_role('button',name='Docente / Personal').click()
    assert 'reconocerá automáticamente' in public.locator('#nota-acceso').inner_text()
    public.close()

    roles = [
      ('bibliotecaria','biblioteca@sanjuan.edu.pe','70000001',[
        'inicio','catalogo','catalogo&q=peru','material_ver&id=1','material_form','material_form&id=1','material_estado&id=1',
        'prestamos','prestamos&filtro=vencidos','prestamos&filtro=perdidos','prestamo_nuevo','devolucion&id=1','renovacion&id=1',
        'historial','alertas','reportes','reglas','importar','cambiar_clave']),
      ('auxiliar','auxiliar@sanjuan.edu.pe','70000002',['inicio','catalogo','prestamos','prestamo_nuevo','historial','alertas','cambiar_clave']),
      ('estudiante','estudiante@sanjuan.edu.pe','74561230',['inicio','catalogo','material_ver&id=1','mis_prestamos','cambiar_clave']),
      ('docente','docente@sanjuan.edu.pe','70112233',['inicio','catalogo','mis_prestamos','cambiar_clave']),
      ('admin','admin@iesanjuan.edu.pe','Admin2026!',['inicio','usuarios','usuario_form','usuario_form&id=4','roles','padron','cambiar_clave']),
    ]
    for role,email,password,routes in roles:
        context = browser.new_context()
        page = context.new_page()
        page.on('pageerror', lambda err: errors.append(str(err)))
        try:
            login(page,email,password)
            if role == 'admin' and 'p=cambiar_clave' in page.url:
                page.locator('[name="actual"]').fill(password)
                page.locator('[name="clave"]').fill('BibliotecaUI2026!')
                page.locator('[name="clave2"]').fill('BibliotecaUI2026!')
                page.get_by_role('button',name='Guardar',exact=True).click()
                page.wait_for_load_state('networkidle')
            for route in routes:
                for width in [1920,1440,1024,768,390,320]:
                    try: check_screen(page,role,route,width)
                    except Exception as e: failures.append({'role':role,'route':route,'width':width,'error':str(e)})
            page.set_viewport_size({'width':390,'height':844})
            goto(page,'inicio')
            page.get_by_role('button',name='Más opciones').click()
            assert page.locator('body').evaluate("el=>el.classList.contains('menu-abierto')")
            page.keyboard.press('Escape')
            assert not page.locator('body').evaluate("el=>el.classList.contains('menu-abierto')")
            if role in ['estudiante','docente']:
                assert goto(page,'usuarios').status == 403
                assert goto(page,'prestamo_nuevo').status == 403
                goto(page,'mis_prestamos')
                page.get_by_role('tab',name='Devueltos').click()
                assert page.locator('#historial').is_visible()
            if role == 'auxiliar':
                assert goto(page,'reportes').status == 403
                assert goto(page,'reglas').status == 403
            if role == 'admin': assert goto(page,'catalogo').status == 403
            print(role, 'completado', flush=True)
        except Exception as e: failures.append({'role':role,'error':str(e)})
        context.close()
    browser.close()
report = {'screens':len(results),'checks':results,'failures':failures,'javascript_errors':errors}
(OUT/'report.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps({'screens':len(results),'failures':failures,'javascript_errors':errors},ensure_ascii=False,indent=2))
raise SystemExit(1 if failures or errors else 0)
