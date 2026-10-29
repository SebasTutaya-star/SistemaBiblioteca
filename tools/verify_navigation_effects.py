"""Recorre enlaces y filtros reales y comprueba la animación de cada navegación."""
import json,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT/'tools/.runtime/python'))
from playwright.sync_api import sync_playwright
BASE='http://127.0.0.1:8080/index.php?p='
checks=[];errors=[]
with sync_playwright() as p:
 b=p.chromium.launch(channel='chrome',headless=True)
 for role,email,key in [('bibliotecaria','biblioteca@sanjuan.edu.pe','70000001'),('auxiliar','auxiliar@sanjuan.edu.pe','70000002'),('estudiante','estudiante@sanjuan.edu.pe','74561230'),('docente','docente@sanjuan.edu.pe','70112233'),('admin','admin@iesanjuan.edu.pe','BibliotecaUI2026!')]:
  for width in [1440,390]:
   c=b.new_context(viewport={'width':width,'height':900});page=c.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
   page.goto(BASE+'login');page.locator('[name=correo]').fill(email);page.locator('[name=clave]').fill(key);page.get_by_role('button',name='Ingresar al Sistema').click();page.wait_for_load_state('networkidle')
   assert page.locator('html').get_attribute('data-navigation')=='submit'
   assert page.locator('main').evaluate('e=>getComputedStyle(e).animationName')=='accion-lista'
   links=page.locator('.menu a').evaluate_all('els=>els.map(e=>e.getAttribute("href"))')
   for href in links:
    if width==390:page.get_by_role('button',name='Más opciones').click()
    page.locator('.menu a[href='+json.dumps(href)+']').click()
    page.wait_for_load_state('networkidle');page.wait_for_timeout(350)
    assert page.locator('main').is_visible()
    if width==390:assert not page.locator('body').evaluate('e=>e.classList.contains("menu-abierto")')
    assert not page.evaluate('document.documentElement.scrollWidth>innerWidth')
    assert page.locator('main').evaluate('e=>getComputedStyle(e).animationName') in ['pagina-lista','accion-lista','resultado-listo','historial-listo']
    checks.append({'role':role,'width':width,'href':href})
   if role=='bibliotecaria':
    page.goto(BASE+'reportes');page.wait_for_load_state('networkidle')
    page.locator('select[name=tipo]').select_option('mensual');page.wait_for_load_state('networkidle')
    assert page.locator('html').get_attribute('data-navigation')=='filter'
    assert page.locator('main').evaluate('e=>getComputedStyle(e).animationName')=='resultado-listo'
    page.go_back();page.wait_for_load_state('networkidle')
    assert page.locator('html').get_attribute('data-navigation')=='history'
    page.goto(BASE+'catalogo');page.wait_for_load_state('networkidle')
    page.locator('input[name=q]').fill('Cuentos');page.get_by_role('button',name='Buscar',exact=True).click();page.wait_for_load_state('networkidle')
    assert page.locator('html').get_attribute('data-navigation')=='filter'
    checks.append({'role':role,'width':width,'flows':['auto-filter','history','search-form']})
   c.close()
  print(role,'navegación verificada',flush=True)
 assert not errors,errors
 b.close()
out=ROOT/'artifacts/qa/navigation-effects.json';out.write_text(json.dumps({'checks':checks,'errors':errors},ensure_ascii=False,indent=2),encoding='utf8')
print('OK:',len(checks),'recorridos; entradas de módulo, formularios, filtros e historial animados.')
