

<?php

function estadoEnvejecimiento($annada, $inicio, $fin, $anioActual = null) {
    if ($anioActual === null) {
        $anioActual = (int)date('Y');
    }

    if ($inicio === null || $fin === null) {
        // si no hay ventana definida, devolvemos algo neutro
        return "Sin ventana definida";
    }

    if ($anioActual < (int)$inicio) return "Demasiado joven";
    if ($anioActual > (int)$fin)   return "Pasado su punto óptimo";
    return "En ventana óptima";
}
//Session management and user authentication functions
    