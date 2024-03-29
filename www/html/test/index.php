<?php

error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client as GuzzleClient;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use \Elastic\Elasticsearch\ClientBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Request;

// If you add a new route don't forget to include its namespace
use Test\UserController;
use Test\HomeController;
use Test\RedisController;
use Test\RabbitMqController;
use Test\MySqlController;
use Test\ElasticsearchController;

/*
 * ----------------
 * | Dependencies |
 * ----------------
 */
$mysql = new PDO('mysql:dbname=db;host=mysql', 'user', 'password');
$redis = new Redis();
$redis->connect('redis');
$rabbitmq = new AMQPStreamConnection('rabbitmq', 5672, 'rabbitmq', 'rabbitmq');
$elasticsearch = ClientBuilder::create()->setHosts(["elasticsearch:9200"])->build();
$guzzle = new GuzzleClient();

$dc = [
    'mysql' => $mysql,
    'redis' => $redis,
    'rabbitmq' => $rabbitmq,
    'elasticsearch' => $elasticsearch,
    'guzzle' => $guzzle
];

/*
 * -----------
 * | Routing |
 * -----------
 */
$routes = [
    'home'          => (new Route('/', ['controller' => HomeController::class]))->setMethods([Request::METHOD_GET]),
    'hi'            => (new Route('/hi/{name}', ['controller' => UserController::class, 'method' => 'get']))->setMethods([Request::METHOD_GET]),
    'redis'         => (new Route('/redis', ['controller' => RedisController::class]))->setMethods([Request::METHOD_GET]),
    'rabbitmq'      => (new Route('/rabbitmq', ['controller' => RabbitMqController::class]))->setMethods([Request::METHOD_GET]),
    'mysql'         => (new Route('/mysql', ['controller' => MySqlController::class]))->setMethods([Request::METHOD_GET]),
    'elasticsearch' => (new Route('/elasticsearch', ['controller' => ElasticsearchController::class]))->setMethods([Request::METHOD_GET]),
];

/*
 * ------------
 * | Dispatch |
 * ------------
 */
$rc = new RouteCollection();
foreach ($routes as $key => $route) {
    $rc->add($key, $route);
}
$context = new RequestContext();
$matcher = new UrlMatcher($rc, $context);
$request = Request::createFromGlobals();
$context->fromRequest($request);

try {
    $attributes = $matcher->match($context->getPathInfo());
    $ctrlName = $matcher->match($context->getPathInfo())['controller'];
    $ctrl = new $ctrlName($dc);
    $request->attributes->add($attributes);
    if (isset($matcher->match($context->getPathInfo())['method'])) {
        $response = $ctrl->{$matcher->match($context->getPathInfo())['method']}($request);
    } else {
        $response = $ctrl($request);
    }
} catch (ResourceNotFoundException $e) {
    $response = new Response('Not found!', Response::HTTP_NOT_FOUND);
}

$response->send();
