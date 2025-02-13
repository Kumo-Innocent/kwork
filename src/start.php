<?php

require_once 'autoload.php';
require_once 'functions.php';

use src\classes\Template;
use src\classes\Hooks;

global $template_engine, $hooks_engine, $database, $jwt_engine, $current_route, $cache;
$template_engine = new Template( 'index' );
$template_engine
    ->set_directory( KUMO_PATH . 'templates' )
    ->scan_templates();
$hooks_engine = new Hooks();

$database = new src\classes\Database( KUMO_HOST, KUMO_USER, KUMO_PASS, KUMO_DB );
$router = new src\classes\Router();
$route = get_route();
$domains = array();
$jwt_engine = new src\classes\JWT( private_key: KUMO_KEY );
$cache = new src\classes\Cache( KUMO_PATH . '/cache', 90 );

$current_path = KUMO_PATH . 'controllers';
scan_controllers( $current_path, $domains );

if( ! in_array( $route[ 'domain' ], array_keys( $domains ) ) ) {
    //@TODO raise error
    echo 'raise error';
} else {
    $starting_point = $domains[ $route[ 'domain' ] ];
    register_controllers( $router, $starting_point, $route );
}

$current_route = array();
$router->start( $route, $current_route );