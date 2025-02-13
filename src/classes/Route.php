<?php

namespace src\classes;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class Route extends API {

    public array $path = array();
    public array $method = array();
    public array $security = array();

    private string $key;
    private string $salt;
    private string $token_method = 'AES-256-CBC';
    public string $bearer_token = '';
    public bool $token_required = false;

    private array $security_schema = array(
        "need_login" => "boolean",
        "redirect_on_nologin" => "boolean",
        "redirect_nologin_path" => "string",
        "need_nologin" => "boolean",
        "redirect_on_login" => "boolean",
        "redirect_login_path" => "string|array",
        "redirect_noright_path" => "string",
        "strict_check" => "array",
        "roles" => "string|array",
        "redirect_noroles_path" => "string",
        "need_client" => "boolean",
        "redirect_on_noclient" => "boolean",
        "redirect_noclient_path" => "string"
    );

    /**
     * @param string|array $path Single or multiple path for the endpoint
     * @param string|array $method Single or multiple method that can be used for the endpoint
     * @param array $security Security applied to the endpoint
     */
    public function __construct(
        string|array $path,
        string|array $method=array('GET'),
        array $security=array(
            "need_login" => true,
            "redirect_on_nologin" => true,
            "redirect_nologin_path" => "/",
            "redirect_noright_path" => "/",
            "redirect_noroles_path" => "/",
            "redirect_noclient_path" => "/",
            "strict_check" => []
        ),
        ?string $bearer_token=null,
        ?string $token_method=null,
        ?bool $token_required=null
    ) {
        if( is_string( $path ) ) $this->path[] = $path;
        else $this->path = $path;
        if( is_string( $method ) ) $this->method[] = $method;
        else $this->method = $method;
        if( $token_method !== null ) $this->token_method = $token_method;
        if( $token_required !== null ) $this->token_required = $token_required;
        if( ! is_array( $security ) ) {
            // @TODO Raise error
            echo 'il faut que ce soit une erreur';
        } else $this->security = $this->validate_schema( $security );
        $this->key = $this->get_default_key();
        $this->salt = $this->get_default_salt();
        $this->bearer_token = $this->get_bearer();
        if( $bearer_token !== null ) $this->bearer_token = $bearer_token;
    }

    /**
     * Validate the input security schema
     *
     * @param array $security
     * @return array
     */
    private function validate_schema( array $security ) : array {
        foreach( $security as $name => $content ) {
            if(
                ! in_array( $name, array_keys( $this->security_schema ) ) ||
                ! in_array( gettype( $content ), ( $list_valid = explode( '|', $this->security_schema[ $name ] ) ) )
            ) {
                unset( $security[ $name ] );
            }
        }
        return $security;
    }

    /**
     * Get authorization headers
     *
     * @return null|string
     */
    private function get_headers() : ?string {
        $headers = null;
        if( isset( $_SERVER[ 'Authorization' ] ) ) {
            $headers = trim( $_SERVER[ 'Authorization' ] );
        } else if( isset( $_SERVER[ 'HTTP_AUTHORIZATION' ] ) ) {
            $headers = trim( $_SERVER[ 'HTTP_AUTHORIZATION' ] );
        } else if( function_exists( 'apache_request_headers' ) ) {
            $request_headers = apache_request_headers();
            $request_headers = array_combine( array_map( 'ucwords', array_keys( $request_headers ) ), array_values( $request_headers ) );
            if( isset( $request_headers[ 'Authorization' ] ) ) {
                $headers = trim( $request_headers[ 'Authorization' ] );
            }
        }
        return $headers;
    }

    /**
     * Get Bearer token from headers
     *
     * @return string
     */
    private function get_bearer() : string {
        $headers = $this->get_headers();
        if( ! empty( $headers ) ) {
            if( preg_match( '/Bearer\s(\S+)/', $headers, $matches ) ) {
                return $matches[ 1 ];
            }
        }
        return '';
    }

    /**
     * Encrypt data
     *
     * @param array|string $value
     * @return string
     */
    public function encrypt( array|string $value ) : string {
        if( is_array( $value ) ) $value = serialize( $value );
        if( ! extension_loaded( 'openssl' ) ) return $value;
        $ivlen = openssl_cipher_iv_length( $this->token_method );
        $iv = openssl_random_pseudo_bytes( $ivlen );
        $raw_value = openssl_encrypt( $value . $this->salt, $this->token_method, $this->key, 0, $iv );
        if( ! $raw_value ) return $value;
        $this->key = $this->old_key ?? $this->key;
        $this->salt = $this->old_salt ?? $this->salt;
        return base64_encode( $iv . $raw_value );
    }

    /**
     * Decrypt data
     *
     * @param string $value
     * @return string
     */
    public function decrypt( string $value ) : string {
        if( ! extension_loaded( 'openssl' ) ) return $value;
        $raw_value = base64_decode( $value, true );
        $ivlen = openssl_cipher_iv_length( $this->token_method );
        $iv = substr( $raw_value, 0, $ivlen );
        $raw_value = substr( $raw_value, $ivlen );
        $value = openssl_decrypt( $raw_value, $this->token_method, $this->key, 0, $iv );
        if( ! $value || ! str_ends_with( $value, $this->salt ) ) return $raw_value;
        $return = substr( $value, 0, - strlen( $this->salt ) );
        $this->key = $this->old_key ?? $this->key;
        $this->salt = $this->old_salt ?? $this->salt;
        return $return;
    }

    /**
     * Decrypt data only if crypted
     *
     * @param mixed $value
     * @return mixed
     */
    public function decrypt_if_crypted( mixed $value ) : mixed {
        $content = @unserialize( $value );
        if( $content !== false ) {
            return $content;
        } else {
            $content = $this->decrypt( $value );
            if( $content !== $value ) {
                return $this->decrypt_if_crypted( $content );
            }
        }
        return $value;
    }

    /**
     * Get default key if KUMO_KEY not defined
     *
     * @return string
     */
    public function get_default_key() : string {
        if( defined( 'KUMO_KEY' ) && KUMO_KEY !== '' ) return KUMO_KEY;
        return 'hlaqLiEPYkK5Aye2RNeCucBD3YXPWYGIgJzWgXkOO7UqxnurKr';
    }

    /**
     * Get default salt if KUMO_SALT not defined
     *
     * @return string
     */
    public function get_default_salt() : string {
        if( defined( 'KUMO_SALT' ) && KUMO_SALT !== '' ) return KUMO_SALT;
        return 's1ms4XTDIY25RRew90VmyYlM5UHzEzrr8Nla6DzviRbytDTQ6v';
    }

    /**
     * Send image to Krop&Size
     *
     * @param string $site_uuid
     * @param string $client_uuid
     * @param string $url
     * @param array $ratios
     * @param null|bool $is_api
     * @return array
     */
    public function use_krop(
        string $site_uuid,
        string $client_uuid,
        string $url,
        array $ratios=array(
            array(
                'width' => 2400,
                'height' => 1350
            )
        ),
        ?bool $is_api=false
    ) : array {
        if(
            ! function_exists( 'curl_init' ) ||
            ! function_exists( 'curl_setopt_array' ) ||
            ! function_exists( 'curl_setopt' ) ||
            ! function_exists( 'curl_exec' ) ||
            ! function_exists( 'curl_close' ) ||
            ! function_exists( 'image_type_to_extension' ) ||
            ! function_exists( 'exif_imagetype' )
        ) {
            // @TODO raise error
            if( $is_api ) self::send( "Missing required functions. (63d354499c8dd78eb52f7d27e1e80159)", false );
            die( "Can't initialize cURL." );
        }
        global $hooks_engine;
        $config = array();
        list( $config, $validity ) = $hooks_engine->do_hook(
            'krop-use-client-validity',
            default: array( array(), true ),
            args: array( $client_uuid, $config )
        );
        if(
            ! $validity ||
            (
                isset( $config[ 'content' ][ 'credit' ] ) &&
                (
                    $config[ 'content' ][ 'credit' ] === 0 ||
                    $config[ 'content' ][ 'credit' ] - 1 < 0
                )
            )
        ) {
            // @TODO raise error
            if( $is_api ) self::send( "Missing credit. (43886ad655bb364fd72285fc7adf7fc8)", false );
            die( "Missing credit." );
        }
        $curl = curl_init();
        curl_setopt_array( $curl, array(
            CURLOPT_URL => KUMO_KROP_URL . '/api.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode( array(
                'action' => 'krop',
                'url' => $url,
                'ratios' => ! empty( $ratios[ 'width' ] ) ? array( $ratios ) : $ratios
            ) ),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            )
        ) );
        $response = curl_exec( $curl );
        curl_close( $curl );
        $decoded_response = json_decode( $response, true );
        if(
            ! isset( $decoded_response[ 'code' ] ) ||
            ! isset( $decoded_response[ 'content' ] ) ||
            boolval( $decoded_response[ 'code' ] )
        ) {
            if(
                ! empty( $decoded_response[ 'code' ] )
            ) {
                // @TODO raise error
                if( $is_api ) self::send( match( $decoded_response[ 'code' ] ) {
                    -3 => "Cannot reach image. (14d44a1ac02408c9531129f168211f54)",
                    -5 => "Image too large. (b794ced3d2f7a1cb157b815917b8398f)"
                }, false );
            }
            return match( $decoded_response[ 'code' ] ?? 0 ) {
                -3 => array(
                    "message" => "Impossible de téléverser l'image.",
                ),
                -5 => array(
                    "message" => "Image trop lourde pour l'optimisation automatique. Max : 20mb",
                ),
                default => array()
            };
        }
        $hooks_engine->add_hook( 'krop-use-after-fetching', array(
            'config' => $config,
            'site_uuid' => $site_uuid,
            'decoded_response' => $decoded_response
        ) );
        $result = array();
        foreach( $decoded_response[ 'content' ] as $loop_image ) {
            $result[] = array(
                'url' => KUMO_KROP_URL . '/output/' . $loop_image[ 'name' ] . '.' . $loop_image[ 'type' ]
            );
        }
        return $result;
    }

}