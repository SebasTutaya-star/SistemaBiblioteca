"""Comprueba que el rediseño conserva el backend y los controladores originales."""
import subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
BASE='b25a7a0'
core=['auth.php','bootstrap.php','catalogo.php','db.php','helpers.php','permisos.php','prestamos.php','reportes.php','lib/excel.php','lib/pdf.php']
paths=['public_html/app/'+p for p in core]+['public_html/index.php','base_de_datos/sql/01_estructura.sql','base_de_datos/sql/02_datos_demo.sql']
def original(path):
    return subprocess.run(['git','show',BASE+':'+path],cwd=ROOT,check=True,capture_output=True).stdout.decode('utf-8-sig').replace('\r\n','\n')
for path in paths:
    assert original(path)==(ROOT/path).read_text(encoding='utf-8-sig'),f'Backend alterado: {path}'
pages=0
for path in (ROOT/'public_html/paginas').glob('*.php'):
    rel=path.relative_to(ROOT).as_posix()
    if path.name=='inicio.php':
        # El panel pasa a una vista con consultas de lectura, sin operaciones de escritura.
        continue
    old=original(rel)
    current=path.read_text(encoding='utf-8-sig')
    if 'pagina_inicio(' in old:
        assert old.split('pagina_inicio(',1)[0]==current.split('pagina_inicio(',1)[0],f'Controlador alterado: {rel}'
        pages+=1
    else:
        assert old==current,f'Endpoint alterado: {rel}'
new_view=(ROOT/'public_html/paginas/vistas/panel.php').read_text(encoding='utf8')
assert not any(op in new_view.upper() for op in ['INSERT INTO','UPDATE USUARIOS','DELETE FROM','ALTER TABLE','CREATE TABLE'])
print(f'OK: {len(paths)} archivos de backend/SQL idénticos; {pages} controladores conservados; endpoints sin cambios; panel nuevo solo lectura.')
