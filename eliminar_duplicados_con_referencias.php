<?php

init();

function init() {

    do {

        try {

            eliminar();

        } catch (Exception $e) {

            //sleep(1); // Tiempo de espera en segundos

            continue;
        }

        break;

    } while (true);

}

function get_db_connection() {

    try {

        // Establecer parámetros de conexión a la BD
        $server = "localhost";
        $user = "root";
        $password = "";
        $dbname = "aonline_ps_test";

        // Conectar
        $db = new PDO("mysql:host=$server;dbname=$dbname", $user, $password);

        // Establecer el nivel de errores a EXCEPTION
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $db;

    } catch (PDOException $e) {
        write_log_file(
            'Error al conectar a la Base de Datos', 
            $e->getMessage()
        );

        return null;
    }
}

function eliminar() {
    // Obetener conexión a la BD
    $db = get_db_connection();
    if (!$db) exit;   

    // Tablas de las que se borrarán los registros
    $tablasEliminar = [
        'ps_product', 
        'ps_product_shop', 
        'ps_product_lang', 
        'ps_stock_available',
        'ps_category_product'
    ];

    $itemsPerPage = 100;
    $results = $db->prepare("SELECT COUNT(*) FROM ps_product");
    $results->execute();
    $totalReg = $results->fetch();
    $pages = ceil($totalReg[0]/$itemsPerPage);

    try {


            $querySeletAll = 'SELECT reference FROM ps_product';
            $stmtSelectAll = $db->prepare($querySeletAll);

            $queryCount = 'SELECT count(*) as cantidad
                                FROM ps_product 
                                WHERE reference=:codigo';
            $stmtCount = $db->prepare($queryCount);

            $querySelectOld = 'SELECT id_product FROM ps_product
                                WHERE date_add = (SELECT MIN(date_add) 
                                                    FROM ps_product 
                                                    WHERE reference = :codigo)';
            $stmtSelectOld = $db->prepare($querySelectOld);
            

            $stmtSelectAll->execute();
            $lista = $stmtSelectAll->fetchAll(PDO::FETCH_ASSOC);

            foreach($lista as $item) {

                $stmtCount->bindParam(':codigo', $item['reference']);
                $stmtCount->execute();

                $cuenta = $stmtCount->fetch(PDO::FETCH_ASSOC); 
             

                if ((int) $cuenta['cantidad'] > 1) {

                    $stmtSelectOld->bindParam(':codigo', $item['reference']);
                    $stmtSelectOld->execute();
                    $oldProduct = $stmtSelectOld->fetch(PDO::FETCH_ASSOC);

                    $idProduct = $oldProduct['id_product'];

                    for ($i=0, $size = count($tablasEliminar); $i < $size; $i++) {
                        $db->query("DELETE FROM $tablasEliminar[$i] WHERE id_product = $idProduct");
                    }

                    echo "Se eliminaron los registros coincidentes con id_product = $idProduct\n";
                    
                    
                }
            }

            // Después de eliminar algunos registros, sale del bucle y termina
            // Se lanza una excepción para volver a iniciar el proceso
            throw new Exception("Error Processing Request", 1);

    } catch (Exception $e) {
        $db = null;
      throw new Exception("Error Processing Request", 1);
       

    }
}
