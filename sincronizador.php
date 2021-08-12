<?php
 require('envio_mail/send_mail.php');


date_default_timezone_set('America/Argentina/Buenos_Aires');

init();

function init() {

    // Determina la cantidad de intentos de conexión a la API
    $maximoIntentos = 5; 

    // Lleva la cuenta del número de intentos
    $intentos = 1;

    do {

        try {

            sincronizar();

        } catch (Exception $e) {

            $intentos++;
            sleep(5); // Tiempo de espera en segundos

            continue;
        }

        break;

    } while ($intentos < $maximoIntentos);

    if ($intentos == $maximoIntentos) {
        write_log_db(
            'Error al conectar a la API', 
            'Error al intentar obtener los datos del ERP'
        );

         enviar_email();
    }
}

function get_db_connection() {

    try {

        //Local
        // $server = "localhost";
        // $user =  "root";
        // $password = "";
        // $dbname = "c0080393_yapur";

        $server = "localhost";
        $user =  "user_sincro";
        $password = "clave_sincro";
        $dbname = "c0080393_yapur";

        // Establecer parámetros de conexión a la BD
        // $server = "localhost";
        // $user =  "c0080393";
        // $password = "Jk3fsKrk075FstupQ";
        // $dbname = "c0080393_yapur";

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

function sincronizar() {

    // Tiempo inicial del proceso
    $start_time = microtime(true);

    // Obetener conexión a la BD
    $db = get_db_connection();

    if (!$db) exit; 

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
         marcar_envio_email();
         bloquear_envio_email_productos_nuevos();

  


    } catch (Exception $e) {
        // Relanzar la excepción para un nuevo intento de conexión
        throw new Exception();
    }


    /************************** Preparar sentencias SQL ***************************/

    // Consulta para obtener un producto
    $queryProducto = 'SELECT id_product, active 
                      FROM ps_product 
                      WHERE reference=:codigo';

    $stmtProducto = $db->prepare($queryProducto);
    $stmtProducto->bindParam(':codigo', $codigo);



    $queryActualizarFecha = 'UPDATE ps_product_shop ps, ps_product p
                            SET ps.date_upd = NOW(),  
                                p.date_upd = NOW()
                            WHERE p.id_product = ps.id_product
                            AND p.id_product = :artId';

    $stmtActualizarFecha = $db->prepare($queryActualizarFecha);
    $stmtActualizarFecha->bindParam(':artId', $artId);


    $queryActivarDesactivar = 'UPDATE ps_product_shop, ps_product
                              SET ps_product_shop.active=:activo, 
                                   ps_product.active=:activo
                              WHERE ps_product.id_product = ps_product_shop.id_product
                                AND ps_product.id_product = :artId';

    $stmtActivarDesactivar = $db->prepare($queryActivarDesactivar);
    $stmtActivarDesactivar->bindParam(':activo', $activo);
    $stmtActivarDesactivar->bindParam(':artId', $artId);


    $queryPrecio = 'UPDATE ps_product_shop, ps_product
                          SET ps_product_shop.price=:precio, 
                               ps_product.price=:precio
                          WHERE ps_product.id_product = ps_product_shop.id_product
                            AND ps_product.id_product = :artId';

    $stmtPrecio = $db->prepare($queryPrecio);
    $stmtPrecio->bindParam(':precio', $precio);
    $stmtPrecio->bindParam(':artId', $artId);


    // Sentencia para actualizar el stock de un producto
    $queryStock = 'UPDATE ps_stock_available 
                   SET quantity=:cantidad, physical_quantity=:cantidad_fisica 
                   WHERE id_product=:artId';

    $stmtStock = $db->prepare($queryStock);
    $stmtStock->bindParam(':cantidad', $cantidad);
    $stmtStock->bindParam(':cantidad_fisica', $cantidad); // Se establecen iguales
    $stmtStock->bindParam(':artId', $artId);

    // El resto de sentencias permiten insertar un producto

    $queryInsertProductShop = 'INSERT INTO ps_product_shop(
                                               id_product, 
                                               id_shop, 
                                               id_tax_rules_group, 
                                               price,
                                               active, 
                                               date_add, 
                                               date_upd
                                            ) 
                               VALUES (:artId, 1, 0, :precio, 1, NOW(), NOW())';

    $stmtInsertProductShop = $db->prepare($queryInsertProductShop);
    $stmtInsertProductShop->bindParam(':artId', $artId);
    $stmtInsertProductShop->bindParam(':precio', $precio);


    $queryInsertProduct = 'INSERT INTO ps_product (
                                           id_tax_rules_group, 
                                           price,
                                           reference,
                                           active, 
                                           date_add, 
                                           date_upd
                                        ) 
                            VALUES (0, :precio, :codigo, 1, NOW(), NOW())';

    $stmtInsertProduct = $db->prepare($queryInsertProduct);
    $stmtInsertProduct->bindParam(':precio', $precio);
    $stmtInsertProduct->bindParam(':codigo', $codigo);



$stmtPrecio = $db->prepare($queryPrecio);
$stmtPrecio->bindParam(':precio', $precio);
$stmtPrecio->bindParam(':artId', $artId);

$queryUpdateDescripcionERP = 'UPDATE ps_product_lang
                                SET descripcion_ERP=:descripcionProductoERP
                                WHERE id_product =:artId';

$stmtUpdateDescriptionERP = $db->prepare($queryUpdateDescripcionERP);
$stmtUpdateDescriptionERP->bindParam(':artId', $artId);
$stmtUpdateDescriptionERP->bindParam(':descripcionProductoERP', $descripcionProductoERP);


    $queryInsertDescription = 'INSERT INTO ps_product_lang (
                                              id_product,
                                              id_shop, 
                                              id_lang,
                                              description,
                                              description_short, 
                                              descripcion_ERP,
                                              link_rewrite, 
                                              name,
                                              available_now, 

                                            ) 
                                VALUES (:artId, 1, 1, :descripcion, 
                                          :descripcion_corta, :descripcion_ERP, :link_rewrite, 
                                            :nombre, :disponible)';

    $stmtInsertDescription = $db->prepare($queryInsertDescription);
    $stmtInsertDescription->bindParam(':artId', $artId);
    $stmtInsertDescription->bindParam(':descripcion', $descripcion);
    $stmtInsertDescription->bindParam(':descripcion_corta', $descripcion_corta);
    $stmtInsertDescription->bindParam(':descripcion_ERP', $descripcion_ERP);
    $stmtInsertDescription->bindParam(':link_rewrite', $linkRewrite);
    $stmtInsertDescription->bindParam(':nombre', $nombre);
    $stmtInsertDescription->bindParam(':disponible', $disponible);


    $queryInsertStock = 'INSERT INTO ps_stock_available (
                                        id_product, 
                                        id_product_attribute, 
                                        id_shop, 
                                        id_shop_group, 
                                        quantity, 
                                        physical_quantity
                                    ) 
                        VALUES (:artId, 0, 1, 0, :cantidad, :cantidad)';

    $stmtInsertStock = $db->prepare($queryInsertStock);
    $stmtInsertStock->bindParam(':artId', $artId);
    $stmtInsertStock->bindParam(':cantidad', $cantidad);


    /************************** Fin Preparar sentencias SQL ***************************/


    // Variables de comprobación de cambios
    $filasActualizadas = 0;
    $filasInsertadas = 0;
    $nuevosProductosAgregados = array();
    $productoBaseDeDatos = '';
    
    // Productos omitidos
    $omitidos = ['LYV58050', 'CYP99970'];

    echo "\n"; // Salto de línea inicio impresión CLI
    echo "Proceso iniciado...\n";

    try {

        for ($j = 0; $j < $totalPages; $j++) {

            $size = count($listaArticulos);

            for($i = 0; $i < $size; $i++) {

                $codigo = $listaArticulos[$i]->articulo->codigo;
                $stmtProducto->execute();
                $producto = $stmtProducto->fetch();


                if ($producto) $artId = $producto['id_product']; 

 
                if($producto) {
                        $descripcionProducto = $db->query("SELECT * FROM ps_product_lang WHERE id_product=$artId");
                        $productoBaseDeDatos = $descripcionProducto->fetchAll();
                  
                }

           
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

                //$stmtUpdateDescriptionERP->execute(); 

                if($productoBaseDeDatos != '') {
                    $descripcionProductoDB = $productoBaseDeDatos[0]['descripcion_ERP'];
                } else {
                    $descripcionProductoDB = '';
                }
              

      
                $banderaProductoConDistintaDescripcion = false;

                //Pregunto si la descripcion del producto que viene del ERP es la misma que el producto en base de datos
                if($descripcionProductoERP != $descripcionProductoDB) {        
                    

                   if ($listaArticulos[$i]->articulo->publicaWeb == 'S' && $artId != null && $artId != '') {    

                        //si no es la misma elimino el producto de la base de datos en las tres tablas que despues se inserta 
                        //y dejo que el proceso siga corriendo porque despues lo carga
                        $eliminarProductoDePs_product = "DELETE FROM ps_product WHERE id_product=$artId";
                        $prepareEliminarProductoDePs_product = $db->prepare($eliminarProductoDePs_product);

                        $eliminarProductoDePs_product_shop = "DELETE FROM ps_product_shop WHERE id_product=$artId";
                        $prepareEliminarProductoDePs_product_shop = $db->prepare($eliminarProductoDePs_product_shop);

                        $eliminarProductoDePs_product_lang = "DELETE FROM ps_product_lang WHERE id_product=$artId";
                        $prepareEliminarProductoDePs_product_lang = $db->prepare($eliminarProductoDePs_product_lang);

                        $responseEliminarProductoDePs_product = $prepareEliminarProductoDePs_product->execute();   
                        $responseEliminarProductoDePs_product_shop = $prepareEliminarProductoDePs_product_shop->execute();   
                        $responseEliminarProductoDePs_product_lang = $prepareEliminarProductoDePs_product_lang->execute();   

                        array_push($nuevosProductosAgregados,$productERP);
                        array_push($nuevosProductosAgregados, array('PrecioFinal'=> $listaArticulos[$i]->prFinal));
         
                        $banderaProductoConDistintaDescripcion = true;

                        desbloquear_envio_email_productos_nuevos();
                   }  
              
                }     
                
           
 
     
                // Comprobar que el producto esté activo
                if ($listaArticulos[$i]->articulo->publicaWeb == 'S') {       
        

                    // Obtener datos de stock
                    $artIdStock = $listaArticulos[$i]->articulo->artId;                
                    $stock = json_decode(
                                @file_get_contents(
                                    "http://186.109.90.17:8080/articulos/$artIdStock/stock", 
                                    false, 
                                    $contexto
                                )
                            );

          
                    // Sumar stock del artículo
                    $cantidad = 0;                
                    if ($stock) {                        
                        foreach ($stock as $artStock) {
                            $cantidad += $artStock->existencia;
                        } 
                    }

                    if ($cantidad > 0) $disponible = 'En stock'; 
        
                    
                    // Comprobar que el producto esté en la BD     
                    
                                                                      
                    if (!$producto) {

                        // Crear producto

                        try {
                            // Obtener el producto
                            $linkDescripcion = $listaArticulos[$i]
                                                  ->articulo
                                                  ->_links
                                                  ->self
                                                  ->href;

                  
                  

                            $product = json_decode(
                                            @file_get_contents(
                                                $linkDescripcion, 
                                                false, 
                                                $contexto
                                            )
                                        );

                            if (!$product) throw new Exception();                        

                            $nombre = $product->descripcion;
                            $linkRewrite = format_link_rewrite($nombre);
                            $codigo = $product->codigo;
                            $descripcion = '';
                            $descripcion_corta = get_description_html($product->descripcion);
                            $precio = $listaArticulos[$i]->prFinal;

                            
                            try {  

                                $db->beginTransaction();
                            
                                $stmtInsertProduct->execute();
                                $artId = $db->lastInsertId();

                                $stmtInsertProductShop->execute();
                                
                                $stmtInsertDescription->execute();
                                $stmtInsertStock->execute();

                                //El producto con descripcion cambiada que se eliminó se agrega como inactivo para darle tiempo 
                                //al personal de cargar las fotos y despues se activa a mano. 
                                if($banderaProductoConDistintaDescripcion) {
                                    $activo = 0;
                                    $stmtActivarDesactivar->execute();
                                }

                                $db->commit();
                                
                                // Incrementa registro de inserciones   
                                $filasInsertadas++;
                              
                            } catch (Exception $e) {

                                $db->rollBack();
                                write_log_db(
                                    "Falló al insertar producto ID: $artId", 
                                    $e->getMessage()
                                );
                            }

                        } catch (Exception $e) {
                            write_log_db(
                                "Falló al obtener el producto: ID: $artId ", 
                                $e->getMessage()
                            );
                        }

                    } else {


                        if (!in_array($codigo, $omitidos)) {

                            // Si el producto está en la BD, pero inactivo, se lo activa
                            if ($producto['active'] == 0) {
                                $activo = 1;
                                $stmtActivarDesactivar->execute();

                            }

                            // Se actualiza el stock
                            $stmtStock->execute();

                        } 

                        // Se actualiza el precio
                        $precio = $listaArticulos[$i]->prFinal;
                        $stmtPrecio->execute();

                    }


                } else {

                    if (!in_array($codigo, $omitidos)) {
                    
                        // Comprobar que el producto esté en la BD
                        if ($producto) {
                            // Si el producto viene con el campo publicaWeb="N",
                            // marcarlo como inactivo
                            $activo = 0;
                            $stmtActivarDesactivar->execute();
                        }
                    }
                }

          
                // Cuenta las modificaciones realizadas
                if ($stmtPrecio->rowCount() > 0 || 
                        $stmtStock->rowCount() > 0 ||
                            $stmtActivarDesactivar->rowCount() > 0) {

                    // Se cambia la fecha de actualización del registro
                    $stmtActualizarFecha->execute();
                    
                    $filasActualizadas++;
                }

                // Mostrar barra de progreso
                echo progress_bar($contadorArticulos++, $totalElements);

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
               write_log_db(
                    'Error al obtener siguiente página de la lista', 
                    $e->getMessage()
                );

               exit;
            }
            
        }

        echo "\n";
        echo 'Actualizados: '. $filasActualizadas."\n";
        echo 'Insertados: '. $filasInsertadas."\n";

       if (!empty($nuevosProductosAgregados)) {
            enviar_email_producto_nuevo_a_modificar($nuevosProductosAgregados);
        }
       


        // Tiempo final del proceso
        $end_time = microtime(true);
        $duration = $end_time - $start_time;
        $hours = (int) ($duration / 60 / 60);
        $minutes = (int) ($duration / 60) - $hours * 60;
        $seconds = (int) $duration - $hours * 60 * 60 - $minutes * 60; 

        echo sprintf(
                "Tiempo empleado: %1$02d:%2$02d:%3$02d\n", 
                $hours, 
                $minutes, 
                $seconds
            );

        write_log_db(
            'Fin del proceso',
            'Actualizados: '. $filasActualizadas."\n".
            'Insertados: '. $filasInsertadas."\n".
            sprintf(
                "Tiempo empleado: %1$02d:%2$02d:%3$02d\n", 
                $hours, 
                $minutes, 
                $seconds
            )
        );
        
        // Cerrar conexión a BD
        $db = null;


    } catch (Exception $e) {
       write_log_db('Error al actualizar los datos', $e->getMessage());
       
       // Cerrar conexión a BD
       $db = null;
    }

}


// El atributo ps_product_lang.link_rewrite se guarda con un formato
// predeterminado.
function format_link_rewrite($str) {
    $str = preg_replace('([^A-Za-z0-9 ])', '', $str);
    $str = preg_replace("/\s+/", " ", trim($str));
    $str = strtolower(str_replace(" ", "-", $str));

    return $str;
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

// Guardar logs en Base de Datos
function write_log_db($description, $detail) {

    // Obetener conexión a la BD
    $db = get_db_connection();
    if (!$db) exit;   


    $queryInsertLog = 'INSERT INTO logs (message, detail, date_add) 
                            VALUES (:mensaje, :detalle, NOW())';
    $stmtInsertLog = $db->prepare($queryInsertLog);
    $stmtInsertLog->bindParam(':mensaje', $description);
    $stmtInsertLog->bindParam(':detalle', $detail);
    $stmtInsertLog->execute();

    $db = null;
}

// Guardar logs en archivo de texto plano
function write_log_file($description, $detail) {
    // Abrir el archivo de logs
    $logFile = fopen("log.txt", 'a') 
                    or die("Error al crear archivo");

    $header = date("d/m/Y H:i:s")
                ." ------------------------------------------>\n\n";

    $msg = "$description. \n$detail";

    fwrite($logFile, "\n\n$header $msg") 
        or die("Error al escribir en el archivo");

    fclose($logFile);
}

function get_description_html($description) {
    return '<p><span style="color:#d0121a;"><strong>'.
            $description.
            '</strong></span></p>';
}


?>