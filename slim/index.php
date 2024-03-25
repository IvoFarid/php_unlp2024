<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
$app->add( function ($request, $handler) {
    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, PATCH, DELETE')
        ->withHeader('Content-Type', 'application/json')
    ;
});

// ACÁ VAN LOS ENDPOINTS
$app->get('/testing', function(Request $request, Response $response){
  $response->getBody()->write('testing');
  return $response;
});

$app->post('/users/create', function(Request $request, Response $response){
  $data = $request->getBody()->getContents();
  var_dump($data);
});

$app->get('/books/{id}', function ($request, $response, array $args) {
  die($args['id']);
});

$app->run();

/*
  SQL EXAMPLES:
    SELECT: Select [Nombre, Apellido]/* FROM Personas WHERE Localidad = 'La Plata' AND/OR Nombre = 'Juan' ORDER BY _campo1_, _campo2_ ASC/DESC LIMIT 15,10
      Limit 15,10 saltea 15 y trae los sig 10.
      Puede ser también LIMIT 10 OFFSET 15
    
    INNER JOINS: SELECT Per.Apellido, Per.Nombre, Loc.Localidad AS Ciudad FROM Personas Per INNER JOIN Localidades Loc ON Per.idLocalidad = Loc.id
      Selecciona Registros de Persona y el registro de la localidad de la otra tabla, para esta última hace inner join de esa tabla bajo el sobrenombre Loc y columna adecuada en la fila que coincidan ids.

    INSERT: INSERT INTO _nombre_tabla_ VALUES (val1, val2, ...)

    UPDATE: UPDATE _nombre_tabla_ SET _column_ = _value_, _column2_ = _value2_ WHERE _column3_ = _value3_

    DELETE FROM _nombre_tabla_ WHERE _column_ = _value_

    SQL MAKE CONNECTION:
      MSQLI:
        PROCEDURAL: 
        $conn = mysqli_connect("example", "user", "passw", "dbname");
        if (mysqli_connect_errno($conn)){
          echo "fallo al conectar a MySQL: " . mysqli_connect_error();
        }
        OOP: 
          $conn = new mysqli("example", "user", "passw", "dbname");
          if ($conn->connect_errno) {
            echo "Fallo al conectar a MySQL: " . $conn->connect_error;
          }
    SQL closing connection:
        PROCEDURAL:
          mysqli_close($mysqli);
        OOP:
          $mysqli->close();
*/