<?php

require 'PHPMailer.php';
require 'SMTP.php';

function send_email($from, $pass, $to, $replyTo) {

    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->SMTPDebug  = 0;                      
        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.gmail.com';                     
        $mail->SMTPAuth   = true;                                   
        $mail->Username   = $from;                     
        $mail->Password   = $pass;                               
        $mail->SMTPSecure = 'tls';         
        $mail->Port       = 587;                                   

        //Recipients
        $mail->setFrom($from);
        $mail->addAddress($to);
        $mail->addReplyTo($replyTo);



        //Content
        $mail->isHTML(true);                                  
        $mail->Subject = 'Error al conectar al ERP';
        $mensaje    = 'Ha ocurrido un error al conectar al ERP en el sitio';
        $mail->Body = "
                        <html>        
                            <head>                                
                                <title>Error al obtener los datos</title>        
                            </head>
                            <body>
                                <h1>$mensaje</h1>
                            </body>    
                        </html>";

        $mail->send();
        echo "Mensaje enviado correctamente\n";

    } catch (Exception $e) {
        echo "No se pudo enviar el mensaje. Mailer Error: {$mail->ErrorInfo}\n";
    }
}


function get_config() {
      
    $file = __DIR__ . '/config.ini';
   
    $config = parse_ini_file($file, false);

    return $config;
}

function set_config($config) {

    $file = __DIR__ . '/config.ini';

    $fp = fopen($file, 'w');

    $salida = "; Enviar un email a la dirección indicada al ocurrir un error de conexión\n";
    foreach ($config as $key => $value) {
        $salida .= "$key = $value\n";
    }

    fwrite($fp, $salida);

    fclose($fp);
}


function enviar_email() {

    $config = get_config();

    if ($config['send_mail']) {
        send_email($config['from'], $config['pass'], $config['to'], $config['replyTo']);
        $config['send_mail'] = 0;
        set_config($config);
    }

}

function marcar_envio_email() {

    $config = get_config();

    if (!$config['send_mail']) {
        $config['send_mail'] = 1;
        set_config($config);        
    } 
}

function enviar_email_producto_nuevo_a_modificar($datos) {

    $config = get_config_productos_agregados();

    if ($config['send_mail']) {
        send_email_productos_nuevos($config['from'], $config['pass'], $config['to'], $config['cc'], $datos);
        $config['send_mail'] = 1;
        set_config_productos_agregados($config);
    }

}

function bloquear_envio_email_productos_nuevos() {

    $config = get_config_productos_agregados();

        $config['send_mail'] = 1;
        set_config_productos_agregados($config);        
}

function desbloquear_envio_email_productos_nuevos() {

    $config = get_config_productos_agregados();
        $config['send_mail'] = 1;
        set_config_productos_agregados($config);        
}

function get_config_productos_agregados() {
      
    $file = __DIR__ . '/configProductosNuevos.ini';
   
    $config = parse_ini_file($file, false);

    return $config;
}

function set_config_productos_agregados($config) {

    $file = __DIR__ . '/configProductosNuevos.ini';

    $fp = fopen($file, 'w');

    $salida = "; Enviar un email a la dirección indicada cuando hay productos nuevos pendientes de revisión\n";
    foreach ($config as $key => $value) {
        $salida .= "$key = $value\n";
    }

    fwrite($fp, $salida);

    fclose($fp);
}

function send_email_productos_nuevos($from, $pass, $to, $cc, $nuevosProductosAgregados) {

    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->SMTPDebug  = 0;                      
        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.gmail.com';                     
        $mail->SMTPAuth   = true;                                   
        $mail->Username   = $from;                     
        $mail->Password   = $pass;                               
        $mail->SMTPSecure = 'tls';         
        $mail->Port       = 587;                                   

        //Recipients
        $mail->setFrom($from);
        $mail->addAddress($to);
        $arreglo = array(explode(",", $cc));

        foreach($arreglo as $recipient){
           for ($k = 0; $k < count($recipient); $k++) { 
            $mail->addAddress($recipient[$k]);
           }
         }

        $tabla =   "<table style='width:100%'>
                       <tr>
                        <th>CODIGO</th>
                        <th>TITULO</th>
                        <th>PRECIO</th>
                    </tr>";


        for ($k = 0; $k < count($nuevosProductosAgregados)-1; $k++) { 


            $codigo = $nuevosProductosAgregados[$k]->codigo;
            $descripcion =  $descripcion = $nuevosProductosAgregados[$k]->descripcion;
            $precioFinal = $nuevosProductosAgregados[$k+1]['PrecioFinal'];
              

        $tabla .= "<tr>
                        <td>$codigo</td>
                        <td>$descripcion</td>
                        <td>$precioFinal</td>
                    </tr>";
        $k++;
        }
        
        $tabla .= "</table>";

        //Content
        $mail->isHTML(true);                                  
        $mail->Subject = 'Se agregaron nuevos productos en tienda online';
        $mensaje    = 'Hay nuevos productos que se han sincronizado desde sistema a tienda online:';
        $mail->Body = "
                        <html>        
                            <head>    
                            <style>
                                table, th, td {
                                border: 1px solid black;
                                }
                                </style>                            
                            <title>Se agregaron nuevos productos</title>        
                            </head>
                            <body>
                                <h1>$mensaje</h1><br>
                                <h4>$tabla</h4><br>
                            </body>    
                        </html>";

        $mail->send();
        echo "Mensaje enviado correctamente\n";

    } catch (Exception $e) {
        echo "No se pudo enviar el mensaje. Mailer Error: {$mail->ErrorInfo}\n";
    }
}


