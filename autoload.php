<?php

spl_autoload_register(function($clsName) {
    $ns = '';
    $p = strrpos($clsName, '\\');
    if (false !== $p) {
        $ns = substr($clsName, 0, $p);
        $clsName = substr($clsName, $p + 1);
    }    
    if (substr($ns, 0, 6) !== 'pdquery') return;
    if ($clsName == 'Collection' || $clsName == 'CollectionTools') {
        require_once(__DIR__ . '/collection.php');
    } elseif ($ns == 'pdquery\EntityModel') {
        require_once(__DIR__ . '/entityModel.php');
    } elseif ($ns == 'pdquery\Sql\Dialects') {
        require_once(__DIR__ . '/sql/dialects.php');
    } elseif ($ns == 'pdquery\Sql') {
        require_once(__DIR__ . '/sql/base.php');
        require_once(__DIR__ . '/sql/queryBuilders.php');
    } else {
        require_once(__DIR__ . '/pdquery.php');
    }
});
