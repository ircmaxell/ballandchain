<?php

require 'vendor/autoload.php';

$hash = new BallAndChain\Hash(__DIR__ . '/data.dat');

$result = $hash->create("foobar", 3, 5, 4);

var_dump($result);

var_dump($hash->verify("foobar", $result));


var_dump($hash->verify("foobaz", $result));
