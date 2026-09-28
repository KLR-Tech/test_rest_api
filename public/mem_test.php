<?php
$m = new Memcached();
$m->addServer('127.0.0.1', 11211); //

$m->set('test_key', 'Работи отлично с PHP 8.4!');
echo $m->get('test_key');