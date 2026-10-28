"""Regresiones de interacción y casos de borde en la instancia ficticia local."""
import json
import sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'tools/.runtime/python'))
from playwright.sync_api import sync_playwright,expect
BASE='http://127.0.0.1:8080/index.php?p='
OUT=ROOT/'artifacts/qa'
checks=[]
def go(page,route):
    r=page.goto(BASE+route);page.wait_for_load_state('networkidle');assert r.status==200
def login(page,email,key):
    go(page,'login');page.locator('[name=correo]').fill(email);page.locator('[name=clave]').fill(key);page.get_by_role('button',name='Ingresar al Sistema').click();page.wait_for_load_state('networkidle');assert 'p=login' not in page.url
def prepare_loan(page,reader_name,material_name):
    go(page,'prestamo_nuevo')
    reader=page.locator('[data-tipo=lector]');reader.locator('.selector-buscar').fill(reader_name)
    reader.locator('.selector-resultados li').filter(has_text=reader_name).first.tap()
    page.locator('.wizard-siguiente').tap()
    material=page.locator('[data-tipo=material]');material.locator('.selector-buscar').fill(material_name)
    material.locator('.selector-resultados li').filter(has_text=material_name).first.tap()
    page.locator('.wizard-siguiente').tap()
    page.get_by_role('button',name='Registrar préstamo',exact=True).tap();page.wait_for_load_state('networkidle')
with sync_playwright() as p:
    b=p.chromium.launch(channel='chrome',headless=True)
    c=b.new_context(viewport={'width':320,'height':844},is_mobile=True,has_touch=True)
    c.route('https://**',lambda r:r.abort())
    page=c.new_page()
    login(page,'admin@iesanjuan.edu.pe','BibliotecaUI2026!')
    for width in [320,360,390,768]:
        page.set_viewport_size({'width':width,'height':844});go(page,'usuarios')
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),width
    page.screenshot(path=str(OUT/'admin-usuarios-320-fixed.png'),full_page=True)
    checks.append('Correos largos en usuarios: 320, 360, 390 y 768 px sin desbordamiento')
    c.close()
    c=b.new_context(viewport={'width':390,'height':844},is_mobile=True,has_touch=True)
    c.route('https://**',lambda r:r.abort())
    page=c.new_page();login(page,'biblioteca@sanjuan.edu.pe','70000001')
    go(page,'catalogo')
    page.locator('.contenido a[href*="p=material_ver"]').first.tap()
    expect(page.locator('dialog')).to_be_visible()
    expect(page.locator('dialog .dialog-contenido h1')).to_be_visible()
    page.screenshot(path=str(OUT/'catalogo-dialogo-390.png'),full_page=True)
    page.get_by_role('button',name='Cerrar ficha').tap()
    expect(page.locator('dialog')).not_to_be_visible()
    checks.append('Ficha en diálogo: apertura y cierre por toque, fuentes e iconos locales')

    prepare_loan(page,'Ana','Ecología')
    expect(page.locator('.aviso-advertencia')).to_contain_text('único')
    expect(page.locator('.wizard-resumen')).to_be_visible()
    page.screenshot(path=str(OUT/'prestamo-unico-ejemplar-390.png'),full_page=True)
    page.get_by_role('button',name='Confirmar préstamo',exact=True).tap();page.wait_for_load_state('networkidle')
    assert 'p=prestamos' in page.url
    checks.append('Único ejemplar: advertencia de servidor y confirmación funcional')
    go(page,'prestamos&q=Ecología')
    page.locator('tbody tr').filter(has_text='Ecología').get_by_role('link',name='Devolver',exact=True).tap()
    page.get_by_role('button',name='Registrar',exact=True).tap();page.wait_for_load_state('networkidle')

    prepare_loan(page,'Diego','Cuentos')
    expect(page.locator('.aviso-error')).to_contain_text('vencido')
    assert 'p=prestamo_nuevo' in page.url
    checks.append('Lector con vencidos: reglas originales impiden el préstamo')
    c.close()
    c=b.new_context(viewport={'width':1440,'height':1000})
    page=c.new_page()
    go(page,'restablecer&token='+'a'*64)
    expect(page.locator('[name=clave]')).to_be_visible()
    page.locator('[name=clave]').fill('RestablecidaUI2026!');page.locator('[name=clave2]').fill('RestablecidaUI2026!')
    page.get_by_role('button',name='Guardar contraseña').click();page.wait_for_load_state('networkidle')
    assert 'p=login' in page.url
    login(page,'qa.interfaz@sanjuan.edu.pe','RestablecidaUI2026!')
    checks.append('Enlace de recuperación: nueva contraseña y acceso real sin correo externo')
    b.close()
report_path=OUT/'report.json'
report=json.loads(report_path.read_text(encoding='utf8'))
report['failures']=[f for f in report['failures'] if not (f.get('role')=='admin' and f.get('route')=='usuarios' and f.get('width')==320)]
report['regression_checks']=checks
report['screens']=len(report['checks'])+1
report['checks'].append({'role':'admin','route':'usuarios','width':320,'status':'pass','retest':True})
report_path.write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf8')
(OUT/'edges.json').write_text(json.dumps(checks,ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps(checks,ensure_ascii=False,indent=2))
