"""Revisión focalizada de los ajustes finales de composición y fuentes locales."""
import json
import sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'tools/.runtime/python'))
from playwright.sync_api import sync_playwright
OUT=ROOT/'artifacts/qa'
passed=[]
with sync_playwright() as p:
 b=p.chromium.launch(channel='chrome',headless=True)
 for role,email,key,routes in [
   ('bibliotecaria','biblioteca@sanjuan.edu.pe','70000001',['inicio','catalogo','material_ver&id=6','prestamo_nuevo']),
   ('auxiliar','auxiliar@sanjuan.edu.pe','70000002',['catalogo','prestamos']),
   ('estudiante','estudiante@sanjuan.edu.pe','74561230',['inicio','catalogo','mis_prestamos']),
   ('docente','docente@sanjuan.edu.pe','70112233',['inicio','mis_prestamos']),
   ('admin','admin@iesanjuan.edu.pe','BibliotecaUI2026!',['usuarios','roles'])]:
  c=b.new_context();c.route('https://**',lambda r:r.abort())
  page=c.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
  page.goto('http://127.0.0.1:8080/index.php?p=login');page.locator('[name=correo]').fill(email);page.locator('[name=clave]').fill(key);page.get_by_role('button',name='Ingresar al Sistema').click();page.wait_for_load_state('networkidle')
  assert 'p=login' not in page.url
  for route in routes:
   for width in [1440,768,390,320]:
    page.set_viewport_size({'width':width,'height':900 if width>760 else 844})
    response=page.goto('http://127.0.0.1:8080/index.php?p='+route);page.wait_for_load_state('networkidle');page.evaluate('document.fonts.ready')
    assert response.status==200,(role,route,width,response.status)
    assert not any(t in page.locator('body').inner_text() for t in ['Fatal error:','Warning:','Deprecated:'])
    assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),(role,route,width)
    assert page.evaluate('Array.from(document.images).every(i=>!i.complete||i.naturalWidth>0)'),(role,route,'Imagen rota')
    assert page.evaluate('document.fonts.check("14px Inter")')
    if width in [1440,390]:page.screenshot(path=str(OUT/f'final-{role}-{route.split("&")[0]}-{width}.png'),full_page=True)
    passed.append({'role':role,'route':route,'width':width})
  if role=='estudiante':
   page.goto('http://127.0.0.1:8080/index.php?p=mis_prestamos#historial');page.wait_for_load_state('networkidle')
   assert page.locator('#historial').is_visible()
  assert not errors,errors
  c.close()
 b.close()
(OUT/'final.json').write_text(json.dumps({'checks':passed,'errors':[]},ensure_ascii=False,indent=2),encoding='utf8')
print('OK:',len(passed),'revisiones finales; fuentes y portadas locales; sin desbordamientos ni errores JavaScript.')
