<?php
$m = new Memcached();
$m->addServer('127.0.0.1', 11211); //

$m->set('test_key', 'Test Integration');
echo $m->get('test_key');