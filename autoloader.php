<?php

namespace pdquery;

class Autoloader {
    public static function autoloadClass($clsName) {
        if (0 !== strncmp($clsName, 'pdquery\\', 8)) return;
        $p = strrpos($clsName, '\\');
        $ns = '';
        if (false !== $p) {
            $ns = substr($clsName, 0, $p);
            $clsName = substr($clsName, $p + 1);
        }
        if ($clsName == 'Collection' || $clsName == 'CollectionTools') {
            require_once(__DIR__ . '/collection.php');
        } elseif ($clsName == 'CollectionDataSource') {
            require_once(__DIR__ . '/collectionDataSource.php');
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
    }
}