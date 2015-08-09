<?php

require 'vendor/autoload.php';

$hash = new BallAndChain\Hash(__DIR__ . '/data.dat');

$result = $hash->create("foobar");

var_dump($result);

var_dump($hash->verify("foobar", $result));


var_dump($hash->verify("foobaz", $result));