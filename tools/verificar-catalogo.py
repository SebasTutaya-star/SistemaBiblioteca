"""QA de fotografías y fichas en la instancia local de pruebas ya iniciada.

No escribe datos. Requiere las dependencias Playwright locales del proyecto.
"""
import json, sys, urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'tools/.runtime/python'))
from playwright.sync_api import sync_playwright
BASE='http://127.0.0.1:8080'
OUT=ROOT/'artifacts/qa/catalogo';OUT.mkdir(parents=True,exist_ok=True)
catalog=json.loads((ROOT/'public_html/assets/catalogo/catalogo.json').read_text(encoding='utf-8'))
photos=[f for r in catalog.values() for f in r['imagenes']]
assert len({f['drive_id'] for f in photos})==len(photos),'Imagen repetida en varios registros'
for f in photos:
    assert f['width']<=1600 and f['height']<=1600
    response=urllib.request.urlopen(BASE+'/'+f['src'])
    assert response.status==200 and response.headers['Content-Type'].startswith('image/'), f['src']
results=[];errors=[]
with sync_playwright() as p:
    browser=p.chromium.launch(channel='chrome',headless=True)
    for role,email,key in [('bibliotecaria','biblioteca@sanjuan.edu.pe','70000001'),('auxiliar','auxiliar@sanjuan.edu.pe','70000002'),('estudiante','estudiante@sanjuan.edu.pe','74561230'),('docente','docente@sanjuan.edu.pe','70112233'),('admin','admin@iesanjuan.edu.pe','BibliotecaUI2026!')]:
        context=browser.new_context();page=context.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
        page.goto(BASE+'/index.php?p=login');page.locator('[name=correo]').fill(email);page.locator('[name=clave]').fill(key)
        page.get_by_role('button',name='Ingresar al Sistema').click();page.wait_for_load_state('networkidle')
        assert 'p=login' not in page.url
        page.goto(BASE+'/index.php?p=catalogo&q=INV-2026-315');page.wait_for_load_state('networkidle')
        # Los enlaces reales permiten identificar la ficha sin depender de IDs locales.
        links=page.locator('a[href*="p=material_ver"]')
        if not links.count():
            results.append(dict(rol=role,catalogo='sin acceso según permisos existentes'));context.close();continue
        href=links.first.get_attribute('href')
        for width in [1440,1024,768,390,320]:
            page.set_viewport_size(dict(width=width,height=900))
            page.goto(href if href.startswith('http') else BASE+'/'+href.lstrip('/'));page.wait_for_load_state('networkidle')
            assert page.locator('.fotos-libro-rejilla button').count()>=1
            page.locator('.fotos-libro').scroll_into_view_if_needed()
            page.wait_for_function("Array.from(document.querySelectorAll('.fotos-libro img')).every(i=>i.complete&&i.naturalWidth>0)")
            assert not page.evaluate('document.documentElement.scrollWidth>innerWidth+1'),(role,width)
            assert not any(x in page.locator('body').inner_text() for x in ['Fatal error:','Warning:','Parse error:'])
            if role in ['bibliotecaria','estudiante'] and width in [1440,390]:page.screenshot(path=str(OUT/f'{role}-{width}.png'),full_page=True)
            results.append(dict(rol=role,ancho=width,fotos=page.locator('.fotos-libro-rejilla button').count(),resultado='correcto'))
        page.locator('.fotos-libro-rejilla button').first.click()
        assert page.locator('.visor-imagen').evaluate('(el) => el.open')
        assert page.locator('.visor-imagen img').get_attribute('src').endswith('.webp')
        page.locator('.visor-cerrar').click()
        assert not page.locator('.visor-imagen').evaluate('(el) => el.open')
        if role in ['estudiante','docente']:assert page.get_by_text('Información administrativa',exact=True).count()==0
        context.close()
    browser.close()
assert not errors,errors
report=dict(libros=len(catalog),fotografias=len(photos),pantallas=results,errores_js=errors)
(OUT/'verificacion.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(report,ensure_ascii=False))
