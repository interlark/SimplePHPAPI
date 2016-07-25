<?php
use Illuminate\Database\Capsule\Manager as Capsule;

require_once '../vendor/autoload.php';

ini_set('display_errors', /*true*/false);

$capsule = new Capsule();
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'db_xsolla',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8',
    'collation' => 'utf8_general_ci',
    'prefix' => ''
]);

$capsule->setAsGlobal();

$capsule->bootEloquent();

?>