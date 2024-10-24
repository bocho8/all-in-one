<?php

namespace Sigae\Controllers;
use Sigae\Models\Cliente;
use Google_Client;
use Google_Service_Oauth2;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class ControladorCliente extends AbstractController {
    private $cliente;
    private const INACTIVIDAD_MAX_SESION = 600; // límite de 10 minutos de inactividad

    public function __construct(){
        $this->cliente=new Cliente();
    }
    function showLandingPage(): Response{
        return $this->render('landingPage.html.twig');
    }
    function login(): Response{
        return $this->render('client/account/login.html.twig');
    }
    function loginAioEmployee(): Response{
        return $this->render('employee/loginEmpleado.html.twig');
    }
    function doLogin(): Response|RedirectResponse{
        $response=['success' => false, 'errors' => [], 'debug' => []];

        // Debug: Log all received data
        $response['debug']['received_data']=$_POST;

        // Validacion de campos vacios
        if (isset($_POST["email"], $_POST["contrasena"]) && 
            !empty($_POST["email"]) && !empty($_POST["contrasena"])) {

            $email = $_POST["email"];
            $contrasena = $_POST["contrasena"];

            // Debug: Datos procesados
            $response['debug']['processed_data'] = [
                'email' => $email,
                'contrasena' => 'REDACTED',
            ];

            // Validar email
            if (!$this->validarEmail($email, 63)) {
                $response['errors'][] = "Por favor, ingrese un correo electrónico válido.";
            } elseif (!$this->validarContrasena($contrasena, 6, 60)) {
                $response['errors'][] = "Por favor, ingrese una contraseña válida.";
            } else {
                if ($this->cliente->iniciarCliente($email, $contrasena)) {
                    // Debug: Login exitoso
                    $response['success'] = true;
                    
                    // Parámetros de cookie de sesión
                    session_set_cookie_params([
                        'lifetime' => 0,          // La cookie se elimina al salir del navegador
                        'path' => '/',            // Accesible en toda la aplicación
                        'secure' => true,         // Solo se envía por HTTPS
                        'httponly' => true,       // Protege que no sea accesible desde JavaScript
                        'samesite' => 'Lax'       // Se envía en la mayoría de las solicitudes siempre que sean "seguras", evitando ataques CSRF
                    ]);

                    // Iniciar sesión del cliente
                    session_start();

                    // Regenerar el ID de sesión para prevenir fijación de sesión
                    session_regenerate_id(true);

                    $_SESSION['ultima_solicitud']= time();
                    $_SESSION['id']=$this->cliente->getId();
                    $_SESSION['ci']=$this->cliente->getCi();
                    $_SESSION['email']=$this->cliente->getEmail();
                    $_SESSION['nombre']=$this->cliente->getNombre();
                    $_SESSION['apellido']=$this->cliente->getApellido();
                    $_SESSION['telefono']=$this->cliente->getTelefono();

                    // Redirigir a la home page
                    return $this->redirectToRoute('home');
                } else {
                    $response['errors'][] = "Correo o contraseña incorrectos.";
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
        return $this->render('client/account/login.html.twig', [
            'response' => $response  // Aquí pasa la respuesta a la vista
        ]);
    }
    function doLoginAioEmployee(){
    }
    function doLoginOAuth(): Response|RedirectResponse{
        // Configurar cliente Google
        $client= new Google_Client();
        $client->setAuthConfig('/var/www/html/private/credencialesOAuth.json');
        $client->setRedirectUri('"https://16fd-167-56-5-207.ngrok-free.app/doLoginOAuth"');
        $client->addScope('email');
        $client->addScope('profile');

        if (!isset($_GET['code'])) {
            $auth_url = $client->createAuthUrl();
            return $this->redirectToRoute(filter_var($auth_url, FILTER_SANITIZE_URL));
        } else {
            // Procesa la respuesta de Google
            $client->authenticate($_GET['code']);
            $_SESSION['access_token'] = $client->getAccessToken();

            // Obtén la información del perfil del usuario
            $oauth2 = new Google_Service_Oauth2($client);
            $userInfo = $oauth2->userinfo->get();

            $fotoPerfil = $userInfo->profile;
            $email = $userInfo->email;

            if ($this->cliente->iniciarCliente($email, null)) {
                $response['success'] = true;

                // Parámetros de cookie de sesión
                session_set_cookie_params([
                    'lifetime' => 0,          // La cookie se elimina al salir del navegador
                    'path' => '/',            // Accesible en toda la aplicación
                    'secure' => true,         // Solo se envía por HTTPS
                    'httponly' => true,       // Protege que no sea accesible desde JavaScript
                    'samesite' => 'Lax'       // Se envía en la mayoría de las solicitudes siempre que sean "seguras", evitando ataques CSRF
                ]);
                
                // Iniciar sesión del cliente
                session_start();

                // Regenerar el ID de sesión para prevenir fijación de sesión
                session_regenerate_id(true);

                $_SESSION['ultima_solicitud']= time();
                $_SESSION['id']=$this->cliente->getId();
                $_SESSION['ci']=$this->cliente->getCi();
                $_SESSION['email']=$this->cliente->getEmail();
                $_SESSION['nombre']=$this->cliente->getNombre();
                $_SESSION['apellido']=$this->cliente->getApellido();
                $_SESSION['telefono']=$this->cliente->getTelefono();
                $_SESSION['fotoPerfil']=$fotoPerfil;

                // Redirigir a la home page
                return $this->render('client/homeCliente.html.twig');
            } else {
                $response['errors'][] = "Error al iniciar sesión.";
            }
        }
        return $this->render('client/account/login.html.twig', [
            'response' => $response  // Pasa la respuesta a la vista
        ]);
    }
    function doSignUpOAuth(){
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

            // Debug: Datos procesados
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
                error_log('Email ya existe: ' . $email);
                $response['errors'][]= "Ya existe un usuario con el correo ingresado.";

            } elseif(!$this->cliente->guardarCliente(null, $email, $contrasena, $nombre, $apellido, null)){
                $response['errors'][] = "Error al registrarse.";

            } else {
                $response['success'] = true;
                // Redirigir al login
                return $this->redirectToRoute('login');
            }
            
        } else {
            $response['errors'][] = "Debe llenar todos los campos.";
            // Debug: Log which fields are missing
            $response['debug']['missing_fields'] = array_diff(
                ['email', 'nombre', 'apellido', 'contrasena', 'repContrasena'],
                array_keys($_POST)
            );
        }
        return $this->render('client/account/signUp.html.twig', [
            'response' => $response
        ]);
    }
    function signup(): Response{
        return $this->render('client/account/signUp.html.twig');
    }
    function doSignup(): Response|RedirectResponse{
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

            // Debug: Datos procesados
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
                error_log('Email ya existe: ' . $email);
                $response['errors'][]= "Ya existe un usuario con el correo ingresado.";

            } elseif(!$this->cliente->guardarCliente(null, $email, $contrasena, $nombre, $apellido, null)){
                $response['errors'][] = "Error al registrarse.";

            } else {
                $response['success'] = true;
                // Redirigir al login
                return $this->redirectToRoute('login');
            }
            
        } else {
            $response['errors'][] = "Debe llenar todos los campos.";
            // Debug: Log which fields are missing
            $response['debug']['missing_fields'] = array_diff(
                ['email', 'nombre', 'apellido', 'contrasena', 'repContrasena'],
                array_keys($_POST)
            );
        }
        return $this->render('client/account/signUp.html.twig', [
            'response' => $response
        ]);
    }
    function logout(): RedirectResponse{
        if (session_status() === PHP_SESSION_NONE){
            session_start();
        }
        // Limpia variables de sesión
        session_unset();
        $_SESSION=[];

        // Destruye las variables en el servidor
        session_destroy();

        /// Borra la cookie de sesión
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();

            // Fomatea el header de la cookie para eliminarla
            $cookieHeader = sprintf(
                '%s=; expires=%s; Max-Age=0; path=%s; domain=%s; secure; httponly; samesite=%s',
                session_name(),
                gmdate('D, d M Y H:i:s T', time() - 42000),// Formatea la fecha para que sea validada por el navegador
                $params["path"], $params["domain"],
                $params["samesite"] ?? 'Lax'// Usa 'Lax' como se especificó 'samesite'
            );
            // Envía el header con los parámetros formateados, sin reemplazar otros headers
            header('Set-Cookie: ' . $cookieHeader, false); 
        }

        return $this->redirectToRoute('showLandingPage');
    }
    function forgotPassword(): Response{
        return $this->render('client/account/forgotPassword.html.twig');
    }
    function services(): Response{
        $redireccion = $this->verificarSesion();
        if ($redireccion) {
            return $redireccion;
        }
        return $this->render('client/serviciosMecanicos.html.twig');
    }
    function bookService(): Response{
        $redireccion = $this->verificarSesion();
        if ($redireccion) {
            return $redireccion;
        }

        $misVehiculos = $this->cliente->cargarMisVehiculos($_SESSION['id']);

        return $this->render('client/reservarServicio.html.twig', [
           'misVehiculos' => $misVehiculos
        ]);
    }
    
    function parking(): Response{
        $redireccion = $this->verificarSesion();
        if ($redireccion) {
            return $redireccion;
        }
        return $this->render('client/aioParking.html.twig');
    }

    function parkingSimple(): Response{
        $redireccion = $this->verificarSesion();
        if ($redireccion) {
            return $redireccion;
        }

        $misVehiculos = $this->cliente->cargarMisVehiculos($_SESSION['id']);
        
        return $this->render('client/reservarParkingSimple.html.twig', [
            'misVehiculos' => $misVehiculos
         ]);
    }

    function parkingLongTerm(): Response{
        $redireccion = $this->verificarSesion();
        if ($redireccion) {
            return $redireccion;
        }

        $misVehiculos = $this->cliente->cargarMisVehiculos($_SESSION['id']);
        
        return $this->render('client/reservarParkingLargoPlazo.html.twig', [
            'misVehiculos' => $misVehiculos
        ]);
    }

    function products(): Response{
        $redireccion = $this->verificarSesion();
        if ($redireccion) {
            return $redireccion;
        }
        return $this->render('client/catalogo.html.twig');
    }

    function myAccount(): Response{
        $redireccion = $this->verificarSesion();
        if ($redireccion) {
            return $redireccion;
        }
        $cliente = [
            'id' => $_SESSION['id'],
            'email' => $_SESSION['email'],
            'nombre' => $_SESSION['nombre'],
            'apellido' => isset($_SESSION['apellido']) ? $_SESSION['apellido'] : null,
            'telefono' => isset($_SESSION['telefono']) ? $_SESSION['telefono'] : null,
            'fotoPerfil' => isset($_SESSION['fotoPerfil']) ? $_SESSION['fotoPerfil'] : null
        ];
        $misVehiculos = $this->cliente->cargarMisVehiculos($_SESSION['id']);
        return $this->render('client/miCuenta.html.twig', [
            'cliente' => $cliente,
            'misVehiculos' => $misVehiculos
        ]);
    }
    function faq(): Response{
        $redireccion = $this->verificarSesion();
        if ($redireccion) {
            return $redireccion;
        }
        return $this->render('client/FAQ.html.twig');
    }
    function home(): Response{
        $redireccion = $this->verificarSesion();
        if ($redireccion) {
            return $redireccion;
        }
        return $this->render('client/homeCliente.html.twig');
    }

    function verificarSesion(): ?RedirectResponse {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    
        // Verificar si la variable de tiempo de inactividad está definida
        if (!isset($_SESSION["ultima_solicitud"])) {
            return $this->logout(); // Si no hay un tiempo definido, se realiza el logout
        }
    
        // Obtiene el tiempo desde la última solicitud
        $inactividad = time() - $_SESSION["ultima_solicitud"];
    
        // Verificación de inactividad de la sesión
        if ($inactividad > $this::INACTIVIDAD_MAX_SESION) {
            return $this->logout(); // Si ha excedido el tiempo de inactividad, cierra la sesión
        }
    
        // Actualiza el tiempo de la última solicitud y regenera la ID de sesión por seguridad
        $_SESSION["ultima_solicitud"] = time();
        session_regenerate_id(true);
    
        // Si la sesión es válida, no se realiza ninguna redirección
        return null;
    }

    function getClientSession(): ?JsonResponse {
        session_start();
        return new JsonResponse([
            'ultima_solicitud' => $_SESSION['ultima_solicitud'],
            'id' => $_SESSION['id'],
            'ci' => $_SESSION['ci'] ?? null,
            'email' => $_SESSION['email'],
            'nombre' => $_SESSION['nombre'] ?? null,
            'apellido' => $_SESSION['apellido'] ?? null,
            'telefono' => $_SESSION['telefono'] ?? null,
            'fotoPerfil' => $_SESSION['fotoPerfil'] ?? null]);
    }

    function getClientProfilePhoto(): ?JsonResponse {
        session_start();
        $fotoPerfil = $_SESSION['fotoPerfil'] ?? null;
        return new JsonResponse(['fotoPerfil' => $fotoPerfil]);
    }

    function getClientEmail(): JsonResponse {
        session_start();
        $email = $_SESSION['email'];
        return new JsonResponse(['email' => $email]);
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

    private function validarCi($ci){
        $digitosDeCedula = str_split($ci);  // Convierte la cédula en un array de caracteres

        $numerosParaMultiplicar = array(2, 9, 8, 7, 6, 3, 4);

        $acum = 0;
        for ($i = 0; $i < count($digitosDeCedula) - 1; $i++) {
            $acum += $digitosDeCedula[$i] * $numerosParaMultiplicar[$i];
        }
        $aux = $acum % 10;
        $verif = 10 - $aux;

        return $verif == $digitosDeCedula[count($digitosDeCedula) - 1];
    }

}