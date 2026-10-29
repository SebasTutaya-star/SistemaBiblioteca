"""Regresión: los iconos deben funcionar incluso cuando las fuentes no cargan."""
import sys
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'tools/.runtime/python'))
from playwright.sync_api import sync_playwright

with sync_playwright() as p:
    browser = p.chromium.launch(channel='chrome', headless=True)
    context = browser.new_context(viewport={'width':1440, 'height':900})
    context.route('**/*.woff2', lambda route: route.abort())
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto('http://127.0.0.1:8080/index.php?p=login')
    page.wait_for_load_state('networkidle')
    page.get_by_role('button', name='Mostrar contraseña').click()
    assert page.locator('[name=clave]').get_attribute('type') == 'text'
    page.get_by_role('button', name='Ocultar contraseña').click()
    page.locator('[name=correo]').fill('biblioteca@sanjuan.edu.pe')
    page.locator('[name=clave]').fill('70000001')
    page.get_by_role('button', name='Ingresar al Sistema').click()
    page.wait_for_load_state('networkidle')
    cdp = context.new_cdp_session(page)
    cdp.send('Network.enable')
    cdp.send('Network.setCacheDisabled', {'cacheDisabled':True})
    for width in [1440, 390]:
        page.set_viewport_size({'width':width, 'height':900})
        for route in ['inicio','catalogo','prestamos','prestamo_nuevo','historial','alertas','reportes']:
            response = page.goto('http://127.0.0.1:8080/index.php?p=' + route)
            page.wait_for_load_state('networkidle')
            assert response.status == 200
            assert page.locator('.material-symbols-outlined').evaluate_all(
                'els=>els.every(e=>e.tagName.toLowerCase()==="svg" && !e.textContent.trim())')
            assert page.locator('.material-symbols-outlined use').evaluate_all(
                'els=>els.every(e=>document.querySelector(e.getAttribute("href")))')
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
            before = page.locator('h1').bounding_box()
            page.wait_for_timeout(250)
            assert before == page.locator('h1').bounding_box()
    assert not errors, errors
    browser.close()
print('OK: iconos SVG estables en 14 pantallas con fuentes bloqueadas y caché desactivada.')
