"""Comprueba acciones reales, cancelación de animaciones y movimiento reducido."""
import json,sys,time
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT/'tools/.runtime/python'))
from playwright.sync_api import sync_playwright,expect
BASE='http://127.0.0.1:8080/index.php?p='
OUT=ROOT/'artifacts/qa';OUT.mkdir(parents=True,exist_ok=True)
checks=[]
def go(page,route):
 page.goto(BASE+route);page.wait_for_load_state('networkidle')
def login(page,email,key):
 go(page,'login');page.locator('[name=correo]').fill(email);page.locator('[name=clave]').fill(key);page.get_by_role('button',name='Ingresar al Sistema').click();page.wait_for_load_state('networkidle');assert 'p=login' not in page.url
def done(text):checks.append(text);print('OK',text,flush=True)
with sync_playwright() as p:
 browser=p.chromium.launch(channel='chrome',headless=True)
 for reduced in [False,True]:
  context=browser.new_context(viewport={'width':390,'height':844},reduced_motion='reduce' if reduced else 'no-preference')
  page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
  go(page,'login');button=page.get_by_role('button',name='Ingresar al Sistema');before=button.bounding_box()
  for _ in range(3):
   page.get_by_role('button',name='Docente / Personal').click();page.get_by_role('button',name='Estudiante',exact=True).click()
  page.wait_for_timeout(300);after=button.bounding_box();assert before==after,(before,after)
  page.get_by_role('button',name='Mostrar contraseña').click();assert page.locator('[name=clave]').get_attribute('type')=='text'
  page.get_by_role('button',name='Ocultar contraseña').click()
  done(('Movimiento reducido: ' if reduced else '')+'acceso estable y contraseña')
  login(page,'biblioteca@sanjuan.edu.pe','70000001')
  go(page,'catalogo')
  def delay_detail(route):time.sleep(.8);route.continue_()
  context.route('**/index.php?p=material_ver*',delay_detail)
  page.locator('.contenido a[href*="p=material_ver"]').first.click();page.wait_for_timeout(220)
  dialog=page.locator('dialog');before=dialog.bounding_box();expect(dialog.locator('h1')).to_be_visible();after=dialog.bounding_box()
  assert all(abs(before[k]-after[k])<1 for k in before),(before,after)
  page.screenshot(path=str(OUT/('motion-dialog-reduced.png' if reduced else 'motion-dialog.png')),full_page=False)
  page.keyboard.press('Escape');expect(dialog).not_to_be_visible();context.unroute('**/index.php?p=material_ver*',delay_detail)
  done(('Movimiento reducido: ' if reduced else '')+'ficha con carga lenta, dimensiones estables y cierre')
  go(page,'prestamo_nuevo')
  reader=page.locator('[data-tipo=lector]');reader.locator('.selector-buscar').fill('Ana')
  reader.locator('.selector-resultados li').filter(has_text='Ana').first.click()
  page.locator('.wizard-siguiente').click();expect(page.locator('.wizard-fechas')).to_be_visible()
  material=page.locator('[data-tipo=material]');material.locator('.selector-buscar').fill('Cuentos')
  material.locator('.selector-resultados li').filter(has_text='Cuentos').first.click()
  page.locator('.wizard-siguiente').click();expect(page.locator('.wizard-resumen')).to_be_visible()
  for _ in range(3):page.locator('.wizard-atras').click();page.locator('.wizard-siguiente').click()
  page.wait_for_timeout(300)
  assert page.locator('#form-prestamo').evaluate('e=>e.style.overflow')==''
  expect(page.locator('#resumen-material')).to_contain_text('Cuentos')
  done(('Movimiento reducido: ' if reduced else '')+'asistente: búsquedas, avance, retroceso y pulsaciones repetidas')
  for module in ['inicio','catalogo','prestamos','historial','alertas','reportes','importar','reglas']:
   go(page,module);page.get_by_role('button',name='Más opciones').click();page.keyboard.press('Escape')
   assert not page.locator('body').evaluate('e=>e.classList.contains("menu-abierto")')
  done(('Movimiento reducido: ' if reduced else '')+'menú móvil en todos los módulos del personal')
  go(page,'reportes')
  for kind in ['devoluciones','pendientes','deudores','mensual','anual','prestamos']:
   page.locator('select[name=tipo]').select_option(kind);page.wait_for_load_state('networkidle');expect(page.locator('h1')).to_be_visible()
  for format in ['xlsx','pdf']:
   with page.expect_download() as download:page.locator('button[name=formato][value='+format+']').click()
   assert download.value.path()
   assert not page.locator('form.enviando').count()
  done(('Movimiento reducido: ' if reduced else '')+'opciones de reportes y descargas sin indicador bloqueado')
  assert not errors,errors;context.close()
 context=browser.new_context(viewport={'width':390,'height':844});page=context.new_page()
 login(page,'estudiante@sanjuan.edu.pe','74561230');go(page,'mis_prestamos')
 for _ in range(4):page.get_by_role('tab',name='Devueltos').click();page.get_by_role('tab',name='Activos').click()
 page.wait_for_timeout(300);expect(page.locator('#activos')).to_be_visible();assert page.locator('#contenido').evaluate('e=>e.style.overflow')==''
 detail=page.locator('details').first
 if detail.count():detail.locator('summary').click();expect(detail).to_have_attribute('open','');detail.locator('summary').click();expect(detail).not_to_have_attribute('open','')
 done('Lector: pestañas repetidas y apertura/cierre de ayuda')
 context.close();context=browser.new_context();page=context.new_page();login(page,'admin@iesanjuan.edu.pe','BibliotecaUI2026!');go(page,'usuario_form')
 for role in ['lector','auxiliar','bibliotecaria','admin','lector']:
  page.locator('#f-rol').select_option(role)
  expect(page.locator('.solo-lector')).to_be_visible() if role=='lector' else expect(page.locator('.solo-lector')).not_to_be_visible()
 page.locator('#f-tipo').select_option('docente');expect(page.locator('.solo-estudiante').first).not_to_be_visible()
 page.locator('#f-tipo').select_option('estudiante');expect(page.locator('.solo-estudiante').first).to_be_visible()
 page.wait_for_timeout(300);assert page.locator('#form-usuario').evaluate('e=>e.style.overflow')==''
 done('Administrador: campos dinámicos por rol y tipo de lector')
 browser.close()
(OUT/'motion-interactions.json').write_text(json.dumps(checks,ensure_ascii=False,indent=2),encoding='utf8')
print('Interacciones verificadas:',len(checks))
