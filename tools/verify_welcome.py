"""Bienvenida personalizada, solo tras autenticación correcta, para cada perfil."""
import sys,json
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT/'tools/.runtime/python'))
from playwright.sync_api import sync_playwright,expect
OUT=ROOT/'artifacts/qa';OUT.mkdir(parents=True,exist_ok=True)
checks=[]
with sync_playwright() as p:
 b=p.chromium.launch(channel='chrome',headless=True)
 for width in [1440,390]:
  for role,email,key,name in [('Bibliotecaria','biblioteca@sanjuan.edu.pe','70000001','Rosa Quispe Huamán'),('Auxiliar','auxiliar@sanjuan.edu.pe','70000002','Carlos Mendoza Ccori'),('Estudiante','estudiante@sanjuan.edu.pe','74561230','Lucía Pariona Tito'),('Docente','docente@sanjuan.edu.pe','70112233','Jorge Gamboa Ruiz'),('Administrador','admin@iesanjuan.edu.pe','BibliotecaUI2026!','Administrador del Sistema')]:
   c=b.new_context(viewport={'width':width,'height':900});page=c.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
   page.goto('http://127.0.0.1:8080/index.php?p=login');page.wait_for_load_state('networkidle');expect(page.locator('.welcome-screen')).to_have_count(0)
   page.locator('[name=correo]').fill(email);page.locator('[name=clave]').fill(key);page.get_by_role('button',name='Ingresar al Sistema').click()
   welcome=page.locator('.welcome-screen');expect(welcome).to_be_visible()
   expect(welcome.locator('#welcome-name')).to_have_text(name);expect(welcome.locator('#welcome-role')).to_contain_text(role)
   assert welcome.locator('img').evaluate('i=>i.complete&&i.naturalWidth>0')
   assert page.locator('main').evaluate('e=>e.inert')
   assert not page.evaluate('document.documentElement.scrollWidth>innerWidth')
   if role=='Bibliotecaria':page.screenshot(path=str(OUT/f'welcome-{width}.png'),full_page=False)
   expect(welcome).not_to_be_visible(timeout=4000);assert not page.locator('main').evaluate('e=>e.inert')
   page.reload();page.wait_for_load_state('networkidle');expect(welcome).not_to_be_visible()
   assert not errors,errors
   checks.append({'role':role,'width':width,'name':name,'once':True});c.close()
  print(width,'perfiles verificados',flush=True)
 c=b.new_context(viewport={'width':390,'height':844},reduced_motion='reduce');page=c.new_page()
 page.goto('http://127.0.0.1:8080/index.php?p=login');page.locator('[name=correo]').fill('biblioteca@sanjuan.edu.pe');page.locator('[name=clave]').fill('70000001');page.get_by_role('button',name='Ingresar al Sistema').click()
 expect(page.locator('.welcome-screen')).to_be_visible()
 assert page.locator('.welcome-orbit').evaluate('e=>getComputedStyle(e).animationName')=='none'
 page.keyboard.press('Escape');expect(page.locator('.welcome-screen')).not_to_be_visible();assert not page.locator('main').evaluate('e=>e.inert')
 checks.append({'reduced_motion':True,'escape':True});c.close()
 c=b.new_context();page=c.new_page();page.goto('http://127.0.0.1:8080/index.php?p=login');page.locator('[name=correo]').fill('no-existe@invalid.example');page.locator('[name=clave]').fill('Incorrecta123');page.get_by_role('button',name='Ingresar al Sistema').click();page.wait_for_load_state('networkidle')
 expect(page.locator('.aviso-error')).to_be_visible();assert page.locator('.welcome-screen').count()==0
 assert page.evaluate('sessionStorage.getItem("biblioteca-welcome")') is None
 checks.append({'invalid_login':'no welcome'});c.close();b.close()
(OUT/'welcome.json').write_text(json.dumps(checks,ensure_ascii=False,indent=2),encoding='utf8')
print('OK:',len(checks),'pruebas de bienvenida, autenticación, recarga y accesibilidad.')
