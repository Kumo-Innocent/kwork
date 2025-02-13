<?php

namespace src\classes;

class JWT {

    private string $alg = 'HS256';
    private string $private_key = '';

    public function __construct( string $alg='HS256', string $private_key='' ) {
        $this->alg = $alg;
        $this->private_key = $private_key;
    }

    /**
     * Encode URL to base64
     *
     * @param string $url
     * @return string
     */
    public static function base64_url_encode( string $url ) : string {
        return rtrim( strtr( base64_encode( $url ), '+/', '-_' ), '=' );
    }

    /**
     * Decode base64 encoded URL
     *
     * @param string $url
     * @return string
     */
    public static function base64_url_decode( string $url ) : string {
        return base64_decode( str_pad( $url, strlen( $url ) % 4, '=', STR_PAD_LEFT ), true );
    }

    /**
     * Generate JWT token from input values
     *
     * @param array $data,
     * @param int $expires Default = 3600
     * @return string
     */
    public function generate_token( array $data, int $expires=3600 ) : string {
        $header = array(
            'typ' => 'JWT',
            'alg' => $this->alg
        );
        $payload = array(
            'iat' => time(),
            'exp' => time() + $expires,
            'data' => $data
        );
        $header_encoded = self::base64_url_encode( json_encode( $header ) );
        $payload_encoded = self::base64_url_encode( json_encode( $payload ) );
        $signature = self::base64_url_encode( hash_hmac( 'sha256', "$header_encoded.$payload_encoded", $this->private_key, true ) );
        return "$header_encoded.$payload_encoded.$signature";
    }

    /**
     * Check if JWT token is valid
     *
     * @param string $token
     * @return bool|array
     */
    public function verify_token( string $token ) : bool|array {
        $parts = explode( '.', $token );
        if( count( $parts ) !== 3 ) {
            // @TODO raise error invalid token format
            return false;
        }
        list( $header_encoded, $payload_encoded, $signature ) = $parts;
        $new_signature = self::base64_url_encode( hash_hmac( 'sha256', "$header_encoded.$payload_encoded", $this->private_key, true ) );
        if( ! hash_equals( $signature, $new_signature ) ) {
            // @TODO raise error invalid token
            return false;
        }
        $payload = json_decode( self::base64_url_decode( $payload_encoded ), true );
        if(
            isset( $payload[ 'exp' ] ) &&
            $payload[ 'exp' ] < time()
        ) {
            // @TODO raise error token expired
            return false;
        }
        return $payload;
    }

}