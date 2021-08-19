<?php

function get_db_connection() {

    try {

        //Local
        $server = "localhost";
        $user =  "root";
        $password = "";
        $dbname = "c0080393_yapur";

        //Server
        // $server = "localhost";
        // $user =  "user_sincro";
        // $password = "clave_sincro";
        // $dbname = "c0080393_yapur";

        // Conectar
        $db = new PDO("mysql:host=$server;dbname=$dbname;charset=utf8", $user, $password);

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


$db = get_db_connection();

$queryProducto = "Select prod.id_product, reference from ps_product prod 
inner join ps_product_lang prod_lang on prod.id_product = prod_lang.id_product  
where prod_lang.descripcion_ERP = '' ";

$stmtProducto = $db->prepare($queryProducto);
$descripcion_ERP = '';
$stmtProducto->bindParam(':descripcion_ERP', $descripcion_ERP);

$stmtProducto->execute();
$result = $stmtProducto->fetchAll();


foreach ($result as $data) {
    echo "id_product:" .$data['id_product'] . " - reference:" . $data['reference']. "\n";
}

?>