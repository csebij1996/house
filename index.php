<?php

require '../vendor/autoload.php';

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addRoute('GET', '/', 'homeHandler');
    $r->addRoute('GET', '/api/kiadasok', 'kiadasokHandler');
    $r->addRoute('GET', '/api/harom-kiadas', 'haromKiadasHandler');
    $r->addRoute('GET', '/api/adatok', 'adatokHandler');
    $r->addRoute('POST', '/api/kiadasfelvetel', 'felvetelHandler');
    $r->addRoute('POST', '/api/torles', 'torlesHandler');
});

function homeHandler() {
    require './build/index.html';
}

function felvetelHandler() {

    header('Content-type: application/json');

    $body = json_decode(file_get_contents("php://input"), true);
    if(empty($body['name']) || empty($body['price'])) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "result" => "Minden mező kitöltése kötelező!"
        ]);
        return;
    }
    $pdo = getConnection();
    $statement = $pdo->prepare('INSERT INTO kiadasok (`name`,`price`,`createdAt`)
        VALUES (?,?,?)
    ');
    $statement->execute([
        $body['name'],
        (int)$body['price'],
        time()
    ]);
    $result = [
        "status" => true,
        "result" => "Sikeres felvétel"
    ];
    echo json_encode($result);
}

function torlesHandler() {

    header('Content-type: application/json');

    $torlesId = json_decode(file_get_contents('php://input'),true);

    $pdo = getConnection();
    $statement = $pdo->prepare('DELETE FROM kiadasok WHERE id = ?');
    $statement->execute([
        $torlesId
    ]);
    $result = [
        "status" => true,
        "result" => "Sikeres törlés!"
    ];
    echo json_encode($result);

}

function adatokHandler() {

    header('Content-type: application/json');

    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM kiadasok');
    $statement->execute([]);
    $kiadasok = $statement->fetchAll(PDO::FETCH_ASSOC);
    
    $LAKAS = 3500000;
    $rakoltve = 0;
    $osszesen = 0;

    foreach($kiadasok as $kiadas) {
        $rakoltve += $kiadas['price'];
    };
    $osszesen = $LAKAS + $rakoltve;
    $result = [
        "status" => true,
        "result" => [
            "lakas" => $LAKAS,
            "rakoltve" => $rakoltve,
            "osszesen" => $osszesen
        ]
    ];
    echo json_encode($result);
}

function haromKiadasHandler() {

    header('Content-type: application/json');

    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM kiadasok ORDER BY createdAt DESC');
    $statement->execute([]);
    $kiadasok = $statement->fetchAll(PDO::FETCH_ASSOC);
    $tomb = [];
    for($i = 0; $i < 3; $i++) {
        array_push($tomb, [
            "id" => $kiadasok[$i]['id'],
            "name" => $kiadasok[$i]['name'],
            "price" => $kiadasok[$i]['price'],
            "createdAt" => date("Y/m/d", $kiadasok[$i]['createdAt']),
        ]);    
    }
    $result = [
        "status" => true,
        "result" => $tomb
    ];
    echo json_encode($result);

}

function kiadasokHandler() {

    header('Content-type: application/json');
    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM kiadasok ORDER BY createdAt DESC');
    $statement->execute([]);
    $kiadasok = $statement->fetchAll(PDO::FETCH_ASSOC);
    
    $tomb = [];
    foreach($kiadasok as $kiadas) {
        array_push($tomb, [
            "id" => $kiadas['id'],
            "name" => $kiadas['name'],
            "price" => $kiadas['price'],
            "createdAt" => date("Y/m/d", $kiadas['createdAt'])
        ]);
    }

    $result = [
        "status" => true,
        "result" => $tomb
    ];
    echo json_encode($result);
}

function notFoundHandler() {
    require './build/index.html';
}

function getConnection() {
    return new PDO('mysql:host=' . $_SERVER['DB_HOST'] . ';dbname=' . $_SERVER['DB_NAME'],
        $_SERVER['DB_USER'],
        $_SERVER['DB_PASSWORD']
    );
}

if(isset($_SERVER["HTTP_ORIGIN"]))
{
    // You can decide if the origin in $_SERVER['HTTP_ORIGIN'] is something you want to allow, or as we do here, just allow all
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
}
else
{
    //No HTTP_ORIGIN set, so we allow any. You can disallow if needed here
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 600");    // cache for 10 minutes

if($_SERVER["REQUEST_METHOD"] == "OPTIONS")
{
    if (isset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_METHOD"]))
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT"); //Make sure you remove those you do not want to support

    if (isset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_HEADERS"]))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    //Just exit with 200 OK with the above headers for OPTIONS method
    exit(0);
}

// Fetch method and URI from somewhere
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip query string (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        notFoundHandler();
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        notFoundHandler();
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        $handler($vars);
        break;
}