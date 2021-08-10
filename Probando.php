<?php


include_once "logger.php";




$mascota = [
    "nombre" => "Maggie",
    "edad" => 3,
    "amigos" => [
        [
            "nombre" => "Guayaba",
            "edad" => 2,
        ],
        [
            "nombre" => "Meca",
            "edad" => 5,
        ],
        [
            "nombre" => "Snowball",
            "edad" => 2,
        ],
    ],
];

error_log("La mascota: " . var_export($mascota, true));




?>