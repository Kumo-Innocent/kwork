<?php

spl_autoload_register( function( $class ) {
    $file = KUMO_PATH . str_replace( '\\', '/', $class ) . '.php';
    if( file_exists( $file ) ) {
        return require_once $file;
    }
    return false;
} );

// Silence is golden