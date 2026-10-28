<?php
if (es_post()) {
    csrf_verificar();
    cerrar_sesion();
}
header('Location: index.php?p=login');
exit;
