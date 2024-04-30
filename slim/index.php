<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware();
$app->add( function (Request $request, $handler) {
$response = $handler->handle($request);
  return $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
      ->withHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, PATCH, DELETE')
      ->withHeader('Content-Type', 'application/json')
  ;
});

function getConnection(){
    $dbhost = "db";
    $dbname = "seminarioPHP2024_db";
    $dbuser = "IvanLisandro";
    $dbpass = "php2024IL";
    
    $connection = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
    return $connection;
}

function validacionStrLength($valor, $length, $key){
  if(isset($valor[$key]) && (mb_strlen($valor[$key]) <= $length) && (mb_strlen($valor[$key]) > 0)){
    return true;
  } else {
    return false;
  }
}

function validacionTinyInt($valor, $key){
  if(isset($valor[$key]) && (($valor[$key] == 1) || ($valor[$key] == 0))) {
    return true;
  } else {
    return false;
  }
}

function validacionCadenaNumeros($valor, $key){
  if(isset($valor[$key]) && preg_match('/^\d+$/', $valor[$key]) != 0){
    return true;
  } else {
    return false;
  }
}

function validacionFormatoFecha($valor, $key){
  if(isset($valor[$key]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor[$key])){
    return true;
  } else {
    return false;
  }
}

function generarPayload($status, $code, $data, $error){
  if($error){
    return json_encode([
      'status' => $status,
      'code' => $code,
      'error' => $data
    ]);
  } else {
    return json_encode([
      'status' => $status,
      'code' => $code,
      'data' => $data
    ]);
  }
}

// ENDPOINTS:
// ______________LOCALIDADES
// GET LOCALIDADES - OK
$app->get('/localidades', function (Request $request, Response $response) {
  try {
    $connection = getConnection();
    $query = $connection->query('SELECT * FROM localidades');
    $localidades = $query->fetchAll(PDO::FETCH_ASSOC);
    $httpStatus = 200;
    $payload = generarPayload('success', $httpStatus, $localidades, false);
  } catch (PDOException $e){
    $httpStatus = 500;
    $payload = generarPayload('error', $httpStatus, $e->getMessage(), true);
  }
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// POST LOCALIDADES - OK 
$app->post('/localidades', function(Request $request, Response $response){
  // Se agarra la data enviada.
  $data = $request->getParsedBody();
  // Se evalúa que esté seteada una key llamada nombre en la data enviada, a su vez también se evalua que el length del string sea menor o igual
  // a 50 y también mayor a 0, para evitar que se mande una key 'nombre' con un value '' (vacio);
  
  if(validacionStrLength($data, 50, 'nombre')) {
    try {
      $connection = getConnection();
      $verificar = $connection->prepare('SELECT * FROM localidades WHERE nombre = :nombre');
      $verificar->bindValue(':nombre', $data['nombre']);
      $verificar->execute();
      // Si la verificacion de que existe una localidad con el nombre enviado retorna 1 row, significa que ese nombre ya está tomado.
      if($verificar->rowCount() > 0){
        $httpStatus = 400;
        $payload = generarPayload('error', $httpStatus, 'El nombre ya existe.', true);
      } else {
        // si no entro en el if, hago la inserción de la localidad.
        $query = $connection->prepare('INSERT INTO localidades (nombre) VALUES (:nombre)');
        $query->bindParam(':nombre', $data['nombre']);
        $query->execute();
        $nom = $data['nombre'];
        $httpStatus = 200;
        $payload = generarPayload('success', $httpStatus, "La localidad $nom se creo correctamente.", false);
      }
    } catch(PDOException $e) {
       // Si alguna consulta falló por razones externas, entro al catch para retornar el error del servidor.
      $httpStatus = 500;
      $payload = generarPayload('error', $httpStatus, $e->getMessage(), true);   
    }
  } else {
    $httpStatus = 400;
    // Si no entre en el if anterior, significa que:
    // o el nombre no esta seteado, o sí esta seteado, pero habrán mandado más de 50 caracteres o habran mandado un value "vacio" ("nombre" => "")
    if(isset($data['nombre'])){
      if(mb_strlen($data['nombre']) == 0){
        $payload = generarPayload('error', $httpStatus, 'El nombre no puede ser vacío.', true);
      } else {
        $payload = generarPayload('error', $httpStatus, 'Nombre no puede tener más de 50 caracteres.', true);
      }
    } else {
      $payload = generarPayload('error', $httpStatus, 'El nombre es requerido.', true);
    }
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// PUT LOCALIDADES - OK 
$app->put('/localidades/{id}', function (Request $request, Response $response, array $args) {
  // Se agarra la data enviada.
  $data = $request->getParsedBody();
  // Se usa $args para agarrar los datos enviados por URL.
  $id = $args['id'];
  // Evalúo que el ID sea una cadena exclusivamente numérica.
  if(preg_match('/^\d+$/', $id) != 0){
    // Se hace la misma evaluación del nombre que antes, este sería el nuevo nombre actualizado de la localidad.
    // Verifica que esté seteado, y que el largo de string esté entre <=50 y >0
    if(validacionStrLength($data, 50, 'nombre')) {
      try {
        $connection = getConnection();
        // Se evalúa que el ID enviado por URL es un ID existente.
        $verificarId = $connection->prepare('SELECT * FROM localidades WHERE id = :id');
        $verificarId->bindValue(':id', $id);
        $verificarId->execute();
        // Si la funcion no retorna ninguna row, es porque no hay ninguna localidad con ese ID. Se retorna el error de ID inexistente.
        if($verificarId->rowCount() == 0){
          $httpStatus = 404;
          $payload = generarPayload('error', $httpStatus, 'El ID seleccionado no existe.', true);
        } else {
          // El ID se sabe que es válido y existe, pero hay que verificar la unicidad del nombre en la tabla localidades.
          $verificarNombre = $connection->prepare('SELECT * FROM localidades WHERE nombre = :nombre');
          $verificarNombre->bindValue(':nombre', $data['nombre']);
          $verificarNombre->execute();
          // Si la funcion retorna una row, significa que el nuevo nombre ya está siendo usado por otra localidad. Se retorna el error de
          // que el nombre está en uso.
          if($verificarNombre->rowCount() > 0){
            $httpStatus = 400;
            $payload = generarPayload('error', $httpStatus, 'El nombre ya existe.', true);
          } else {
            // Si no retorna ninguna row, tanto el ID como el nombre son válidos, y puedo realizar el UPDATE.
            $query = $connection->prepare('UPDATE localidades SET nombre = :nombre WHERE id = :id');
            $query->bindParam(':id', $id);
            $query->bindValue(':nombre', $data['nombre']);
            $query->execute();
            $httpStatus = 200;
            $payload = generarPayload('success', $httpStatus, "Localidad $id actualizada correctamente con el nombre " . $data['nombre'], false);
          }
        }
      } catch (PDOException $e) {
        $httpStatus = 500;
        // Si alguna consulta falló por razones externas, entro al catch para retornar el error del servidor.
        $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
      }
    } else {
      $httpStatus = 400;
      // Misma validación que antes, evalúa la validez del campo nombre.
      // Si no entre en el if anterior, significa que:
      // o el nombre no esta seteado, o sí esta seteado, pero habrán mandado más de 50 caracteres o habran mandado un value "vacio" ("nombre" => "")
      if(isset($data['nombre'])){
        if(mb_strlen($data['nombre']) == 0){
          $mensajeError = "El nombre no puede ser vacío.";
        } else {
          $mensajeError = "Nombre no puede tener mas de 50 caracteres.";
        }
      } else {
        $mensajeError = "El nombre es requerido.";
      }
      $payload = generarPayload('error', $httpStatus, $mensajeError, true); 
    }
  } else {
    $httpStatus = 400;
    // Si no entre en el IF anterior (el primero de todos), es porque el ID enviado por URL de por sí es inválido, es decir, tenía caracteres alfanuméricos.
    $payload = generarPayload('error', $httpStatus, 'El ID es invalido.', true); 
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// DELETE LOCALIDADES - OK
$app->delete('/localidades/{id}', function (Request $request, Response $response, array $args){
  // Se usa $args para agarrar los datos enviados por URL.
  $id = $args['id'];
  // Evalúo que el ID sea una cadena exclusivamente numérica.
  if(preg_match('/^\d+$/', $id) != 0){
    try {
      $connection = getConnection();
      // Se evalúa que el ID enviado por URL es un ID existente.
      $verificarId = $connection->prepare('SELECT * FROM localidades WHERE id = :id');
      $verificarId->bindValue(':id', $id);
      $verificarId->execute();
      // Si la funcion no retorna ninguna row, es porque no hay ninguna localidad con ese ID. Se retorna el error de ID inexistente.
      if($verificarId->rowCount() == 0){
        $httpStatus = 404;
        $payload = generarPayload('error', $httpStatus, 'El ID seleccionado no existe.', true); 
      } else {
        // Si retornó alguna row, es porque efectivamente el ID es válido y existe.
        $query = $connection->prepare('DELETE FROM localidades WHERE id = :id');
        $query->bindParam(':id', $id);
        $query->execute();
        $httpStatus = 200;
        $payload = generarPayload('success', $httpStatus, "Localidad $id borrada correctamente.", false);
      } 
    } catch (PDOException $e) {
      $httpStatus = 500;
      // Si alguna consulta falló por razones externas, entro al catch para retornar el error del servidor.
      $payload = generarPayload('error', $httpStatus, $e->getMessage(), true);
    }
  } else {
    $httpStatus = 400;
    $payload = generarPayload('error', $httpStatus, 'El ID es inválido.', true);
    // Si no entre en el IF anterior (el primero de todos), es porque el ID enviado por URL de por sí es inválido, es decir, tenía caracteres alfanuméricos.
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// ______________TIPOS PROPIEDAD
// GET TIPOS PROPIEDAD - OK
$app->get('/tipos_propiedad', function (Request $request, Response $response) {
  try {
    $connection = getConnection();
    $query = $connection->query('SELECT * FROM tipo_propiedades');
    $tipos = $query->fetchAll(PDO::FETCH_ASSOC);
    $httpStatus = 200;
    $payload = generarPayload('success', $httpStatus, $tipos, false);
  } catch (PDOException $e){
    $httpStatus = 500;
    $payload = generarPayload('error', $httpStatus, $e->getMessage(), true);
    }
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// POST TIPOS PROPIEDAD - OK 
$app->post('/tipos_propiedad', function(Request $request, Response $response){
  $data = $request->getParsedBody();
  // Tipos propiedad también tiene un único campo 'nombre' requerido y tiene las mismas validaciones de length.
  if(validacionStrLength($data, 50, 'nombre')) {
    try {
      $connection = getConnection();
      // La unicidad del nombre también se cumple en tipo_propiedades, por lo que se evalúa que el nombre que quiero agregar no está siendo usado.
      $verificar = $connection->prepare('SELECT * FROM tipo_propiedades WHERE nombre = :nombre');
      $verificar->bindValue(':nombre', $data['nombre']);
      $verificar->execute();
      // Si la verificación retornó alguna row, significa que encontró una coincidencia, por lo que el nombre ya existe. Retorno error de nombre en uso.
      if($verificar->rowCount() > 0){
        $httpStatus = 400;
        $payload = generarPayload('error', $httpStatus, 'El nombre ya existe.', true); 
      } else {
        // Si la verificación no trajo ninguna coincidencia, es porque el nombre es válido y realizo la inserción en la base.
        $query = $connection->prepare('INSERT INTO tipo_propiedades (nombre) VALUES (:nombre)');
        // bindValue pasa el valor directo, bindParam sólo establece la referencia de la variable.
        $query->bindValue(':nombre', $data['nombre']);
        $query->execute();
        $nom = $data['nombre'];
        $httpStatus = 200;
        $payload = generarPayload('success', $httpStatus, "Tipo de propiedad $nom creado correctamente.", false);
      }
    } catch(PDOException $e) {
      $httpStatus = 500;
      $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
      // Si alguna consulta falló por razones externas, entro al catch para retornar el error del servidor.
    }
  } else {
    $httpStatus = 400;
    // Misma validación que antes, evalúa la validez del campo nombre.
    // Si no entre en el if anterior, significa que:
    // o el nombre no esta seteado, o sí esta seteado, pero habrán mandado más de 50 caracteres o habran mandado un value "vacio" ("nombre" => "")
    if(isset($data['nombre'])){
      if(mb_strlen($data['nombre']) == 0){
        $mensajeError = 'El nombre no puede ser vacío.';
      } else {
        $mensajeError = 'Nombre no puede tener mas de 50 caracteres.';
      }
    } else {
      $mensajeError = 'El nombre es requerido.';
    }
    $payload = generarPayload('error', $httpStatus, $mensajeError, true); 
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// PUT TIPOS PROPIEDAD - OK
$app->put('/tipos_propiedad/{id}', function (Request $request, Response $response, array $args) {
  // Se agarra tanto la data del body como el valor ID enviado por URL.
  $data = $request->getParsedBody();
  $id = $args['id'];
  // Evalúo si el ID es válido (cadena exclusiva de caracteres numéricos, por lo que puede retornar su valor integer y retorna 0 sólo cuando no puede convertirlo)
  if(preg_match('/^\d+$/', $id) != 0){
    // Evalúo que el nombre enviado sea válido, misma comprobación que antes.
    if(validacionStrLength($data, 50, 'nombre')) {
      try {  
        $connection = getConnection();
        // El ID es válido, pero se evalúa que exista efectivamente en la base.
        $verificarId = $connection->prepare('SELECT * FROM tipo_propiedades WHERE id = :id');
        $verificarId->bindValue(':id', $id);
        $verificarId->execute();
        // Si retorna 0 rows, es porque el ID no existe, retorno el correspondiente error.
        if($verificarId->rowCount() == 0){
          $httpStatus = 404;
          $payload = generarPayload('error', $httpStatus, 'El ID seleccionado no existe.', true); 
        } else { 
          // Si retornó distinto de 0 (1), es porque el ID existe. Evalúo ahora que el nombre enviado no esté en uso por otro tipo de propiedad.
          $verificarNombre = $connection->prepare('SELECT * FROM tipo_propiedades WHERE nombre = :nombre');
          $verificarNombre->bindValue(':nombre', $data['nombre']);
          $verificarNombre->execute();
          // Misma validación, si retorna >0 es porque encontró un tipo_propiedad con el nombre enviado, por lo que retorno error correspondiente.
          if($verificarNombre->rowCount() > 0){
            $httpStatus = 400;
            $payload = generarPayload('error', $httpStatus, 'El nombre ya existe.', true); 
          } else {
            // Si no retornó ninguna row, es porque el nombre no está en uso, por lo que realizo el UPDATE en la base.
            $query = $connection->prepare('UPDATE tipo_propiedades SET nombre = :nombre WHERE id = :id');
            $query->bindParam(':id', $id);
            $query->bindValue(':nombre', $data['nombre']);
            $query->execute();
            
            $httpStatus = 200;
            $payload = generarPayload('success', $httpStatus, "Tipo de propiedad $id actualizada correctamente con el nombre " .$data['nombre'], false);
          } 
        }
      } catch (PDOException $e) {
        $httpStatus = 500;
        $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
        // Si alguna consulta falló por razones externas, retorno el error del servidor.
      }
    } else {
      $httpStatus = 400;
      // Misma validación que antes, evalúa la validez del campo nombre.
      // Si no entre en el if anterior, significa que:
      // o el nombre no esta seteado, o sí esta seteado, pero habrán mandado más de 50 caracteres o habran mandado un value "vacio" ("nombre" => "")
      if(isset($data['nombre'])){
        if(mb_strlen($data['nombre']) == 0){
          $mensajeError = 'El nombre no puede ser vacío.';
        } else {
          $mensajeError = 'Nombre no puede tener mas de 50 caracteres.';
        }
      } else {
        $mensajeError = 'El nombre es requerido.';
      }
      $payload = generarPayload('error', $httpStatus, $mensajeError, true); 
    }
  } else {
    $httpStatus = 400;
    // Si no entre en el IF anterior (el primero de todos), es porque el ID enviado por URL de por sí es inválido, es decir, tenía caracteres alfanuméricos.
    $payload = generarPayload('error', $httpStatus, 'El ID es inválido.', true); 
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// DELETE TIPO PROPIEDAD - OK
$app->delete('/tipos_propiedad/{id}', function (Request $request, Response $response, array $args){
  // Se agarra el ID y se evalúa su validez.
  $id = $args['id'];
  if(preg_match('/^\d+$/', $id) != 0){
    try {
      $connection = getConnection();
      // Si el ID es válido, se evalúa que sea un ID existente en la base.
      $verificarId = $connection->prepare('SELECT * FROM tipo_propiedades WHERE id = :id');
      $verificarId->bindValue(':id', $id);
      $verificarId->execute();
      // Si retorna 0 rows, es porque no existe.
      if($verificarId->rowCount() == 0){
        $httpStatus = 404;
        $payload = generarPayload('error', $httpStatus, 'El ID seleccionado no existe.', true); 
      } else {
        // Si existe, realizo el DELETE.
        $query = $connection->prepare('DELETE FROM tipo_propiedades WHERE id = :id');
        $query->bindParam(':id', $id);
        $query->execute();
        $httpStatus = 200;
        $payload = generarPayload('success', $httpStatus, "Tipo de propiedad $id eliminada correctamente", false);
      } 
    } catch (PDOException $e) {
      $httpStatus = 500;
      $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
    }
  } else {
    $httpStatus = 400;
    $payload = generarPayload('error', $httpStatus, 'El ID es inválido.', true); 
    // Si no entre al if principal, es porque el ID enviado por URL es inválido.
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// ______________INQUILINOS
// GET INQUILINOS - OK
$app->get('/inquilinos', function (Request $request, Response $response) {
  try {
    $connection = getConnection();
    $query = $connection->query('SELECT * FROM inquilinos');
    $inquilinos = $query->fetchAll(PDO::FETCH_ASSOC);
    $httpStatus = 200;
    $payload = generarPayload('success', $httpStatus, $inquilinos, false);
  }
  catch (PDOException $e){
    $httpStatus = 500;
    $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
  }
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// GET INQUILINO ID - OK
$app->get('/inquilinos/{id}', function (Request $request, Response $response, array $args) {
  $id = $args['id'];
  // Evaluo la validez del ID.
  if(preg_match('/^\d+$/', $id) != 0){
    try {
      $connection = getConnection();
      $verificarId = $connection->prepare('SELECT * FROM inquilinos WHERE id = :id');
      $verificarId->bindValue(':id', $id);
      $verificarId->execute();
      // Si retorna 0, es porque no encontró el ID, retorno el error adecuado.
      if($verificarId->rowCount() == 0){
        $httpStatus = 404;
        $payload = generarPayload('error', $httpStatus, 'El ID seleccionado no existe.', true); 
      } else {
        // Si encontró una row con ese ID, retorno el inquilino adecuado.
        $inquilino = $verificarId->fetch(PDO::FETCH_ASSOC);
        $httpStatus = 200;
        $payload = generarPayload('success', $httpStatus, $inquilino, false);
      }
    } catch (PDOException $e){
      $httpStatus = 500;
      $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
      // Si alguna consulta falló por razones externas, retorno el error del servidor.
    }
  } else {
    $httpStatus = 400;
    $payload = generarPayload('error', $httpStatus, 'El ID es inválido.', true); 
    // Si no entre al IF general, es porque el ID es inválido.
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// POST INQUILINO - OK
$app->post('/inquilinos', function (Request $request, Response $response) {
  // Agarro la data enviada por body.
  $data = $request->getParsedBody();
  // Creé una variable auxiliar para cada campo para poder hacer la validación más sencilla.
  // Cada variable es = a un ternario con sus correspondientes validaciones
  // Tanto nombre, apellido y email poseen validaciones similares, evalúo si estan seteadas, luego el length del string enviado, si todo esto da TRUE
  // se retorna TRUE a la variable, sino, FALSE
  // $variable = condiciones a cumplir ? (valor_si_cumple) : (valor_si_no_cumple)
  $validacionNombre = validacionStrLength($data, 25, 'nombre');
  $validacionApellido = validacionStrLength($data, 15, 'apellido');
  $validacionEmail = validacionStrLength($data, 20, 'email');
  $validacionActivo = validacionTinyInt($data,'activo');
  // Evaluo que el DNI también cumpla con ser una cadena exclusiva de números.
  $validacionDNI = validacionCadenaNumeros($data, 'documento');

  $errors = [];
  
  // Si cada variable posee valor TRUE es porque pasaron las validaciones, sino, alguna será falsa, y no entrara al IF.
  if($validacionNombre && $validacionApellido && $validacionEmail && $validacionActivo && $validacionDNI){
    try {
      $connection = getConnection();
      // El DNI en el inquilino es único, por lo que evalúo que el DNI no esté en uso.
      $verificarDNI = $connection->prepare('SELECT * FROM inquilinos WHERE documento = :documento');
      $verificarDNI->bindValue(':documento', $data['documento']);
      $verificarDNI->execute();
      // Si retorna >0, es porque hay alguna row con el DNI y retorno el error adecuado.
      if($verificarDNI->rowCount() > 0){
        $httpStatus = 400;
        $payload = generarPayload('error', $httpStatus, 'El DNI ya existe.', true); 
      } else {
        // Si el DNI no existe en la base y cada campo pasó su validación, realizo la inserción.
        $query = $connection->prepare('INSERT INTO inquilinos (apellido, nombre, documento, email, activo) VALUES (:apellido, :nombre, :documento, :email, :activo)');
        $query->bindParam(':nombre', $data['nombre']);
        $query->bindParam(':apellido', $data['apellido']);
        $query->bindParam(':documento', $data['documento']);
        $query->bindParam(':email', $data['email']);
        $query->bindParam(':activo', $data['activo']);
        $query->execute();
        
        $httpStatus = 201;
        $payload = generarPayload('success', $httpStatus, "El inquilino de nombre " . $data['nombre'] . " fue creado correctamente.", false); 
      }
    } catch (PDOException $e){
      $httpStatus = 500;
      $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
      // Si alguna consulta falló por razones externas, retorno el error del servidor.
    }
  } else {
    if (!$validacionNombre) { $errors["nombre"] = "El nombre es requerido y tiene que tener entre 1 y 25 caracteres."; }
    if (!$validacionApellido) { $errors["apellido"] = "El apellido es requerido y tiene que tener entre 1 y 15 caracteres."; }
    if (!$validacionEmail) { $errors["email"] = "El email es requerido y tiene que tener entre 1 y 20 caracteres."; }
    if (!$validacionActivo) { $errors["activo"] = "El campo 'activo' es requerido y tiene que valer 0 o 1."; }
    if (!$validacionDNI) { $errors["dni"] = "El DNI tiene que ser una cadena exclusiva de números, no debe poseer puntos ni letras."; }
    $httpStatus = 400;
    $payload = generarPayload('error', $httpStatus, $errors, true); 
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// PUT INQUILINO ID - OK
$app->put('/inquilinos/{id}', function (Request $request, Response $response, array $args) {
  $data = $request->getParsedBody();
  // Para actualizar el inquilino debo tomar todos los datos de nuevo, realizar las mismas validaciones en los campos
  // y tambien realizar la validacion del ID enviado por URL.
  $id = $args['id'];
  // Evaluo validez del ID.
  if(preg_match('/^\d+$/', $id) != 0){
    $validacionNombre = validacionStrLength($data, 25, 'nombre');
    $validacionApellido = validacionStrLength($data, 15, 'apellido');
    $validacionEmail = validacionStrLength($data, 20, 'email');
    $validacionActivo = validacionTinyInt($data,'activo');
    // No se valida largo del DNI.
    $validacionDNI = validacionCadenaNumeros($data, 'documento');
    // Si el ID es válido, evalúo que la información enviada haya pasado las validaciones.
    $errors = [];
    if($validacionNombre && $validacionApellido && $validacionEmail && $validacionActivo && $validacionDNI){
      try {  
        $connection = getConnection();
        // Si todo está correcto, evalúo que el ID exista efectivamente en la base. 
        $verificarId = $connection->prepare('SELECT * FROM inquilinos WHERE id = :id');
        $verificarId->bindValue(':id', $id);
        $verificarId->execute();
        // Si retorna 0, retorno que el ID no existe.
        if($verificarId->rowCount() == 0){
          $httpStatus = 404;
          $payload = generarPayload('error', $httpStatus, 'El ID seleccionado no existe.', true); 
        } else { 
          // EL ID existe y los campos son válidos, pero tengo que verificar que el nuevo DNI no esté siendo usado por otro inquilino
          // Por lo que se requiere una segunda validación, esta vez del DNI.
          $verificarDNI = $connection->prepare('SELECT * FROM inquilinos WHERE documento = :documento');
          $verificarDNI->bindValue(':documento', $data['documento']);
          $verificarDNI->execute();
          if($verificarDNI->rowCount() > 0){
            // Evalúo qué DNI se encontró.
            $dniEncontrado = $verificarDNI->fetch(PDO::FETCH_ASSOC);
            // ID del inquilino a editar
            $inquilinoUpdate = $verificarId->fetch(PDO::FETCH_ASSOC);
            // Si el ID del inquilino a updatear coincide con el ID del inquilino encontrado en la base, significa que el usuario quiere actualizar sus datos, pero no su DNI, por lo que se mantiene igual.
            if ($inquilinoUpdate['id'] == $dniEncontrado['id']){
              $query = $connection->prepare('UPDATE inquilinos SET nombre = :nombre, apellido = :apellido, documento = :documento, email = :email, activo = :activo WHERE id = :id');
              $query->bindParam(':id', $id);
              $query->bindParam(':nombre', $data['nombre']);
              $query->bindParam(':apellido', $data['apellido']);
              $query->bindParam(':documento', $data['documento']);
              $query->bindParam(':email', $data['email']);
              $query->bindParam(':activo', $data['activo']);
              $query->execute();

              $httpStatus=200;
              $payload = generarPayload('success', $httpStatus, "El inquilino de DNI " . $data['documento'] . " actualizó sus datos correctamente.", false);
            } else {
              $httpStatus = 400;
              // Si el ID no coincide, significa que el DNI nuevo ya pertenece a otra persona.
              $payload = generarPayload('error', $httpStatus, 'El DNI ya pertenece a otra persona.', true); 
            }
          } else {
            // Si el DNI no está siendo usado, Realizo el UPDATE.
            $query = $connection->prepare('UPDATE inquilinos SET nombre = :nombre, apellido = :apellido, documento = :documento, email = :email, activo = :activo WHERE id = :id');
            $query->bindParam(':id', $id);
            $query->bindParam(':nombre', $data['nombre']);
            $query->bindParam(':apellido', $data['apellido']);
            $query->bindParam(':documento', $data['documento']);
            $query->bindParam(':email', $data['email']);
            $query->bindParam(':activo', $data['activo']);
            $query->execute();

            $httpStatus = 200;
            $payload = generarPayload('success', $httpStatus, "El inquilino $id logró actualizar todos sus datos correctamente.", false);
          } 
        }
      } catch (PDOException $e) {
        $httpStatus = 500;
        // Si alguna consulta falló por razones externas, retorno el error del servidor.
        $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
      }
    } else {
      if (!$validacionNombre) { $errors["nombre"] = "El nombre es requerido y tiene que tener entre 1 y 25 caracteres."; }
      if (!$validacionApellido) { $errors["apellido"] = "El apellido es requerido y tiene que tener entre 1 y 15 caracteres."; }
      if (!$validacionEmail) { $errors["email"] = "El email es requerido y tiene que tener entre 1 y 20 caracteres."; }
      if (!$validacionActivo) { $errors["activo"] = "El campo 'activo' es requerido y tiene que valer 0 o 1."; }
      if (!$validacionDNI) { $errors["dni"] = "El DNI tiene que ser una cadena exclusiva de números, no debe poseer puntos ni letras."; }
      $httpStatus = 400;
      $payload = generarPayload('error', $httpStatus, $errors, true); 
    }
  } else {
    $httpStatus = 400;
    $payload = generarPayload('error', $httpStatus, 'El ID es inválido.', true); 
    // Si nunca entre ni al primer IF, es porque el ID enviado es inválido.
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// DELETE INQUILINO ID - OK
$app->delete('/inquilinos/{id}', function (Request $request, Response $response, array $args){
  // Se evalua ID.
  $id = $args['id'];
  if(preg_match('/^\d+$/', $id) != 0){
    try {
      $connection = getConnection();
      // Si el ID es válido, evalúo que exista.
      $verificarId = $connection->prepare('SELECT * FROM inquilinos WHERE id = :id');
      $verificarId->bindValue(':id', $id);
      $verificarId->execute();
      // Si rowCount es 0, es porque no existe.
      if($verificarId->rowCount() == 0){
        $httpStatus = 404;
        $payload = generarPayload('error', $httpStatus, 'El ID seleccionado no existe.', true); 
      } else { 
        // Si existe, realizo el DELETE.
        $query = $connection->prepare('DELETE FROM inquilinos WHERE id = :id');
        $query->bindParam(':id', $id);
        $query->execute();

        $httpStatus = 200;
        $payload = generarPayload('success', $httpStatus, "El inquilino $id se eliminó correctamente.", false);
      } 
    }
    catch (PDOException $e) {
      $httpStatus = 500;
      $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
      // Si alguna consulta falló por razones externas, retorno el error del servidor.
    }
  } else {
    $httpStatus = 400;
    $payload = generarPayload('error', $httpStatus, 'El ID es inválido.', true); 
    // Si no entre ni al IF general, es porque el ID no es válido.
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// GET RESERVAS DE INQUILINO. OK
$app->get('/inquilinos/{idInquilino}/reservas', function(Request $request, Response $response, array $args) {
  $id = $args['idInquilino'];
  if(preg_match('/^\d+$/', $id) != 0){
    try {
      $connection = getConnection();
      // no voy a validar la existencia del inquilino. En ningún momento se podria realizar una reserva de un inquilino inexistente o inválido.
      $query = $connection->prepare('SELECT * FROM reservas WHERE inquilino_id = :id');
      $query->bindValue(':id', $id);
      $query->execute();
      if($query->rowCount() > 0){
        $httpStatus = 200;
        $payload = generarPayload('success', $httpStatus, $query->fetchAll(PDO::FETCH_ASSOC), false);
      } else {
        $httpStatus = 400;
        $payload = generarPayload('error', $httpStatus, 'No hay reservas del usuario seleccionado o bien el usuario es inexistente.', true); 
      }
    } catch (PDOException $e) {
      $httpStatus = 500;
      $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
    }
  } else {
    $httpStatus = 400;
    $payload = generarPayload('error', $httpStatus, 'El ID es inválido.', true); 
      // Si no entre al IF general, es porque el ID es inválido.
  }
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// ______________PROPIEDADES.
// GET PROPIEDADES - OK
$app->get('/propiedades', function(Request $request, Response $response){
  // Agarro los query params enviados por URL.
  $filtros = $request->getQueryParams();
  // Se evalúa a si mismo si tiene valor, si lo tiene, lo retorna, sino, null.
  
  $localidad = validacionCadenaNumeros($filtros, 'localidad_id') ? $filtros['localidad_id'] : null;
  $disponible = validacionTinyInt($filtros, 'disponible');
  $fecha_inicio = validacionFormatoFecha($filtros, 'fecha_inicio_disponibilidad') ? $filtros['fecha_inicio_disponibilidad'] : null;
  $cantidad_huespedes = validacionCadenaNumeros($filtros, 'cantidad_huespedes') ? $filtros['cantidad_huespedes'] : null;

  try {
    $connection = getConnection();
    $filtroAplicado = false;
    // Si sé que tengo mínimamente algún filtro aplicado, sé que la sentencia SQL va a tener un WHERE.
    // Si no hay ningún filtro aplicado, se que la sentencia SQL es la básica.
    if ($localidad || $disponible || $fecha_inicio || $cantidad_huespedes){
      $sql = 'SELECT * FROM propiedades WHERE';
      // La sentencia podría tener muchos AND, ya que son varios filtros, podría no tener ninguno hasta el momento, porque recién agregaria el primer filtro
      // En ese caso, no necesitaría un AND primero.
      if ($localidad) { $sql.= ' localidad_id = :localidad'; $filtroAplicado = true;}
      // Si el filtro de localidad fue aplicado, realizo un ternario para agregar un AND a la consulta.
      // Disponible pasa con un valor de 0 o 1, no puedo evaluar eso como condicion unicamente porque si viene 0, PHP lo toma como un valor falso.
      // Por lo que evaluo simplemente que tenga un valor seteado, que sea distinto de nulo.
      if ($disponible) { $sql .= ($filtroAplicado ? ' AND':'') . ' disponible = :disponible'; $filtroAplicado = true; }
      if ($fecha_inicio) { $sql .= ($filtroAplicado ? ' AND':'') . ' fecha_inicio_disponibilidad >= :fecha_inicio'; $filtroAplicado = true; }
      if ($cantidad_huespedes) { $sql .= ($filtroAplicado ? ' AND':'') . ' cantidad_huespedes >= :cantidad_huespedes'; $filtroAplicado = true; }
    } else {
      $sql = 'SELECT * FROM propiedades';
    }
    $query = $connection->prepare($sql);
    if ($localidad) { $query->bindValue(':localidad', $filtros['localidad_id']); }
    if ($disponible) { $query->bindValue(':disponible', $filtros['disponible']); }
    if ($fecha_inicio) { $query->bindValue(':fecha_inicio', $filtros['fecha_inicio_disponibilidad']);}
    if ($cantidad_huespedes) { $query->bindValue(':cantidad_huespedes', $filtros['cantidad_huespedes']); }
    $query->execute();
    
    if ($query->rowCount() == 0) {
      $httpStatus = 200;
      $payload = generarPayload('success', $httpStatus, 'No se encontró ninguna propiedad disponible.', false);
    } else {
      $propiedades = $query->fetchAll(PDO::FETCH_ASSOC);
      $httpStatus = 200;
      $payload = generarPayload('success', $httpStatus, $propiedades, false);
    }
  } catch (PDOException $e) {
    $httpStatus = 500;
    $payload = generarPayload('error', $httpStatus, $e->getMessage(), true);
  }
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// GET PROPIEDADES ID - OK
$app->get('/propiedades/{id}', function (Request $request, Response $response, array $args) {
  $id = $args['id'];
  // Evaluo la validez del ID.
  if(preg_match('/^\d+$/', $id) != 0){
    try {
      $connection = getConnection();
      $verificarId = $connection->prepare('SELECT * FROM propiedades WHERE id = :id');
      $verificarId->bindValue(':id', $id);
      $verificarId->execute();
      // Si retorna 0, es porque no encontró el ID, retorno el error adecuado.
      if($verificarId->rowCount() == 0){
        $httpStatus = 404;
        $payload = generarPayload('error', $httpStatus, 'El ID seleccionado no existe.', true);
      } else {
        $httpStatus = 200;
        // Si encontró una row con ese ID, retorno el inquilino adecuado.
        $propiedad = $verificarId->fetch(PDO::FETCH_ASSOC);
        $payload = generarPayload('success', $httpStatus, $propiedad, false);
      }
    } catch (PDOException $e){
      $httpStatus = 500;
      $payload = generarPayload('error', $httpStatus, $e->getMessage(), true);
      // Si alguna consulta falló por razones externas, retorno el error del servidor.
    }
  } else {
    $httpStatus = 400;
    $payload = generarPayload('error', $httpStatus, 'El ID es inválido.', true); 
    // Si no entre al IF general, es porque el ID es inválido.
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// POST - PROPIEDADES
$app->post('/propiedades', function (Request $request, Response $response) {
  $data = $request->getParsedBody();
  // VALIDACION DE CAMPOS OBLIGATORIOS:
  $validacionDomicilio = isset($data['domicilio']) ? true : false;
  $validacionLocalidadId = validacionCadenaNumeros($data, 'localidad_id');
  $validacionHuespedes = validacionCadenaNumeros($data, 'cantidad_huespedes');
  $validacionDias = validacionCadenaNumeros($data, 'cantidad_dias');
  $validacionDisponibilidad = validacionTinyInt($data, 'disponible');
  $validacionPrecio = validacionCadenaNumeros($data, 'valor_noche');
  $validacionTipoPropiedad = validacionCadenaNumeros($data, 'tipo_propiedad_id');
  $validacionFecha = validacionFormatoFecha($data, 'fecha_inicio_disponibilidad');
  // VALIDACION DE CAMPOS NO OBLIGATORIOS.
  $validacionImagen = isset($data['imagen']) ? true : false;
  $validacionTipoImagen = isset($data['tipo_imagen']) ? true : false;
  $validacionHabitaciones = validacionCadenaNumeros($data, 'cantidad_habitaciones');
  $validacionBaños = validacionCadenaNumeros($data, 'cantidad_banios');
  // la practica dice que cochera es un boolean, la base de datos guarda un integer.
  $validacionCochera = validacionTinyInt($data, 'cochera');
  
  // Si cada variable posee valor TRUE es porque pasaron las validaciones, sino, alguna será falsa, y no entrara al IF.
  // EVALUO PRIMERO TODAS LAS VALIDACIONES QUE SON OBLIGATORIAS.
  $errors = [];
  if($validacionDomicilio && $validacionLocalidadId && $validacionHuespedes && $validacionFecha && $validacionDias && $validacionDisponibilidad && $validacionPrecio && $validacionTipoPropiedad){
    try {
      $connection = getConnection();
      // Tengo que evaluar la existencia del ID de localidad, como también el ID del tipo de propiedad.
      $verificarLocalidadId = $connection->prepare('SELECT * FROM localidades WHERE id = :id');
      $verificarLocalidadId->bindValue(':id', $data['localidad_id']);
      $verificarLocalidadId->execute();
      // Si la propiedad no existe, retorno el error adecuado.
      if($verificarLocalidadId->rowCount() == 0){
        $httpStatus = 404;
        $payload = generarPayload('error', $httpStatus, 'El ID seleccionado de la localidad no existe.', true); 
      } else { 
        $verificarTipoPropiedad = $connection->prepare('SELECT * FROM tipo_propiedades WHERE id = :id');
        $verificarTipoPropiedad->bindValue(':id', $data['tipo_propiedad_id']);
        $verificarTipoPropiedad->execute();
        // Si la propiedad no existe, retorno el error adecuado.
        if($verificarTipoPropiedad->rowCount() == 0){
          $httpStatus = 404;
          $payload = generarPayload('error', $httpStatus, 'El ID seleccionado del tipo de propiedad no existe.', true); 
        } else {
          // SENTENCIA SQL BÁSICA CON LOS VALORES OBLIGATORIOS SEPARADAS EN CAMPOS Y EN VALUES.
          $sql = "INSERT INTO propiedades (domicilio, localidad_id, cantidad_huespedes, fecha_inicio_disponibilidad, disponible, valor_noche, tipo_propiedad_id, cantidad_dias";
          $values = "VALUES (:domicilio, :localidad_id, :cantidad_huespedes, :fecha_inicio_disponibilidad, :disponible, :valor_noche, :tipo_propiedad_id, :cantidad_dias";

          // SI ALGUNA VALIDACION DE LOS CAMPOS OPCIONALES ES TRUE, ES PORQUE SE ENVIÓ DATA EFECTIVA. CONCATENO SECUENCIAS SQL.
          if($validacionImagen) { $sql .= ", imagen"; $values .= ", :imagen"; }
          if($validacionTipoImagen) { $sql .= ", tipo_imagen"; $values .= ", :tipo_imagen"; }
          if($validacionBaños) { $sql .= ", cantidad_banios"; $values .= ", :cantidad_banios"; }
          if($validacionCochera) { $sql .= ", cochera"; $values .= ", :cochera"; }
          if($validacionHabitaciones) { $sql .= ", cantidad_habitaciones"; $values .= ", :cantidad_habitaciones"; }
          
          // LUEGO DE EVALUAR LOS CAMPOS OPCIONALES, CIERRO LA SENTENCIA SQL Y LA CONCATENO.
          $sql .= ") " . $values . ")";
          $query = $connection->prepare($sql);
          // bindeo los parámetros que son obligatorios.
          $query->bindParam(':domicilio', $data['domicilio']);
          $query->bindParam(':localidad_id', $data['localidad_id']);
          $query->bindParam(':cantidad_huespedes', $data['cantidad_huespedes']);
          $query->bindParam(':cantidad_dias', $data['cantidad_dias']);
          $query->bindParam(':fecha_inicio_disponibilidad', $data['fecha_inicio_disponibilidad']);
          $query->bindParam(':disponible', $data['disponible']);
          $query->bindParam(':valor_noche', $data['valor_noche']);
          $query->bindParam(':tipo_propiedad_id', $data['tipo_propiedad_id']);
          // bindeo aquellos parametros opcionales que fueron agregados a la consulta sql por ser válidos.
          if($validacionImagen) { $query->bindParam(':imagen', $data['imagen']); }
          if($validacionTipoImagen) { $query->bindParam(':tipo_imagen', $data['tipo_imagen']); }
          if($validacionBaños) { $query->bindParam(':cantidad_banios', $data['cantidad_banios']); }
          if($validacionCochera) { $query->bindParam(':cochera', $data['cochera']); }
          if($validacionHabitaciones) { $query->bindParam(':cantidad_habitaciones', $data['cantidad_habitaciones']); }
          $query->execute();
          $httpStatus = 201;
          $payload = generarPayload('success', $httpStatus, "La propiedad ubicada en " . $data['domicilio'] . " fue creada correctamente", false);
        }
      }
    } catch (PDOException $e){
      $httpStatus = 500;
      $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
      // Si alguna consulta falló por razones externas, retorno el error del servidor.
    }
  } else {
    if (!$validacionDomicilio) { $errors["domicilio"] = "El Domicilio es requerido."; }
    if (!$validacionLocalidadId) { $errors["idLocalidad"] = "El ID de la localidad es requerido y debe ser válido."; }
    if (!$validacionHuespedes) { $errors["huespedes"] = "La cantidad de huespedes es requerida y debe ser un número."; }
    if (!$validacionDias) { $errors["dias"] = "La cantidad de días es requerida y debe ser un número."; }
    if (!$validacionDisponibilidad) { $errors["disponibilidad"] = "La disponibilidad es requerida y debe tener valor 0 o 1."; }
    if (!$validacionPrecio) { $errors["precio"] = "El precio es requerido y debe ser un número."; }
    if (!$validacionTipoPropiedad) { $errors["tipoPropiedad"] = "El tipo de propiedad es requerido."; }
    if (!$validacionFecha) { $errors["inicioDisponibilidad"] = "El Inicio de disponibilidad es requerido y debe ser una fecha YYYY-MM-DD."; }
    // No se que se retornará en estos casos porque no sé exactamente qué se valida.
    if (!$validacionImagen) { $errors["imagen"] = "Imagen introducido incorrecto"; }
    if (!$validacionTipoImagen) { $errors["tipoImagen"] = "Tipo de imagen introducido incorrecto"; }
    if (!$validacionHabitaciones) { $errors["habitaciones"] = "La cantidad de habitaciones deben ser un número."; }
    if (!$validacionBaños) { $errors["baños"] = "La cantidad de baños deben ser un número."; }
    if (!$validacionCochera) { $errors["cochera"] = "La cochera debe tener valor 0 o 1."; }

    $httpStatus= 400;
    $payload = generarPayload('error', $httpStatus, $errors, true);
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// PUT PROPIEDADES
$app->put('/propiedades/{id}', function (Request $request, Response $response, array $args) {
  $data = $request->getParsedBody();
  $id = $args['id'];
  // Evaluo validez del ID.
  if(preg_match('/^\d+$/', $id) != 0){
    // VALIDACION DE CAMPOS OBLIGATORIOS.
    $validacionDomicilio = isset($data['domicilio']) ? true : false;
    $validacionLocalidadId = validacionCadenaNumeros($data, 'localidad_id');
    $validacionHuespedes = validacionCadenaNumeros($data, 'cantidad_huespedes');
    $validacionDias = validacionCadenaNumeros($data, 'cantidad_dias');
    $validacionDisponibilidad = validacionTinyInt($data, 'disponible');
    $validacionPrecio = validacionCadenaNumeros($data, 'valor_noche');
    $validacionTipoPropiedad = validacionCadenaNumeros($data, 'tipo_propiedad_id');
    $validacionFecha = validacionFormatoFecha($data, 'fecha_inicio_disponibilidad');
    // VALIDACION DE CAMPOS NO OBLIGATORIOS.
    $validacionImagen = isset($data['imagen']) ? true : false;
    $validacionTipoImagen = isset($data['tipo_imagen']) ? true : false;
    $validacionHabitaciones = validacionCadenaNumeros($data, 'cantidad_habitaciones');
    $validacionBaños = validacionCadenaNumeros($data, 'cantidad_banios');
    // la practica dice que cochera es un boolean, la base de datos guarda un integer.
    $validacionCochera = validacionTinyInt($data, 'cochera');
    
    // Si el ID es válido, evalúo que la información enviada haya pasado las validaciones.
    $errors = [];
    if($validacionDomicilio && $validacionLocalidadId && $validacionHuespedes && $validacionFecha && $validacionDias && $validacionDisponibilidad && $validacionPrecio && $validacionTipoPropiedad){
      try {
        $connection = getConnection();
        // Si todo está correcto, evalúo que el ID exista efectivamente en la base. 
        $verificarId = $connection->prepare('SELECT * FROM propiedades WHERE id = :id');
        $verificarId->bindValue(':id', $id);
        $verificarId->execute();
        // Si retorna 0, retorno que el ID no existe.
        if($verificarId->rowCount() == 0){
          $httpStatus = 404;
          $payload = generarPayload('error', $httpStatus, 'El ID seleccionado no existe.', true); 
        } else { 
          // Tengo que evaluar la existencia del ID de localidad, como también el ID del tipo de propiedad.
          $verificarLocalidadId = $connection->prepare('SELECT * FROM localidades WHERE id = :id');
          $verificarLocalidadId->bindValue(':id', $data['localidad_id']);
          $verificarLocalidadId->execute();
          // Si la propiedad no existe, retorno el error adecuado.
          if($verificarLocalidadId->rowCount() == 0){
            $httpStatus = 404;
            $payload = generarPayload('error', $httpStatus, 'El ID seleccionado de la localidad no existe.', true); 
          } else { 
            $verificarTipoPropiedad = $connection->prepare('SELECT * FROM tipo_propiedades WHERE id = :id');
            $verificarTipoPropiedad->bindValue(':id', $data['tipo_propiedad_id']);
            $verificarTipoPropiedad->execute();
            // Si la propiedad no existe, retorno el error adecuado.
            if($verificarTipoPropiedad->rowCount() == 0){
              $httpStatus = 404;
              $payload = generarPayload('error', $httpStatus, 'El ID seleccionado del tipo de propiedad no existe.', true); 
            } else {
              // SENTENCIA SQL BÁSICA CON LOS VALORES OBLIGATORIOS SEPARADAS EN CAMPOS Y EN VALUES.
              $sql = "UPDATE propiedades SET domicilio = :domicilio, localidad_id = :localidad_id, cantidad_huespedes = :cantidad_huespedes, fecha_inicio_disponibilidad = :fecha_inicio_disponibilidad, disponible = :disponible, valor_noche = :valor_noche, tipo_propiedad_id = :tipo_propiedad_id, cantidad_dias = :cantidad_dias,";
    
              // SI ALGUNA VALIDACION DE LOS CAMPOS OPCIONALES ES TRUE, ES PORQUE SE ENVIÓ DATA EFECTIVA. CONCATENO SECUENCIA SQL.
              if($validacionImagen) { $sql .= " imagen = :imagen,"; }
              if($validacionTipoImagen) { $sql .= " tipo_imagen = :tipo_imagen,"; }
              if($validacionBaños) { $sql .= " cantidad_banios = :cantidad_banios,"; }
              if($validacionCochera) { $sql .= " cochera = :cochera,"; }
              if($validacionHabitaciones) { $sql .= " cantidad_habitaciones = :cantidad_habitaciones,"; }
              
              // LUEGO DE EVALUAR LOS CAMPOS OPCIONALES, ELIMINO LA ÚLTIMA COMA SOBRANTE, QUE SOBRA CON O SIN ARGUMENTOS OPCIONALES PASADOS.
              $sql = rtrim($sql, ',');
              // LUEGO DE ELIMINAR LA ULTIMA COMA, AGREGO EL ID AL QUE HAY QUE ACTUALIZAR.
              $sql .= " WHERE id = :id";
              $query = $connection->prepare($sql);
              // bindeo los parámetros que son obligatorios.
              $query->bindParam(':domicilio', $data['domicilio']);
              $query->bindParam(':localidad_id', $data['localidad_id']);
              $query->bindParam(':cantidad_huespedes', $data['cantidad_huespedes']);
              $query->bindParam(':cantidad_dias', $data['cantidad_dias']);
              $query->bindParam(':fecha_inicio_disponibilidad', $data['fecha_inicio_disponibilidad']);
              $query->bindParam(':disponible', $data['disponible']);
              $query->bindParam(':valor_noche', $data['valor_noche']);
              $query->bindParam(':tipo_propiedad_id', $data['tipo_propiedad_id']);
              $query->bindParam(':id', $id);
              // bindeo aquellos parametros opcionales que fueron agregados a la consulta sql por ser válidos.
              if($validacionImagen) { $query->bindParam(':imagen', $data['imagen']); }
              if($validacionTipoImagen) { $query->bindParam(':tipo_imagen', $data['tipo_imagen']); }
              if($validacionBaños) { $query->bindParam(':cantidad_banios', $data['cantidad_banios']); }
              if($validacionCochera) { $query->bindParam(':cochera', $data['cochera']); }
              if($validacionHabitaciones) { $query->bindParam(':cantidad_habitaciones', $data['cantidad_habitaciones']); }
              $query->execute();
              $httpStatus = 201;
              // retornar un posible payload con errores, pero que la creacion se hizo correctamente, esto puede pasar por los campos no obligatorios.
              $payload = generarPayload('success', $httpStatus, "La propiedad $id fue actualizada correctamente.", false);
            }
          }
        }
      } catch (PDOException $e){
        $httpStatus = 500;
        $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
        // Si alguna consulta falló por razones externas, retorno el error del servidor.
      }
    } else { 
      if (!$validacionDomicilio) { $errors["domicilio"] = "El Domicilio es requerido."; }
      if (!$validacionLocalidadId) { $errors["idLocalidad"] = "El ID de la localidad es requerido y debe ser válido."; }
      if (!$validacionHuespedes) { $errors["huespedes"] = "La cantidad de huespedes es requerida y debe ser un número."; }
      if (!$validacionDias) { $errors["dias"] = "La cantidad de días es requerida y debe ser un número."; }
      if (!$validacionDisponibilidad) { $errors["disponibilidad"] = "La disponibilidad es requerida y debe tener valor 0 o 1."; }
      if (!$validacionPrecio) { $errors["precio"] = "El precio es requerido y debe ser un número."; }
      if (!$validacionTipoPropiedad) { $errors["tipoPropiedad"] = "El tipo de propiedad es requerido."; }
      if (!$validacionFecha) { $errors["inicioDisponibilidad"] = "El Inicio de disponibilidad es requerido y debe ser una fecha YYYY-MM-DD."; }
      // No se que se retornará en estos casos porque no sé exactamente qué se valida.
      if (!$validacionImagen) { $errors["imagen"] = "Imagen introducido incorrecto"; }
      if (!$validacionTipoImagen) { $errors["tipoImagen"] = "Tipo de imagen introducido incorrecto"; }
      if (!$validacionHabitaciones) { $errors["habitaciones"] = "La cantidad de habitaciones deben ser un número."; }
      if (!$validacionBaños) { $errors["baños"] = "La cantidad de baños deben ser un número."; }
      if (!$validacionCochera) { $errors["cochera"] = "La cochera debe tener valor 0 o 1."; }
      $httpStatus= 400;
      $payload = generarPayload('error', $httpStatus, $errors, true); 
    }
  } else {
    $httpStatus = 400;
    $payload = generarPayload('error', $httpStatus, 'El ID es inválido.', true); 
    // Si nunca entre ni al primer IF, es porque el ID enviado es inválido.
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// DELETE PROPIEDADES - OK
$app->delete('/propiedades/{id}', function (Request $request, Response $response, array $args) {
  // Se evalua ID.
  $id = $args['id'];
  if(preg_match('/^\d+$/', $id) != 0){
    try {
      $connection = getConnection();
      // Si el ID es válido, evalúo que exista.
      $verificarId = $connection->prepare('SELECT * FROM propiedades WHERE id = :id');
      $verificarId->bindValue(':id', $id);
      $verificarId->execute();
      // Si rowCount es 0, es porque no existe.
      if($verificarId->rowCount() == 0){
        $httpStatus = 404;
        $payload = generarPayload('error', $httpStatus, 'El ID seleccionado no existe.', true); 
      } else { 
        $httpStatus = 200;
        // Si existe, realizo el DELETE.
        $query = $connection->prepare('DELETE FROM propiedades WHERE id = :id');
        $query->bindParam(':id', $id);
        $query->execute();
        $payload = generarPayload('success', $httpStatus, "Propiedad $id eliminada correctamente.", false);
      } 
    }
    catch (PDOException $e) {
      $httpStatus = 500;
      $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
      // Si alguna consulta falló por razones externas, retorno el error del servidor.
    }
  } else {
    $httpStatus = 400;
    $payload = generarPayload('error', $httpStatus, 'El ID es inválido.', true); 
    // Si no entre ni al IF general, es porque el ID no es válido.
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// ______________RESERVAS.
// GET RESERVAS - OK
$app->get('/reservas', function(Request $request, Response $response){
  try {
    $connection = getConnection();
    $query = $connection->prepare('SELECT * FROM reservas');
    $query->execute();
    $reservas = $query->fetchAll(PDO::FETCH_ASSOC);
    $httpStatus = 200;
    $payload = generarPayload('success', $httpStatus, $reservas, false);
  } catch (PDOException $e) {
    $httpStatus = 500;
    $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
  }
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

$app->post('/reservas', function(Request $request, Response $response){
  // Evaluo los campos requeridos para la reserva. Que los datos estén seteados, que sus cadenas sean números, y que la fecha pueda ser formateada a DateTime.
  $data = $request->getParsedBody();
  $validacionPropiedad = validacionCadenaNumeros($data, 'propiedad_id');
  $validacionInquilino = validacionCadenaNumeros($data, 'inquilino_id');
  $validacionNoches = validacionCadenaNumeros($data, 'cantidad_noches');
  $validacionFecha = validacionFormatoFecha($data, 'fecha_desde');

  $errors = [];
  if ($validacionPropiedad && $validacionInquilino && $validacionFecha && $validacionNoches){
    $fecha_desde = new DateTime($data['fecha_desde']);
    // Si pasa la validación, verifico que la propiedad elegida existe.
    try {
      $connection = getConnection();
      $verificarPropiedadId = $connection->prepare('SELECT * FROM propiedades WHERE id = :id');
      $verificarPropiedadId->bindValue(':id', $data['propiedad_id']);
      $verificarPropiedadId->execute();
      // Si la propiedad no existe, retorno el error adecuado.
      if($verificarPropiedadId->rowCount() == 0){
        $httpStatus = 404;
        $payload = generarPayload('error', $httpStatus, "El ID seleccionado de la propiedad no existe.", true); 
      } else { 
        // Si la propiedad existe, verifico la existencia del inquilino seleccionado.
        $verificarInquilinoId = $connection->prepare('SELECT * FROM inquilinos WHERE id = :id');
        $verificarInquilinoId->bindValue(':id', $data['inquilino_id']);
        $verificarInquilinoId->execute();
        // Si el inquilino no existe, retorno el error adecuado.
        if($verificarInquilinoId->rowCount() == 0){
          $httpStatus = 404;
          $payload = generarPayload('error', $httpStatus, "El ID seleccionado del inquilino no existe.", true);
        } else { 
          // Si la propiedad y el inquilino existen, evalúo que la fecha para la reserva es válida, es decir, la fecha en la que se dispone la propiedad es menor a la fecha que yo quiero
          // reservar, es decir, reservo después o al mismo tiempo del inicio de la disponibilidad.
          // Fetcheo la propiedad para poder evaluar si la disponibilidad con respecto a la solicitud de reserva es válida.
          $propiedad = $verificarPropiedadId->fetch(PDO::FETCH_ASSOC);
          // para evaluar fecha 
          $dispone_desde = $propiedad['fecha_inicio_disponibilidad'];
          $dispone_desde = new DateTime($dispone_desde);
          // Verifico que el inquilino que está realizando la reserva también está activo, sino, no puede realizar una reserva.
          $inquilino = $verificarInquilinoId->fetch(PDO::FETCH_ASSOC);
          $user_is_active = $inquilino['activo']; // EVALUAR SI USER ESTA ACTIVO

          // VALIDACIONES.
          $fechaOK = ($dispone_desde <= $fecha_desde) ? true : false;
          $userOK =  ($user_is_active == 1) ? true : false;
          $disponibilidadOK = ($propiedad['disponible'] == 1) ? true : false; 
          // si cantidad de noches es menor a la cantidad de dias disponibles, es valida la cantidad de reserva realizada.
          $cantidadOK = ($data['cantidad_noches'] < $propiedad['cantidad_dias']) ? true : false;
          // Si las fechas son correctas, y el usuario está activo, realizo la reserva, y la disponibilidad de la propiedad pasa a estar en OFF.
          if ($fechaOK && $userOK && $disponibilidadOK & $cantidadOK) {
            // Necesito guardar el valor total que va a costar la reserva, por lo que necesito saber el costo por noche de la propiedad
            // para luego multiplicarlo por la cantidad de noches reservada.
            $valor_noche = $propiedad['valor_noche'];
            $valorTotal = $valor_noche * $data['cantidad_noches'];
            $query = $connection->prepare('INSERT INTO reservas (propiedad_id, inquilino_id, fecha_desde, cantidad_noches, valor_total) VALUES (:propiedad_id, :inquilino_id, :fecha_desde, :cantidad_noches, :valor_total)');
            $query->bindValue(':propiedad_id', $data['propiedad_id']);
            $query->bindValue(':inquilino_id', $data['inquilino_id']);
            $query->bindValue(':fecha_desde', $data['fecha_desde']);
            $query->bindValue(':cantidad_noches', $data['cantidad_noches']);
            $query->bindValue(':valor_total', $valorTotal); // valor_noche de propiedad * cantidad noches seleccionada.
            $query->execute();
            // La reserva se realizó, pero tengo que cambiar ahora la disponibilidad de la propiedad.
            $query = $connection->prepare('UPDATE propiedades SET disponible = 0 WHERE id = :id');
            // $query->bindValue(':disponibilidad', 0);
            $query->bindValue(':id', $propiedad['id']);
            $httpStatus = 200;
            $query->execute();
            $payload = generarPayload('success', $httpStatus, $data, false);
          } else {
            $httpStatus = 400;
            if (!$fechaOK) { $errors['dispone_desde'] = "La reserva debe estar en una fecha posterior al inicio de la disponibilidad de la propiedad."; }
            if (!$userOK) { $errors['usuario'] = "El usuario seleccionado no está activo para realizar una reserva."; }
            if (!$disponibilidadOK) { $errors['propiedad'] = "La propiedad seleccionada no está disponible para realizar una reserva."; }
            if (!$cantidadOK) { $errors['cantidad'] = "La propiedad seleccionada no tiene los suficientes días disponibles para realizar una reserva de esa extensión."; }
            $payload = generarPayload('error', $httpStatus, $errors, true);
          }
        }
      } 
    } catch(PDOException $e) {
      $httpStatus = 500;
      $payload = generarPayload('error', $httpStatus, $e->getMessage(), true);
      // Si alguna consulta falló por razones externas, retorno el error del servidor.
    }
  } else {
    if (!$validacionPropiedad) { $errors['propiedad'] = "La propiedad es requerida y debe ser válida.";}
    if (!$validacionInquilino) { $errors['inquilino'] = "El inquilino es requerido y debe ser válido."; }
    if (!$validacionFecha) { $errors['fechaVacia'] = "La fecha es requerida, debe ser válida y tiene que tener el formato YYYY-MM-DD."; }
    if (!$validacionNoches) { $errors['noches'] = "La cantidad de noches es requerida y debe ser numérica."; }
    $httpStatus = 400;
    $payload = generarPayload('error', $httpStatus, $errors, true);
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// PUT RESERVAS - OK
$app->put('/reservas/{id}', function (Request $request, Response $response, array $args) {
  $id = $args['id'];

  if(preg_match('/^\d+$/', $id) != 0){
    $data = $request->getParsedBody();
    $validacionPropiedad = validacionCadenaNumeros($data, 'propiedad_id');
    $validacionInquilino = validacionCadenaNumeros($data, 'inquilino_id');
    $validacionNoches = validacionCadenaNumeros($data, 'cantidad_noches');
    $validacionFecha = validacionFormatoFecha($data, 'fecha_desde');
    $errors = [];
    if ($validacionPropiedad && $validacionInquilino && $validacionFecha && $validacionNoches){
      try {
        $connection = getConnection();
        // Evalúo que la reserva exista.
        $getReserva = $connection->prepare('SELECT * FROM reservas WHERE id = :id');
        $getReserva->bindValue('id', $id);
        $getReserva->execute();
        if($getReserva->rowCount() == 0){
          $httpStatus = 404;
          $payload = generarPayload('error', $httpStatus, 'El ID seleccionado de la reserva no existe.', true);
        } else {
          // Si la reserva existe en la base, evalúo que la propiedad seleccionada exista (No se por qué editaría una reserva cambiándola de propiedad);
          // Si cambio la propiedad de una reserva, debería evaluar si la propiedad es igual a antes de updatear la reserva
          // Si es la misma no pasa nada, pero si no es la misma, tendría que cambiar la disponibilidad en ambas
          // La vieja pasaría a estar de nuevo en disponible, la nueva pasaría a estar ocupada, además de que debería evaluar las fechas de la nueva propiedad de nuevo.
          $propiedadQuery = $connection->prepare('SELECT * FROM propiedades WHERE id = :id');
          $propiedadQuery->bindValue('id', $data['propiedad_id']);
          $propiedadQuery->execute();
          // Evaluo si la propiedad seleccionada existe, si no, retorno el error adecuado.
          if($propiedadQuery->rowCount() == 0){
            $httpStatus = 404;
            $payload = generarPayload('error', $httpStatus, 'El ID seleccionado para la propiedad no existe.', true);
          } else {
            // Si llegué acá, significa que la propiedad y la reserva pasaron la validación y ambas son existentes y válidas.
            // Fetcheo tanto la propiedad como la reserva, para poder evaluar las siguientes condiciones:
            // La propiedad seleccionada para evaluar si está disponible, y también para posteriormente evaluar cuánto cuesta por noche, para calcular el total.
            // La reserva seleccionada, para evaluar su fecha de inicio, y si la fecha de inicio es mayor a la fecha actual, significa que todavía estoy a tiempo de editarla.
            // Pero también tengo que evaluar que la nueva fecha ingresada es mayor a la fecha actual, en ese caso, las fechas van a ser válidas.
            $propiedad = $propiedadQuery->fetch(PDO::FETCH_ASSOC);
            $reserva = $getReserva->fetch(PDO::FETCH_ASSOC);
            // reserva['propiedad_id'] -> propiedad vieja
            // $propiedad['id'] -> valor nuevo
            // Genero una fecha actual, una para la reserva original, y otra para la nueva reserva editada.
            $fecha_actual = new DateTime();
            $fecha_reserva = new DateTime($reserva['fecha_desde']);
            $fecha_actualizada = new DateTime($data['fecha_desde']);
            $fecha_propiedad = new DateTime($propiedad['fecha_inicio_disponibilidad']);

            $fecha_reservaOK = $fecha_reserva > $fecha_actual ? true : false;
            
            $fecha_actualOK = $fecha_actualizada > $fecha_actual ? true : false;
            // tengo que evaluar fecha anterior, fecha nueva con respecto a la actual.
            // si la propiedad vieja es igual a la nueva seleccionada
            $fecha_propiedadOK = $fecha_actualizada > $fecha_propiedad ? true : false;
            $cantidadOK = ($data['cantidad_noches'] < $propiedad['cantidad_dias']) ? true : false;
            if ($reserva['propiedad_id'] == $propiedad['id']){
              // evaluo que las fechas estén bien y no evaluo que la disponibilidad esté en 1 ya que se sabe que está en 0.
              if ($fecha_reservaOK && $fecha_actualOK && $fecha_propiedadOK && $cantidadOK) {
                $valor_noche = $propiedad['valor_noche'];
                $valorTotal = $valor_noche * $data['cantidad_noches'];
                // se sabe de antemano que la propiedad es la misma, por lo que no se actualiza.
                $query = $connection->prepare('UPDATE reservas SET inquilino_id = :inquilino_id, fecha_desde = :fecha_desde, cantidad_noches = :cantidad_noches, valor_total = :valor_total WHERE id = :id');
                $query->bindValue(':inquilino_id', $data['inquilino_id']); // should be retrieved by a security library.
                $query->bindValue(':fecha_desde', $data['fecha_desde']); // evaluate
                $query->bindValue(':cantidad_noches', $data['cantidad_noches']);
                $query->bindValue(':valor_total', $valorTotal);
                $query->bindValue(':id', $id);
                $query->execute();
                
                $httpStatus = 200;
                // acomodo el payload.
                $payload = generarPayload('success', $httpStatus, $data, false);
              } else {
                $httpStatus = 400;
                if(!$fecha_reservaOK) { $errors['fecha'] = 'La reserva ya arrancó y no puede ser editada.'; }
                if(!$fecha_actualOK) { $errors['fecha_actual'] = 'La fecha de la reserva tiene que ser posterior a la actual.'; }
                if(!$fecha_propiedadOK) { $errors['fecha_propiedad'] = 'La fecha de la reserva tiene que ser posterior a la fecha disponible de la propiedad.'; }
                if(!$cantidadOK) { $errors['cantidad_noches'] = 'La cantidad de noches debe ser menor a la cantidad disponible de días de la propiedad.'; }
                $payload = generarPayload('error', $httpStatus, $errors, true); 
              }
            } else {
              // si la propiedad seleccionada es distinta, evaluo su disponibilidad.
              $propiedad_OK = $propiedad['disponible'] == 1 ? true : false;
              if ($fecha_reservaOK && $fecha_actualOK && $propiedad_OK && $cantidadOK) {
                // antes de actualizar la reserva, uso su viejo valor en propiedad para volver a setear en disponible la vieja propiedad.
                $query = $connection->prepare('UPDATE propiedades SET disponible = 1 WHERE id = :id');
                $query->bindValue(':id', $reserva['propiedad_id']);
                $query->execute();
                // La propiedad nueva seleccionada tiene posiblemente otro costo. Se calcula.
                $valor_noche = $propiedad['valor_noche'];
                $valorTotal = $valor_noche * $data['cantidad_noches'];
                // Se debe actualizar la propiedad_id en la reserva.
                $query = $connection->prepare('UPDATE reservas SET propiedad_id = :propiedad_id, inquilino_id = :inquilino_id, fecha_desde = :fecha_desde, cantidad_noches = :cantidad_noches, valor_total = :valor_total WHERE id = :id');
                $query->bindValue(':propiedad_id', $data['propiedad_id']);
                $query->bindValue(':inquilino_id', $data['inquilino_id']); // should be retrieved by a security library.
                $query->bindValue(':fecha_desde', $data['fecha_desde']); // evaluate
                $query->bindValue(':cantidad_noches', $data['cantidad_noches']);
                $query->bindValue(':valor_total', $valorTotal);
                $query->bindValue(':id', $id);
                $query->execute();

                // Se actualiza propiedad nueva con disponibilidad 0.
                $query = $connection->prepare('UPDATE propiedades SET disponible = 0 WHERE id = :id');
                $query->bindValue(':id', $propiedad['id']);
                $query->execute();
                
                $httpStatus = 200;
                $payload = generarPayload('success', $httpStatus, $data, false);
              } else {
                if(!$fecha_reservaOK) { $errors['fecha'] = 'La reserva ya arrancó y no puede ser editada.'; }
                if(!$fecha_actualOK) { $errors['fecha_actual'] = 'La fecha de la reserva tiene que ser posterior a la actual.'; }
                if(!$propiedad_OK) { $errors['propiedad'] = 'La propiedad seleccionada no está disponible.'; }
                if(!$fecha_propiedadOK) { $errors['fecha_propiedad'] = 'La fecha de la reserva tiene que ser posterior a la fecha disponible de la propiedad.'; }
                if(!$cantidadOK) { $errors['cantidad_noches'] = 'La cantidad de noches debe ser menor a la cantidad disponible de días de la propiedad.'; }

                $httpStatus = 400;
                $payload = generarPayload('error', $httpStatus, $errors, true); 
              }
            }
          }
        }
      } catch (PDOException $e){
        $httpStatus = 500;
        $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
      }
    } else {
      if (!$validacionPropiedad) { $errors['propiedad'] = "La propiedad es requerida y debe ser válida.";}
      if (!$validacionInquilino) { $errors['inquilino'] = "El inquilino es requerido y debe ser válido.";}
      if (!$validacionFecha) { $errors['fecha'] = "La fecha es requerida, debe ser válida y debe estar en un formato YYYY-MM-DD.";}
      if (!$validacionNoches) { $errors['noches'] = "La cantidad de noches es requerida y debe ser numérica.";}
      $httpStatus = 400;
      $payload = generarPayload('error', $httpStatus, $errors, true); 
    }
  } else {
    $httpStatus = 400;
    $payload = generarPayload('error', $httpStatus, 'El ID de la reserva es inválido.', true); 
    // Si no entre ni al IF general, es porque el ID no es válido.
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

// DELETE RESERVAS
$app->delete('/reservas/{id}', function (Request $request, Response $response, array $args){
  $id = $args['id'];
  if(preg_match('/^\d+$/', $id) != 0){
    try {
      $connection = getConnection();
      // Evalúo que la reserva exista.
      $getReserva = $connection->prepare('SELECT * FROM reservas WHERE id = :id');
      $getReserva->bindValue('id', $id);
      $getReserva->execute();

      if($getReserva->rowCount() == 0){
        $httpStatus = 404;
        $payload = generarPayload('error', $httpStatus, 'El ID seleccionado no existe.', true); 
      } else {
        // Si la reserva existe, evalúo las fechas.
        $reserva = $getReserva->fetch(PDO::FETCH_ASSOC);
        $hora_actual = new DateTime();
        $dia_reserva = new DateTime($reserva['fecha_desde']);
        // Si la fecha en la que arranca la reserva es todavía mayor al día actual, significa que tengo tiempo para eliminarla.
        if ($dia_reserva > $hora_actual) {
          $propiedadId = $reserva['propiedad_id'];
          $query = $connection->prepare('UPDATE propiedades SET disponible = 1 WHERE id = :id');
          $query->bindParam(':id', $propiedadId);
          $query->execute();

          $query = $connection->prepare('DELETE FROM reservas WHERE id = :id');
          $query->bindParam(':id', $id);
          $query->execute();

          $httpStatus = 200;
          $payload = generarPayload('success', $httpStatus, $reserva, false);
        } else {
          $httpStatus = 400;
          $payload = generarPayload('error', $httpStatus, 'No se puede eliminar una reserva que ya arrancó.', true); 
          // Si la fecha es inválida, significa que quiero deletear una reserva que ya arrancó, ya que la fecha actual es mayor a fecha_Desde
        }
      } 
    } catch (PDOException $e) {
      $httpStatus = 500;
      $payload = generarPayload('error', $httpStatus, $e->getMessage(), true); 
      // Si alguna consulta falló por razones externas, envío el error del servidor.
    }
  } else {
    $httpStatus = 400;
    // Si no entre al IF general, es porque el ID es inválido.
    $payload = generarPayload('error', $httpStatus, 'El ID es inválido.', true); 
  }
  // retorno la response con el $payload.
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type','application/json')->withStatus($httpStatus);
});

$app->run();