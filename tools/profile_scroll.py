"""Perfil local comparativo de scroll real; no representa el FPS de todos los dispositivos."""
import argparse,json,sys,statistics
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT/'tools/.runtime/python'))
from playwright.sync_api import sync_playwright,expect
parser=argparse.ArgumentParser();parser.add_argument('--output',required=True);args=parser.parse_args()
results=[]
with sync_playwright() as p:
 b=p.chromium.launch(channel='chrome',headless=True)
 for width in [1440,390]:
  c=b.new_context(viewport={'width':width,'height':800});page=c.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
  page.goto('http://127.0.0.1:8080/index.php?p=login');page.locator('[name=correo]').fill('biblioteca@sanjuan.edu.pe');page.locator('[name=clave]').fill('70000001');page.get_by_role('button',name='Ingresar al Sistema').click();page.wait_for_load_state('networkidle');expect(page.locator('.welcome-screen')).not_to_be_visible(timeout=4000)
  client=c.new_cdp_session(page);client.send('Performance.enable');client.send('Emulation.setCPUThrottlingRate',{'rate':4})
  for route in ['inicio','catalogo','historial','alertas']:
   page.goto('http://127.0.0.1:8080/index.php?p='+route);page.wait_for_load_state('networkidle')
   before={x['name']:x['value'] for x in client.send('Performance.getMetrics')['metrics']}
   page.evaluate('window.frameGaps=[];window.recordFrames=true;var last=performance.now();function tick(t){if(!recordFrames)return;frameGaps.push(t-last);last=t;requestAnimationFrame(tick)}requestAnimationFrame(tick)')
   page.mouse.move(width//2,450)
   for delta in [260,260,260,260,-260,-260,-260,-260]:page.mouse.wheel(0,delta);page.wait_for_timeout(130)
   gaps=page.evaluate('recordFrames=false;frameGaps.filter(x=>x>0)')
   after={x['name']:x['value'] for x in client.send('Performance.getMetrics')['metrics']}
   results.append({'width':width,'route':route,'frames':len(gaps),'median_ms':round(statistics.median(gaps),2),'p95_ms':round(sorted(gaps)[int((len(gaps)-1)*.95)],2),'task_ms':round((after['TaskDuration']-before['TaskDuration'])*1000,2),'layout_ms':round((after['LayoutDuration']-before['LayoutDuration'])*1000,2),'style_ms':round((after['RecalcStyleDuration']-before['RecalcStyleDuration'])*1000,2),'header_blur':page.locator('.cabecera-app').evaluate('e=>getComputedStyle(e).backdropFilter'),'overflow':page.evaluate('document.documentElement.scrollWidth>innerWidth')})
  assert not errors,errors;c.close()
 b.close()
out=ROOT/'artifacts/qa'/args.output;out.parent.mkdir(parents=True,exist_ok=True);out.write_text(json.dumps(results,indent=2),encoding='utf8')
print(json.dumps(results,indent=2))
