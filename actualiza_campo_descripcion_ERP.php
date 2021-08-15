<?php

date_default_timezone_set('America/Argentina/Buenos_Aires');

sincronizar();

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

function sincronizar() {

    $db = get_db_connection();

    try {

        // Crear un flujo
        $opciones = array(
          'http'=>array(
            'method'=>"GET",
            'header'=>"content-type: application/json\r\n" .
                      "Accept: application/json\r\n" .
                      "Authorization: Basic VVNFUldFQjpNQkVDRVlTQQ==\r\n" .
                      "EmpID: 1\r\n"
          )
        );
       
        $contexto = stream_context_create($opciones);        

        // Abre el fichero usando las cabeceras HTTP establecidas arriba    
        $fichero = @file_get_contents(
                      'http://186.109.90.17:8080/listasprecios/10/articulos?size=100', 
                       false, 
                       $contexto
                    );

        if ($fichero == null) 
            throw new Exception('Error al conectar al servidor');

        $data = json_decode($fichero);

        $listaArticulos = $data->_embedded->listaArticuloResources;

        $totalPages = $data->page->totalPages;
        $totalElements = $data->page->totalElements;
        $contadorArticulos = 0; // Cantidad de artículos procesados
        
        //Se cancela el envio de email
         //marcar_envio_email();
         //bloquear_envio_email_productos_nuevos();

  


    } catch (Exception $e) {
        // Relanzar la excepción para un nuevo intento de conexión
        throw new Exception();
    }



    $queryProducto = 'SELECT p.id_product, descripcion_ERP, active 
                        FROM ps_product p, ps_product_lang pl 
                        WHERE reference = :codigo
                            AND pl.id_product = p.id_product';

    $stmtProducto = $db->prepare($queryProducto);
    $stmtProducto->bindParam(':codigo', $codigo);


    $queryUpdateDescripcionERP = 'UPDATE ps_product_lang
                                   SET descripcion_ERP = :descripcionProductoERP
                                   WHERE id_product = :artId';

    $stmtUpdateDescriptionERP = $db->prepare($queryUpdateDescripcionERP);
    $stmtUpdateDescriptionERP->bindParam(':artId', $artId);
    $stmtUpdateDescriptionERP->bindParam(':descripcionProductoERP', $descripcionProductoERP);
   

    echo "\n"; // Salto de línea inicio impresión CLI
    echo "Proceso iniciado...\n";

    try {

        for ($j = 0; $j < $totalPages; $j++) {

            $size = count($listaArticulos);

            for($i = 0; $i < $size; $i++) {

                $codigo = $listaArticulos[$i]->articulo->codigo;

                $stmtProducto->execute();
                $producto = $stmtProducto->fetch();

                if ($producto) {

                    $artId = $producto['id_product'];

                    //echo "producto: codigo = $codigo  - id = $artId\n";

                    $linkDescripcion = $listaArticulos[$i]
                                        ->articulo
                                        ->_links
                                        ->self
                                        ->href;

                    $productERP = json_decode(
                        @file_get_contents(
                            $linkDescripcion, 
                            false, 
                            $contexto
                        )
                    );

                    
                    $descripcionProductoERP = $productERP->descripcion;

                    $stmtUpdateDescriptionERP->execute();
                }

                // Mostrar barra de progreso
                $contadorArticulos++;
                echo progress_bar($contadorArticulos, $totalElements);

            }

            // En la última página el link es nulo
            if ($j == $totalPages - 1) continue;

            try {

                // Cargar siguiente página en la lista
                $nextPage = $data->_links->next->href;
                $fichero = @file_get_contents($nextPage, false, $contexto);        

                if (!$fichero) {
                    throw new Exception("Error al obtener datos de: $nextPage");                   
                }

                $data = json_decode($fichero);

                $listaArticulos = $data->_embedded->listaArticuloResources;

            } catch (Exception $e) {
                echo 'Error al obtener siguiente página de la lista '. $e->getMessage();
                exit;
            }
            
        }


    } catch (Exception $e) {
       echo 'Error al actualizar los datos '. $e->getMessage();
       
       // Cerrar conexión a BD
       $db = null;
    }

}

// Mostrar una barra de progreso al ejecutar por CLI
function progress_bar($done, $total, $info="", $width=50) {
    $perc = round(($done * 100) / $total);
    $bar = round(($width * $perc) / 100);
    return sprintf(
                "%s%%[%s>%s]%s\r", 
                $perc, 
                str_repeat("=", $bar), 
                str_repeat("·", $width - $bar), 
                $info
            );
}

?>