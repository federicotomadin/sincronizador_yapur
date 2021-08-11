<?php

require 'PHPMailer.php';
require 'SMTP.php';

enviar_email_producto_nuevo_a_modificar();


function get_config_productos_agregados() {
      
    $file = __DIR__ . '/configProductosNuevos.ini';
   
    $config = parse_ini_file($file, false);

    return $config;
}


function enviar_email_producto_nuevo_a_modificar() {

$config = get_config_productos_agregados();

if ($config['send_mail']) {
    send_email_productos_nuevos($config['from'], $config['pass'], $config['to'], $config['cc']);
    $config['send_mail'] = 1;
  //  set_config_productos_agregados($config);
}

}


function send_email_productos_nuevos($from, $pass, $to, $cc) {

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
       // $mail->addReplyTo($replyTo);

        $arreglo = array(explode(",", $cc));

        foreach($arreglo as $recipient){
           for ($k = 0; $k < count($recipient); $k++) { 
            $mail->addAddress($recipient[$k]);
           }
         }



        // for ($k = 0; $k < count($nuevosProductosAgregados)-1; $k++) { 


        //     $codigo = $nuevosProductosAgregados[$k]->codigo;
        //     $descripcion =  $descripcion = $nuevosProductosAgregados[$k]->descripcion;
        //     $precioFinal = $nuevosProductosAgregados[$k+1]['PrecioFinal'];
              

        // $tabla .= "<tr>
        //                 <td>$codigo</td>
        //                 <td>$descripcion</td>
        //                 <td>$precioFinal</td>
        //             </tr>";
        // $k++;
        // }
        

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
                            </body>    
                        </html>";

        $mail->send();
        echo "Mensaje enviado correctamente\n";

    } catch (Exception $e) {
        echo "No se pudo enviar el mensaje. Mailer Error: {$mail->ErrorInfo}\n";
    }
}



?>