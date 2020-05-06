<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use Elastica\Client as ElasticaClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Request;

// If you add a new route don't forget to include it's namespace
use TestApp\UserController;
use TestApp\HomeController;

/*
 * ----------------
 * | Dependencies |
 * ----------------
 */
$mysql = new PDO('mysql:dbname=db;host=mysql', 'user', 'password');
$redis = new Redis();
$redis->connect('redis');
$rabbitmq = new AMQPStreamConnection('rabbitmq', 5672, 'rabbitmq', 'rabbitmq');
$elasticsearch = (new ElasticaClient(['host' => 'elasticsearch', 'port' => 9200]));
$elasticsearch->getVersion();

$dc = [
    'mysql' => $mysql,
    'redis' => $redis,
    'rabbitmq' => $rabbitmq,
    'elasticsearch' => $elasticsearch
];

/*
 * -----------
 * | Routing |
 * -----------
 */
$routes = [
    'home'      => (new Route('/',           ['controller' => HomeController::class]))->setMethods([Request::METHOD_GET]),
    'get_user'  => (new Route('/users/',     ['controller' => UserController::class]))->setMethods([Request::METHOD_GET]),
    'post_user' => (new Route('/users/{id}', ['controller' => UserController::class, 'method' => 'create']))->setMethods([Request::METHOD_POST]),
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
