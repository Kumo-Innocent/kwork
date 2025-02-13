<?php

use objects\Clients;
use objects\Sites;
use objects\Users;
use Random\RandomException;

/**
 * Get current request informations
 *
 * @return array
 */
function get_route() : array {
    $method = $_SERVER[ 'REQUEST_METHOD' ];
    $all_query = parse_url( preg_replace('/([^:])(\/{2,})/', '$1/', $_SERVER[ 'REQUEST_URI' ] ) );
    $uri = '/' . implode( '/', array_diff( preg_split( '@/@', $all_query[ 'path' ], -1, PREG_SPLIT_NO_EMPTY ), preg_split( '@/@', '/', -1, PREG_SPLIT_NO_EMPTY ) ) );
    $uri = substr_replace(
        $uri,
        '',
        0,
        strlen( KUMO_ALT )
    );
    if( @$uri[ 0 ] !== '/' ) $uri = "/$uri";
    $domain = "homepage";
    if( preg_match( '|^/(?P<domain>\w+){1}.*$|i', $uri, $match ) ) {
        $domain = $match[ 'domain' ];
    }
    if( ( $temp = strpos( $uri, "/$domain" ) ) !== false && $temp === 0 ) {
        $uri = substr_replace( $uri, '', 0, strlen( "/$domain" ) );
    }
    if(
        $uri === "/$domain" ||
        empty( $uri )
    ) $uri = '/';
    return array(
        'uri' => $uri,
        'domain' => $domain,
        'method' => $method
    );
}

/**
 * Pretty print value
 *
 * @param mixed $value
 * @param bool $dump If true, var_dump is used
 * @return void
 */
function log_it( mixed $value, bool $dump=false ) : void {
    echo '<pre>';
    if( $dump ) {
        var_dump( $value );
    } else {
        print_r( $value );
    }
    echo '</pre>';
}

/**
 * Load template from path using the Template Class
 *
 * @param string $path
 * @param bool $once
 * @return void
 */
function load_template( string $path, bool $once=true ) : void {
    global $template_engine;
    $template_engine->load_template( $path, $once );
}

/**
 * Add hook to the HookEngine
 *
 * @param string $domain
 * @param mixed $callback
 * @param mixed $args
 * @return void
 */
function add_hook( string $domain, mixed $callback, mixed $args=null ) : void {
    global $hooks_engine;
    $hooks_engine->add_hook( $domain, $callback, $args );
}

/**
 * Run all hooks of the specified domain
 *
 * @param string $domain
 * @return mixed
 */
function do_hook( string $domain ) : mixed {
    global $hooks_engine;
    return $hooks_engine->do_hook( $domain );
}

/**
 * Remove hook using HooksEngine
 *
 * @param string $domain
 * @return void
 */
function remove_hooks( string $domain ) : void {
    global $hooks_engine;
    $hooks_engine->remove_hook( $domain );
}

/**
 * Get URL
 *
 * @param string|null $path
 * @param bool $return_part
 * @param bool $without_get
 * @return string
 */
function get_url( ?string $path, bool $return_part=false, bool $without_get=false ) : string {
    if( $path[ 0 ] == '+' ) $path = isset( $_SERVER[ 'REQUEST_URI' ] ) ? $_SERVER[ 'REQUEST_URI' ] . substr( $path, 1 ) : substr( $path, 1 );
    $temp = explode( KUMO_ALT, $path, 2 );
    $url = ( ( @$_SERVER[ 'HTTPS' ] ? 'https://' : 'http://' ) . ( $_SERVER[ 'HTTP_HOST' ] . KUMO_ALT ?? '' ) );
    $temp_path = end( $temp );
    if( $temp_path !== null ) {
        $url = preg_replace( '/([^:])(\/{2,})/', '$1/', $url . "/$temp_path" );
    }
    if( $return_part ) $url = trim( end( $temp ), '/' );
    if( $without_get ) $url = strtok( $url, '?' );
    return $url;
}

/**
 * Get connected user using PHP Session
 *
 * @return mixed
 */
function get_connected_user() : mixed {
    @session_start();
    if( isset( $_SESSION[ '_uuid' ] ) ) {
        return Users::get_user( $_SESSION[ '_uuid' ] );
    }
    return null;
}

/**
 * Get client of connected user
 *
 * @param null|array $user
 * @return null|array
 */
function get_current_client( ?array $user=null ) : null|array {
    global $database, $no_client;
    $no_client = true;
    if( empty( $user ) ) return null;
    $client = $database->prepared_query(
        'SELECT * FROM clients WHERE manager_uuid = ?',
        's',
        $user[ 'uuid' ]
    );
    if( ! empty( $client ) ) {
        $no_client = false;
        return $client[ 0 ];
    } else {
        $client = Clients::get_user_client( $user[ 'uuid' ] );
        if( ! empty( $client ) ) {
            $no_client = false;
            return $client[ 0 ];
        }
    }
    return null;
}

/**
 * Get site of current client connected
 *
 * @param null|array $client
 * @return null|array
 */
function get_current_site( ?array $client=null ) : null|array {
    if( empty( $client ) ) return null;
    @session_start();
    if( ! empty( $_SESSION[ 'selected_website' ] ) ) {
        $site = Sites::get_by_uuid( $_SESSION[ 'selected_website' ], $client[ 'uuid' ] );
        if( ! empty( $site ) ) {
            return end( $site );
        }
    }
    return null;
}

/**
 * Add value to info section
 *
 * @param string $content
 * @param null|string $scope
 * @param null|string $tag
 * @return void
 */
function add_info( string $content, ?string $scope='errors', ?string $tag=null ) : void {
    @session_start();
    if(
        ! isset( $_SESSION[ '_errors' ] ) ||
        ! is_array( $_SESSION[ '_errors' ] )
    ) $_SESSION[ '_errors' ] = array(
        'errors' => array(),
        'infos' => array()
    );
    if(
        ! isset( $_SESSION[ '_errors' ][ $scope ] ) ||
        ! is_array( $_SESSION[ '_errors' ][ $scope ] )
    ) $_SESSION[ '_errors' ][ $scope ] = array();
    $content = array(
        'content' => $content,
        'validity' => time()
    );
    if( $tag === null ) {
        $_SESSION[ '_errors' ][ $scope ][ 'unnamed' ] = $content;
    } else {
        $_SESSION[ '_errors' ][ $scope ][ $tag ] = $content;
    }
}

/**
 * Get all infos from session
 *
 * @param null|string $scope
 * @param int $validity_seconds
 * @return array
 */
function get_infos( ?string $scope=null, int $validity_seconds=30 ) : array {
    @session_start();
    if( ! empty( $_SESSION[ '_errors' ] ) ) {
        check_infos_validity( $validity_seconds );
        if( ! empty( $_SESSION[ '_errors' ][ $scope ] ) ) return $_SESSION[ '_errors' ][ $scope ];
        return $_SESSION[ '_errors' ];
    }
    return array();
}

/**
 * Get info from sessions infos
 *
 * @param string $scope
 * @param string $tag
 * @param int $validity_seconds
 * @return string|array
 */
function get_info( string $scope, string $tag, int $validity_seconds=30 ) : string|array {
    check_infos_validity( $validity_seconds );
    $content = get_infos( $scope );
    if( ! empty( $content[ $tag ] ) ) return $content[ $tag ];
    return $content;
}

/**
 * Check if current messages stored in session are valid. If not removing it
 *
 * @param int $validity_seconds
 * @return void
 */
function check_infos_validity( int $validity_seconds=30 ) : void {
    @session_start();
    foreach( $_SESSION[ '_errors' ] as $group => $loop_group ) {
        foreach( $loop_group as $name => $loop_log ) {
            if(
                is_array( $loop_log ) &&
                time() > $loop_log[ 'validity' ] + $validity_seconds
            ) {
                unset( $_SESSION[ '_errors' ][ $group ][ $name ] );
            }
        }
    }
}

/**
 * Restore all infos from session
 *
 * @return void
 */
function restore_infos() : void {
    @session_start();
    $_SESSION[ '_errors' ] = array(
        'errors' => array(),
        'infos' => array()
    );
}

/**
 * Remove info from session
 *
 * @param string $scope
 * @param string $tag
 * @return void
 */
function remove_info( string $scope, string $tag ) : void {
    $content = get_infos();
    if( isset( $content[ $scope ][ $tag ] ) ) {
        unset( $content[ $scope ][ $tag ] );
    }
    $_SESSION[ '_errors' ] = $content;
}

/**
 * Create a nonce (thanks WordPress)
 *
 * @param string $action
 * @param string $id_user
 * @param string $token
 * @param int $living_seconds
 * @return string
 */
function create_nonce( string $action, string $id_user, string $token, int $living_seconds=86400 ) : string {
    $tick = ceil( time() / ( $living_seconds / 2 ) );
    return substr(
        hash_hmac(
            'md5',
            $tick . '|' . $action . '|' . $id_user . '|' . $token,
            NONCE_SALT
        ),
        -12,
        10
    );
}

/**
 * Validate the nonce (thanks WordPress)
 *
 * @param string $action
 * @param string $id_user
 * @param string $token
 * @param string $nonce
 * @param int $living_seconds
 * @return bool
 */
function validate_nonce( string $action, string $id_user, string $token, string $nonce, int $living_seconds=86400 ) : bool {
    $tick = ceil( time() / ( $living_seconds / 2 ) );
    $excepted = substr(
        hash_hmac(
            'md5',
            $tick . '|' . $action . '|' . $id_user . '|' . $token,
            NONCE_SALT
        ),
        -12,
        10
    );
    if( hash_equals( $excepted, $nonce ) ) return true;
    return false;
}

/**
 * Create timed hash
 *
 * @param string $action
 * @param string $id_user
 * @param string $token
 * @param int $living_seconds
 * @return string
 */
function create_timed_hash( string $action, string $id_user, string $token, int $living_seconds=86400 ) : string {
    $current_time = time();
    $expiration = $current_time + $living_seconds;
    $data = "$action|$id_user|$token|$expiration";
    $hash = hash_hmac( 'md5', $data, NONCE_SALT );
    return base64_encode( "$hash|$expiration" );
}

/**
 * Validate timed hash
 *
 * @param string $hash
 * @param string $action
 * @param string $id_user
 * @param string $token
 * @return bool
 */
function validate_timed_hash( string $hash, string $action, string $id_user, string $token ) : bool {
    $decoded = base64_decode( $hash );
    [ $decoded_hash, $expiration ] = explode( '|', $decoded, 2 );
    if( time() > $expiration ) return false;
    $data = "$action|$id_user|$token|$expiration";
    $expected = hash_hmac( 'md5', $data, NONCE_SALT );
    return $decoded_hash === $expected;
}

/**
 * Create UUID (length 32 chars)
 *
 * @return string
 * @throws RandomException
 */
function create_uuid() : string {
    $data = random_bytes( 16 );
    $data[ 6 ] = chr( ord( $data[ 6 ] ) & 0x0f | 0x40 );
    $data[ 8 ] = chr( ord( $data[ 8 ] ) & 0x3f | 0x80 );
    return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
}

/**
 * Get well-formed phone number from input
 *
 * @param string|null $input
 * @param string|null $return_if_null
 * @return string
 */
function get_well_phone( ?string $input=null, ?string $return_if_null='' ) : string {
    if( $input === null ) return $return_if_null;
    $input = str_replace( ' ', '', $input );
    if( empty( $input ) ) return $input;
    if( $input[ 0 ] === '+' ) {
        $text = implode( '.', array_reverse( array_map( fn( $x ) => implode( array_reverse( $x ) ), array_chunk( str_split( strrev( $input ) ), 2 ) ) ) );
        $pos = strpos( $text, '.' );
        return substr_replace( $text, '', $pos, 1 );
    } else {
        if( $input[ 0 ] !== '0' ) $input = "0$input";
        return implode( '.', array_map( 'implode', array_chunk( str_split( $input ), 2 ) ) );
    }
}

/**
 * Scan input directory (and subdirectories) to get controllers
 *
 * @param string $current_path
 * @param array $domains
 * @param array $additional
 * @param int $i
 * @return void
 */
function scan_controllers( string $current_path, array &$domains, array $additional=array(), int $i=1 ) : void {
    if( ! is_dir( $current_path ) ) return;
    foreach( array_diff( scandir( $current_path ), array( '.', '..' ) ) as $loop_domain ) {
        $to_add = array();
        if( is_dir( "$current_path/$loop_domain" ) ) {
            $temp_files = array_diff( scandir( "$current_path/$loop_domain" ), array( '.', '..' ) );
            foreach( $temp_files as $loop_file ) {
                if( is_dir( "$current_path/$loop_domain/$loop_file" ) ) {
                    scan_controllers( "$current_path/$loop_domain", $domains, array_merge( $additional, array( $loop_domain ) ), ++$i );
                } else {
                    $to_add[] = "$current_path/$loop_domain/$loop_file";
                }
            }
        }
        if( ! empty( $to_add ) ) {
            if(
                ! empty( $additional )
            ) {
                $test = array_get_multidimensional( $domains, $additional );
                if(
                    $test !== null &&
                    isset( $test[ $loop_domain ] ) &&
                    is_array( $test[ $loop_domain ] )
                ) {
                    $temp_domains = search_multidim_array_by_key( $loop_domain, $domains );
                    add_to_multidimensional( array( ...$additional, $loop_domain ), $domains, array_merge( $temp_domains[ $loop_domain ], $to_add ) );
                } else {
                    add_to_multidimensional( array( ...$additional, $loop_domain ), $domains, $to_add );
                }
            } else if(
                isset( $domains[ $loop_domain ] ) &&
                is_array( $domains[ $loop_domain ] )
            ) {
                $domains[ $loop_domain ] = array_merge( $domains[ $loop_domain ], $to_add );
            } else {
                $domains[ $loop_domain ] = $to_add;
            }
        }
    }
}

function add_to_multidimensional( array $path, array &$content, mixed $to_add ) : void {
    if( empty( $path ) ) {
        $content = $to_add;
        return;
    }
    foreach( $path as $index => $loop_path ) {
        unset( $path[ $index ] );
        if(
            ! isset( $content[ $loop_path ] )
        ) {
            $content[ $loop_path ] = array();
        }
        add_to_multidimensional( $path, $content[ $loop_path ], $to_add );
        return;
    }
}

/**
 * Get value from multiple keys chained for a multidimensional array
 *
 * @param array $array
 * @param array $keys
 * @return null|array
 */
function array_get_multidimensional( array $array, array $keys=array() ) : mixed {
    $temp = $array;
    foreach( $keys as $key ) {
        if( is_array( $temp ) && isset( $temp[ $key ] ) ) {
            $temp = $temp[ $key ];
        }
    }
    return $temp === $array ? null : $temp;
}

/**
 * Search value into multidimensional array by key
 *
 * @param string $key
 * @param array $data
 * @return mixed
 */
function search_multidim_array_by_key( string $key, array $data ) : mixed {
    if( array_key_exists( $key, $data ) ) {
        return $data[ $key ];
    }
    foreach( $data as $loop_value ) {
        if( is_array( $loop_value ) ) {
            $found = call_user_func( __FUNCTION__, $key, $loop_value );
            if( $found ) return $loop_value;
        }
    }
    return false;
}

/**
 * Register all required controllers for current request
 *
 * @param src\classes\Router $router
 * @param array $domain_route
 * @param array $route
 * @param array $additional
 * @return void
 */
function register_controllers( src\classes\Router $router, array $domain_route, array $route, array $additional=array() ) : void {
    foreach( $domain_route as $name => $loop_route ) {
        if( is_string( $name ) ) {
            register_controllers( $router, $loop_route, $route, array( ...$additional, $name ) );
        } else {
            try {
                $final_domain = $route[ 'domain' ] . ( ! empty( $additional ) ? '\\' . implode( '\\', $additional ) : '' );
                $router->register( 'controllers\\' . $final_domain . '\\' . pathinfo( $loop_route, PATHINFO_FILENAME ), $final_domain );
            } catch ( ReflectionException $e ) {
                //@TODO raise error
                echo $e->getMessage();
            }
        }
    }
}

/**
 * Get prompt rate based on current calculated rate and limits
 *
 * @param float $current
 * @param float $base_rate
 * @param float $low_limit
 * @param float $high_limit
 * @return float
 */
function get_prompt_rate( float $current, float $base_rate=3.42642, float $low_limit=3.10, float $high_limit=4.3 ) : float {
    if( $current < $low_limit ) return $base_rate;
    else if( $current > $high_limit ) return $base_rate;
    return $current;
}

/**
 * Get stat price using known information
 *
 * @param array $stat_price
 * @param array $price_config
 * @param int $in_for
 * @param int $out_for
 * @return float
 */
function get_stat_price( array $stat_price, array $price_config, int $in_for=1000, int $out_for=1000 ) : float {
    return ( @$stat_price[ 'in_tokens' ] * @$price_config[ 'input' ] ) / $in_for + ( @$stat_price[ 'out_tokens' ] * @$price_config[ 'output' ] ) / $out_for;
}

/**
 * Fetch the actual used domain of the requested URL coming from SSL Certificate
 *
 * @param string $base_url
 * @return string
 */
function get_certificate_domain( string $base_url ) : string {
    $curl = curl_init();
    curl_setopt_array( $curl, array(
        CURLOPT_URL => $base_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
        CURLOPT_CERTINFO => true,
        CURLOPT_NOBODY => true
    ) );
    if( curl_exec( $curl ) === false ) return $base_url;
    $certificate = curl_getinfo( $curl, CURLINFO_CERTINFO );
    if(
        ! empty( $certificate[ 0 ][ 'Subject' ] ) &&
        preg_match( '/CN\s*=\s*(?<domain>.*)/i', $certificate[ 0 ][ 'Subject' ], $match )
    ) {
        return $match[ 'domain' ];
    }
    return $base_url;
}

// Silence is golden