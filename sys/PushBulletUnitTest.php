<?php

$base = dirname(dirname(__FILE__));

include ($base . "/conf.php");
include ($base . '/library/PushBullet/PushBullet.class.php');

$dbConnection = new PDO('mysql:host=' . $CONF_DB['host'] . ';dbname=' . $CONF_DB['db_name'], $CONF_DB['username'], $CONF_DB['password'], array(
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
));
$dbConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function get_conf_param($in) {
    global $dbConnection;

    $stmt = $dbConnection->prepare('SELECT value FROM perf where param=:in');
    $stmt->execute(array(
        ':in' => $in
    ));
    $fio = $stmt->fetch(PDO::FETCH_ASSOC);

    return $fio['value'];
}

$p = new PushBullet(get_conf_param('pb_api'));

//email, title, msg
$response = $p->pushNote('vermyter@gmail.com', 'PushBullet-Unit-test', 'unit testing');

var_dump($response);
