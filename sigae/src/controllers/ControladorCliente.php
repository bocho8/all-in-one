<?php
require_once '../src/models/Cliente.php';

use Google_Client;

class ControladorCliente{
    private $cliente;

    public function __construct(){
        $this->cliente=new Cliente();
    }
    function login(){
        include '../src/views/account/login.html';
    }
    function doLogin(){
        $response=['success' => false, 'errors' => [], 'debug' => []];

        // Debug: Log all received data
        $response['debug']['received_data']=$_POST;

        // Validacion de campos vacios
        if (isset($_POST["email"], $_POST["contrasena"]) && 
            !empty($_POST["email"]) && !empty($_POST["contrasena"])) {

            $email = $_POST["email"];
            $contrasena = $_POST["contrasena"];

            // Debug: Log processed data
            $response['debug']['processed_data'] = [
                'email' => $email,
                'contrasena' => 'REDACTED',
            ];

            // Validar email
            if (!$this->validarEmail($email, 63)) {
                $response['errors'][] = "Por favor, ingrese un correo electrónico válido.";
            } elseif (!$this->validarContrasena($contrasena, 6, 60)) {
                $response['errors'][] = "La contraseña debe tener entre 6 y 60 caracteres.";
            } else {
                if ($this->cliente->iniciarCliente($email, $contrasena)) {
                    $response['success'] = true;
                    
                    // Iniciar sesión del cliente
                    session_start();
                    $_SESSION['logged']= true;
                    $_SESSION['id']=$this->cliente->getId();
                    $_SESSION['ci']=$this->cliente->getCi();
                    $_SESSION['email']=$this->cliente->getEmail();
                    $_SESSION['nombre']=$this->cliente->getNombre();
                    $_SESSION['apellido']=$this->cliente->getApellido();
                    $_SESSION['telefono']=$this->cliente->getTelefono();

                    // Redirigir a la home page
                    header('Location: index.php?action=home');
                } else {
                    $response['errors'][] = "Error al iniciar sesión.";
                }
            }
        } else {
            $response['errors'][] = "Debe llenar todos los campos.";
            // Debug: Log which fields are missing
            $response['debug']['missing_fields'] = array_diff(
                ['email', 'contrasena'],
                array_keys($_POST)
            );
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    function doLoginOAuth(){
        session_start();

        // Configurar cliente Google
        $client= new GoogleClient();
        $client->setAuthConfig('/var/www/html/private/credentials.json');
        $client->setRedirectUri('http://yourdomain.com/index.php?action=doLoginOAuth')
        $client->addScope('email');
        $client->addScope('profile');
        $client->addScope('nombre');

        if (!isset($_GET['code'])) {
            $auth_url = $client->createAuthUrl();
            header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
            exit();
        } else {
            // Procesa la respuesta de Google
            $client->authenticate($_GET['code']);
            $_SESSION['access_token'] = $client->getAccessToken();

            // Obtén la información del perfil del usuario
            $oauth2 = new \Google_Service_Oauth2($client);
            $userInfo = $oauth2->userinfo->get();

            $perfil = $userInfo->profile;

            if ($this->cliente->iniciarCliente($email, null)) {
                $response['success'] = true;
                
                // Iniciar sesión del cliente
                session_start();
                $_SESSION['logged']= true;
                $_SESSION['id']=$this->cliente->getId();
                $_SESSION['ci']=$this->cliente->getCi();
                $_SESSION['email']=$this->cliente->getEmail();
                $_SESSION['nombre']=$this->cliente->getNombre();
                $_SESSION['apellido']=$this->cliente->getApellido();
                $_SESSION['telefono']=$this->cliente->getTelefono();
                $_SESSION['perfil']=$perfil;

                // Redirigir a la home page
                header('Location: index.php?action=home');
            } else {
                $response['errors'][] = "Error al iniciar sesión.";
            }
        }
    }
    function signup(){
        include '../src/views/account/signUp.html';
    }
    function doSignup(){
        $response=['success' => false, 'errors' => [], 'debug' => []];

        // Debug: Log all received data
        $response['debug']['received_data']=$_POST;

        // Validacion de campos vacios
        if (isset($_POST["email"], $_POST["nombre"], $_POST["apellido"], $_POST["contrasena"], $_POST["repContrasena"]) && 
            !empty($_POST["email"]) && !empty($_POST["nombre"]) && !empty($_POST["apellido"]) && !empty($_POST["contrasena"]) && !empty($_POST["repContrasena"])) {

            $email = $_POST["email"];
            $nombre = $_POST["nombre"];
            $apellido = $_POST["apellido"];
            $contrasena = $_POST["contrasena"];
            $repContrasena = $_POST["repContrasena"];

            // Debug: Log processed data
            $response['debug']['processed_data'] = [
                'email' => $email,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'contrasena' => 'REDACTED',
                'repContrasena' => 'REDACTED'
            ];

            if (!$this->validarEmail($email, 63)) {
                $response['errors'][] = "Por favor, ingrese un correo electrónico válido.";

            } elseif (!$this->validarNombreApellido($nombre, 23) || !$this->validarNombreApellido($apellido, 23)) {
                $response['errors'][] = "Por favor, ingrese un nombre o apellido válido.";

            } elseif (!$this->validarContrasena($contrasena, 6, 60)) {
                $response['errors'][] = "Use un mínimo de 6 caracteres con mayúsculas, minúsculas y números.";

            } elseif ($contrasena !== $repContrasena) {
                $response['errors'][] = "Las contraseñas no coinciden.";

            } elseif(Cliente::existeEmail($email)) {
                $response['errors'][]= "Ya existe un usuario con el correo ingresado.";

            } elseif(!$this->cliente->guardarCliente(null, $email, $contrasena, $nombre, $apellido, null)){
                $response['errors'][] = "Error al registrarse.";

            } else {
                $response['success'] = true;
                // Redirigir al login
                header('Location: index.php?action=login');
            }
            
        } else {
            $response['errors'][] = "Debe llenar todos los campos.";
            // Debug: Log which fields are missing
            $response['debug']['missing_fields'] = array_diff(
                ['email', 'nombre', 'apellido', 'contrasena', 'repContrasena'],
                array_keys($_POST)
            );
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    function doLogout(){
        // TODO: Implementar log out de la sesion
    }
    function forgotPassword(){
        include '../src/views/account/forgotPassword.html';
    }
    function services(){
        session_start();
        error_log($_SESSION['email']. " abrió la página de servicios");
        error_log(print_r($_SESSION, true));
        include '../src/views/client/serviciosMecanicos.html';
    }
    function bookService(){
        session_start();
        error_log($_SESSION['email']. " abrió la página de reserva de servicios");
        error_log(print_r($_SESSION, true));

        $this->cargarMisVehiculosAjax($_SESSION['id']);
        include '../src/views/client/reservarServicio.html';
    }
    
    function parking(){
        session_start();
        error_log($_SESSION['email']. " abrió la página de AIO Parking");
        error_log(print_r($_SESSION, true));
        
        include '../src/views/client/aioParking.html';
    }

    function parkingSimple(){
        session_start();
        error_log($_SESSION['email']. " abrió la página de reserva de parking simple");
        error_log(print_r($_SESSION, true));

        $this->cargarMisVehiculosAjax($_SESSION['id']);
        include '../src/views/client/reservarParkingSimple.html';
    }

    function parkingLongTerm(){
        session_start();
        error_log($_SESSION['email']. " abrió la página de reserva de parking de largo plazo");
        error_log(print_r($_SESSION, true));

        /*
        Implementar dropdown de vehiculos
        $this->cargarMisVehiculosAjax($_SESSION['id']);
        */
        
        include '../src/views/client/reservarParkingLargoPlazo.html';
    }

    function products(){
        session_start();
        error_log($_SESSION['email']. " abrió la página del catálogo de productos");
        error_log(print_r($_SESSION, true));
        include '../src/views/client/catalogo.html';
    }

    function myUser(){
        include '../src/views/client/miUsuario.html';
    }
    function home(){
        session_start();
        error_log($_SESSION['email']. " abrió la página home");
        error_log(print_r($_SESSION, true));
        include '../src/views/client/homeCliente.html';
    }

    public function cargarMisVehiculosAjax($id) {
        
        // Ruta del archivo JSON donde se guardarán los vehículos
        $filePath = 'data/misVehiculos.json';

        // Limpiar archivo json
        file_put_contents($filePath, json_encode([]));

        $misVehiculos = $this->cliente->cargarMisVehiculos($id);
        
        if ($misVehiculos) {
            // Guardar los vehículos en formato JSON
            $jsonData = json_encode($misVehiculos, JSON_PRETTY_PRINT);
            file_put_contents($filePath, $jsonData);
        } else {
            // Si no hay vehículos, guardar el archivo vacío
            file_put_contents($filePath, json_encode([]));
        }
        
        // Validar errores y registrar en error_log
        if (file_exists($filePath)) {
            $fileContent = file_get_contents($filePath);
            $data = json_decode($fileContent, true);
        
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Error al cargar los vehículos del cliente con ID: $id. Error: " . json_last_error_msg());

            } elseif (isset($data['error'])) {
                error_log("Error al cargar los vehículos del cliente con ID: $id. Error: " . $data['error']);
            }
        } else {
            error_log("Error al cargar los vehículos del cliente con ID: $id. Archivo no encontrado: $filePath");
        }
    }
    

    private function validarContrasena($str, $min, $max) {
        /* Verifica si la contraseña contiene mayusculas, minusculas y numeros
        y si la extension de la cadena se ncuentra en el rango especificado por las variables $min y $max. */ 
        return ((preg_match('/[A-Z]/', $str) && preg_match('/[a-z]/', $str) && preg_match('/[0-9]/', $str) 
                && strlen($str) <= $max && strlen($str) >= $min));

    }
    private function validarEmail($str, $max) {
        /* Verifica si la cadena $str cumple con ciertos criterios de caracteres y contiene un dominio de correo valido
        y si la extension de la cadena es menor o igual al maximo especificado por la variable $max. */ 
        return (preg_match("/^[a-zA-Z0-9][a-zA-Z0-9._%+-]*@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/", $str) && strlen($str) <= $max);
    }

    private function validarNombreApellido($str, $max) {
        /* Verifica si la cadena $str cumple con ciertos criterios de caracteres (contiene letras, espacios, tildes, apostrofes o guiones)
        y si la extension de la cadena es menor o igual al maximo especificado por la variable $max. */
        return (preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚüÜñÑ '-]+$/", $str) && strlen($str) <= $max);
    }

}