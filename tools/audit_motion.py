"""Audita estabilidad visual de todos los módulos, con JavaScript deliberadamente lento."""
import argparse,json,sys,time
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'tools/.runtime/python'))
from playwright.sync_api import sync_playwright
parser=argparse.ArgumentParser();parser.add_argument('--output',default='motion-baseline.json');args=parser.parse_args()
OUT=ROOT/'artifacts/qa';OUT.mkdir(parents=True,exist_ok=True)
observer="""window.visualShifts=[];new PerformanceObserver(list=>{for(const e of list.getEntries())if(!e.hadRecentInput)window.visualShifts.push({value:e.value,sources:e.sources.map(s=>({tag:s.node?.tagName,cls:s.node?.className,previous:s.previousRect.toJSON(),current:s.currentRect.toJSON()}))});}).observe({type:'layout-shift',buffered:true});"""
roles=[
 ('bibliotecaria','biblioteca@sanjuan.edu.pe','70000001',['inicio','catalogo','catalogo&q=peru','catalogo&disp=1','material_ver&id=1','material_form','material_form&id=1','material_estado&id=1','prestamos','prestamos&filtro=vencidos','prestamos&filtro=proximos','prestamos&filtro=perdidos','prestamo_nuevo','devolucion&id=1','renovacion&id=1','reposicion&id=1','historial','historial&estado=devuelto','alertas','reportes','reportes&tipo=devoluciones','reportes&tipo=pendientes','reportes&tipo=deudores','reportes&tipo=mensual','reportes&tipo=anual','reglas','importar','cambiar_clave']),
 ('auxiliar','auxiliar@sanjuan.edu.pe','70000002',['inicio','catalogo','material_form','material_estado&id=1','prestamos','prestamo_nuevo','devolucion&id=1','renovacion&id=1','historial','alertas','cambiar_clave']),
 ('estudiante','estudiante@sanjuan.edu.pe','74561230',['inicio','catalogo','material_ver&id=1','mis_prestamos','mis_prestamos#historial','cambiar_clave']),
 ('docente','docente@sanjuan.edu.pe','70112233',['inicio','catalogo','material_ver&id=6','mis_prestamos','mis_prestamos#historial','cambiar_clave']),
 ('admin','admin@iesanjuan.edu.pe','BibliotecaUI2026!',['inicio','usuarios','usuarios&rol=lector','usuarios&estado=0','usuario_form','usuario_form&id=4','roles','padron','cambiar_clave'])]
results=[];errors=[]
with sync_playwright() as p:
 b=p.chromium.launch(channel='chrome',headless=True)
 for role,email,key,routes in [('public',None,None,['login','recuperar','restablecer'])]+roles:
  c=b.new_context();c.add_init_script(observer);page=c.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
  if email:
   page.goto('http://127.0.0.1:8080/index.php?p=login');page.locator('[name=correo]').fill(email);page.locator('[name=clave]').fill(key);page.get_by_role('button',name='Ingresar al Sistema').click();page.wait_for_load_state('networkidle');assert 'p=login' not in page.url,role
  def slow_js(route):
   time.sleep(.3);route.continue_()
  c.route('**/assets/js/**',slow_js)
  cdp=c.new_cdp_session(page);cdp.send('Network.enable');cdp.send('Network.setCacheDisabled',{'cacheDisabled':True})
  for width in [1440,390]:
   page.set_viewport_size({'width':width,'height':900})
   for route in routes:
    response=page.goto('http://127.0.0.1:8080/index.php?p='+route);page.wait_for_load_state('networkidle');page.wait_for_timeout(100)
    shifts=page.evaluate('window.visualShifts');score=sum(x['value'] for x in shifts)
    entry={'role':role,'route':route,'width':width,'status':response.status if response else 200,'cls':round(score,6),'shifts':shifts,'overflow':page.evaluate('document.documentElement.scrollWidth>innerWidth')}
    results.append(entry)
    if score>.005:print('SHIFT',role,route,width,round(score,5),flush=True)
  print(role,'completo',flush=True);c.close()
 b.close()
(OUT/args.output).write_text(json.dumps({'screens':results,'errors':errors},ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps({'screens':len(results),'with_shifts':sum(x['cls']>.005 for x in results),'overflow':sum(x['overflow'] for x in results),'errors':errors}))
