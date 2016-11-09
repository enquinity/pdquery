<?php
require_once __DIR__ . '/autoloader.php';

spl_autoload_register([\pdquery\Autoloader::class, 'autoloadClass']);