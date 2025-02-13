<?php

namespace src\classes;

use JetBrains\PhpStorm\NoReturn;

abstract class API {

    /**
     * Send response to client
     *
     * @param mixed $data
     * @param bool $code
     * @param bool $pretty_print
     * @param array|null $custom
     * @return void
     */
    #[NoReturn] public static function send( mixed $data, bool $code=true, bool $pretty_print=false, ?array $custom=null ) : void {
        if( $code ) {
            http_response_code( 200 );
        } else {
            http_response_code( 400 );
        }
        header( 'Content-Type: application/json' );
        die( json_encode( $custom ?? array(
            'content' => $data,
            'code' => $code
        ), $pretty_print ? JSON_PRETTY_PRINT : 0 ) );
    }

}