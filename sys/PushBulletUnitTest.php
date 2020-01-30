<?php

$base = dirname(dirname(__FILE__));

include ($base . "/conf.php");
include ($base . '/library/PushBullet/PushBullet.class.php');

$p = new PushBullet(get_conf_param('pb_api'));

//email, title, msg
$response = $p->pushNote('newbie.jedicoder@gmail.com', 'PushBullet-Unit-test', 'unit testing');

var_dump($response);