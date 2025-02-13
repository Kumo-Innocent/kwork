<?php

namespace src\classes;

class WPAPI extends API {
    
    private string $user;
    private string $password;
    private string $domain;
    private string $schema;
    private string $auth_token;
    private bool $is_api;
    private array $accepted_mimes = array(
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_JPEG => 'jpeg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_ICO => 'ico'
    );

    protected bool|\CurlHandle $curl;

    public function __construct(
        string $user,
        string $password,
        string $domain,
        string $schema='/wp-json/wp/v2',
        ?bool $api=false
    ) {
        if(
            ! function_exists( 'curl_init' ) ||
            ! function_exists( 'curl_setopt_array' ) ||
            ! function_exists( 'curl_setopt' ) ||
            ! function_exists( 'curl_exec' ) ||
            ! function_exists( 'curl_close' )
        ) {
            // @TODO raise error
            if( $api ) self::send( "Missing required functions. (39043d54505a61698882baf27a25f882)", false );
            die( "cURL functions not enabled.");
        }
        if( ! function_exists( 'base64_encode' ) ) {
            // @TODO raise error
            if( $api ) self::send( "Missing required functions. (6996f727491cc22af16b4f3c23cca4d9)", false );
            die( "base64 functions not enabled." );
        }
        $this->user = $user;
        $this->password = $password;
        $this->domain = $domain;
        $this->schema = $schema;
        $this->is_api = $api;
        $this->auth_token = base64_encode( "{$user}:{$password}" );
        $this->curl = curl_init();
        $this->_set_curl_opts();
        return $this;
    }

    /**
     * Content to 'application/x-www-form-urlencoded'. array( "key" => "value test" ) ==> "key=value%20test"
     *
     * @param array $content
     * @return string
     */
    private function _url_encode( array $content ) : string {
        $to_return = array();
        foreach( $content as $key => $value ) {
            $to_return[] = "$key=" . rawurldecode( $value );
        }
        return implode( '&', $to_return );
    }

    /**
     * Set cURL base options
     *
     * @return void
     */
    private function _set_curl_opts() : void {
        if( $this->curl ) {
            curl_setopt_array( $this->curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
            ) );
        } else {
            // @TODO raise error
            if( $this->is_api ) self::send( "Missing required functions. (63d354499c8dd78eb52f7d27e1e80159)", false );
            die( "Can't initialize cURL." );
        }
    }

    /**
     * Create WP post using API
     *
     * @param string $title
     * @param string $chapo
     * @param string $content
     * @param string $status
     * @param null|int $real_author
     * @param null|int $media_id
     * @param null|array $categories
     * @param null|string $slug
     * @return array
     */
    public function create_post(
        string $title,
        string $chapo,
        string $content,
        string $status,
        ?int $real_author=null,
        ?int $media_id=null,
        ?array $categories=array(),
        ?string $slug=null
    ) : array {
        $content = "<h2>$chapo</h2>" . $content;
        $post_content = array(
            'title' => $title,
            'content' => $content,
            'status' => $status,
            'categories' => $categories,
            'meta' => array(
                'yoast_wpseo_metadesc' => $chapo
            )
        );
        if( $real_author !== null ) $post_content[ 'author' ] = $real_author;
        if( $media_id !== null ) $post_content[ 'featured_media' ] = $media_id;
        if( $slug !== null ) $post_content[ 'slug' ] = $slug;
        curl_setopt_array( $this->curl, array(
            CURLOPT_URL => 'https://' . $this->domain . $this->schema . "/posts?context=edit",
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Basic ' . $this->auth_token
            ),
            CURLOPT_POSTFIELDS => json_encode( $post_content )
        ) );
        $response = curl_exec( $this->curl );
        curl_close( $this->curl );
        $decoded_response = json_decode( $response, true );
        if(
            ! $decoded_response ||
            ! isset( $decoded_response[ 'id' ] ) ||
            ! isset( $decoded_response[ 'date' ] )
        ) {
            // @TODO raise error
            return array();
        }
        return array(
            'id' => $decoded_response[ 'id' ],
            'gmt' => $decoded_response[ 'date' ],
            'url' => $decoded_response[ 'link' ],
            'slug' => ! empty( $decoded_response[ 'slug' ] ) ? $decoded_response[ 'slug' ] : $decoded_response[ 'generated_slug' ],
        );
    }

    /**
     * Get WordPress post information
     *
     * @param int $id
     * @return array
     */
    public function get_post( int $id ) : array {
        curl_setopt_array( $this->curl, array(
            CURLOPT_URL => 'https://' . $this->domain . $this->schema . "/posts/$id",
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic ' . $this->auth_token
            )
        ) );
        $response = curl_exec( $this->curl );
        curl_close( $this->curl );
        $decoded_response = json_decode( $response, true );
        if(
            ! $decoded_response ||
            ! empty( $decoded_response[ 'code' ] )
        ) {
            if( $this->is_api ) self::send( array(
                "message" => "WordPress error. (624c5a9e06be0fc3faa1a76ba54fdb3d)",
                "code" => $decoded_response[ 'code' ],
                "wp_message" => $decoded_response[ 'message' ]
            ), false );
            return array();
        }
        return $decoded_response;
    }

    /**
     * Get media information from '_links' 'wp:featuredmedia' URL
     *
     * @param string $url
     * @return array
     */
    public function get_media( string $url ) : array {
        curl_setopt_array( $this->curl, array(
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic ' . $this->auth_token
            )
        ) );
        $response = curl_exec( $this->curl );
        curl_close( $this->curl );
        $decoded_response = json_decode( $response, true );
        if(
            ! $decoded_response ||
            ! empty( $decoded_response[ 'code' ] )
        ) {
            if( $this->is_api ) self::send( array(
                "message" => "WordPress error. (624c5a9e06be0fc3faa1a76ba54fdb3d)",
                "code" => $decoded_response[ 'code' ],
                "wp_message" => $decoded_response[ 'message' ]
            ), false );
            return array();
        }
        return $decoded_response;
    }

    /**
     * Delete WordPress Post
     *
     * @param int $real_id
     * @param bool $force
     * @return array
     */
    public function delete_post( int $real_id, bool $force=false ) : array {
        curl_setopt_array( $this->curl, array(
            CURLOPT_URL => 'https://' . $this->domain . $this->schema . "/posts/$real_id" . ( $force ? '?force=true' : ''  ),
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $this->auth_token
            )
        ) );
        $response = curl_exec( $this->curl );
        curl_close( $this->curl );
        $decoded_response = json_decode( $response, true );
        if(
            ! empty( $decoded_response[ 'deleted' ] ) &&
            $decoded_response[ 'deleted' ] === true &&
            ! empty( $decoded_response[ 'previous' ][ 'id' ] ) &&
            ! empty( $decoded_response[ 'previous' ][ 'date' ] )
        ) {
            return array(
                'id' => $decoded_response[ 'previous' ][ 'id' ],
                'gmt' => $decoded_response[ 'previous' ][ 'date' ]
            );
        } else if(
            ! empty( $decoded_response[ 'id' ] ) &&
            ! empty( $decoded_response[ 'date' ] )
        ) {
            return array(
                'id' => $decoded_response[ 'id' ],
                'gmt' => $decoded_response[ 'date' ]
            );
        } if( ! empty( $decoded_response[ 'code' ] ) ) {
            return match( $decoded_response[ 'code' ] ) {
                'rest_post_invalid_id' => array(
                    'message' => "Not existing",
                    'code' => 1
                ),
                'rest_already_trashed' => array(
                    'message' => "Already in trash",
                    'code' => 2
                ),
                default => array()
            };
        }
        return array();
    }

    /**
     * Update WordPress Post based on post id
     *
     * @param integer $real_id
     * @param array $to_change
     * @return array
     */
    public function update_post( int $real_id, array $to_change ) : array {
        curl_setopt_array( $this->curl, array(
            CURLOPT_URL => 'https://' . $this->domain . $this->schema . "/posts/$real_id",
            CURLOPT_CUSTOMREQUEST => 'POST',
            /*CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $this->auth_token
            ),
            CURLOPT_POSTFIELDS => $this->_url_encode( $to_change )*/
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Basic ' . $this->auth_token
            ),
            CURLOPT_POSTFIELDS => json_encode( $to_change )
        ) );
        $response = curl_exec( $this->curl );
        curl_close( $this->curl );
        $decoded_response = json_decode( $response, true );
        if( ! $decoded_response ) {
            // @TODO raise error
            return array();
        }
        return array(
            'id' => $decoded_response[ 'id' ],
            'gmt' => $decoded_response[ 'date' ],
            'url' => $decoded_response[ 'link' ],
            'slug' => ! empty( $decoded_response[ 'slug' ] ) ? $decoded_response[ 'slug' ] : $decoded_response[ 'generated_slug' ]
        );
    }

    /**
     * Get WP categories using API
     *
     * @return array
     */
    public function get_categories() : array {
        curl_setopt_array( $this->curl, array(
            CURLOPT_URL => 'https://' . $this->domain . $this->schema . "/categories?per_page=100",
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic ' . $this->auth_token
            )
        ) );
        $response = curl_exec( $this->curl );
        curl_close( $this->curl );
        $decoded_response = json_decode( $response, true );
        if(
            ! $decoded_response ||
            count( $decoded_response ) <= 0
        ) {
            // @TODO raise error
            return array();
        }
        return $decoded_response;
    }

    /**
     * List all users
     *
     * @param null|int $per_page
     * @return array
     */
    public function list_users( ?int $per_page=null ) : array {
        curl_setopt_array( $this->curl, array(
            CURLOPT_URL => 'https://' . $this->domain . $this->schema . "/users" . ( ! empty( $per_page ) ? "?per_page=$per_page" : '' ),
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic ' . $this->auth_token,
                'Accept: application/json',
                'Content-Type: application/json'
            )
        ) );
        $response = curl_exec( $this->curl );
        curl_close( $this->curl );
        $decoded_response = json_decode( $response, true );
        if(
            ! $decoded_response ||
            ! empty( $decoded_response[ 'code' ] )
        ) return array();
        return $decoded_response;
    }

    /**
     * List all posts
     *
     * @param null|int $per_page
     * @param int $offset
     * @param array $to_exclude
     * @return array
     */
    public function list_posts( ?int $per_page=null, int $offset=0, array $to_exclude=array() ) : array {
        curl_setopt_array( $this->curl, array(
            CURLOPT_URL => 'https://' . $this->domain . $this->schema . "/posts?status=publish,draft&offset=$offset" . ( ! empty( $per_page ) ? "&per_page=$per_page" : '' ) . ( ! empty( $to_exclude ) ? '&exclude=' . implode( ',', $to_exclude ) : '' ),
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic ' . $this->auth_token,
                'Accept: application/json'
            )
        ) );
        $response = curl_exec( $this->curl );
        curl_close( $this->curl );
        $decoded_response = json_decode( $response, true );
        if(
            ! $decoded_response ||
            ! empty( $decoded_response[ 'code' ] )
        ) return array();
        return $decoded_response;
    }

    /**
     * Upload image to the WordPress server
     *
     * @param string $url
     * @param string|null $name
     * @return array
     */
    public function upload_media( string $url, ?string $name=null ) : array {
        if(
            ! filter_var(
                ini_get( 'allow_url_fopen' ),
                FILTER_VALIDATE_BOOLEAN
            ) ||
            ! function_exists( 'file_get_contents' ) ||
            ! function_exists( 'exif_imagetype' )
        ) {
            if( $this->is_api ) self::send( "Missing required functions. (63d354499c8dd78eb52f7d27e1e80159)", false );
            die( "Can't initialize cURL." );
        }
        $user = get_connected_user();
        if( ! ( $exif = @exif_imagetype( $url ) ) ) {
            $image_curl = curl_init();
            curl_setopt_array( $image_curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Basic ' . $this->auth_token
                )
            ) );
            $temp_content = curl_exec( $image_curl );
            curl_close( $image_curl );
            $temp_file = tempnam( sys_get_temp_dir(), 'exif_' );
            file_put_contents( $temp_file, $temp_content );
            $fp = fopen( $temp_file, 'rb' );
            fclose( $fp );
            if( ! ( $exif = @exif_read_data( $fp ) ) ) {
                goto cant_exif;
            }
            unlink( $temp_file );
            cant_exif:
            if( $this->is_api ) self::send( "Can't check exif. (f7fb9f673c7f332f24cd46b41c52fe78)", false );
            die( "Can't check exif." );
        }
        if( ! in_array( $exif, array_keys( $this->accepted_mimes ) ) ) {
            if( $this->is_api ) self::send( "Image format not accepted. (56b42bf3ccc8cfabb37bc56134aab7db)", false );
            die( "Image format not accepted." );
        }
        $current_user = $this->get_current_user();
        if( empty( $current_user ) ) {
            if( $this->is_api ) self::send( "Can't get current user informations. (a99080098aecbf81d670029902cfe113)", false );
            die( "Can't get current user informations." );
        }
        curl_setopt_array( $this->curl, array(
            CURLOPT_URL => 'https://' . $this->domain . $this->schema . "/media",
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic ' . $this->auth_token,
                'X-WP-Nonce: ' . create_nonce( 'media-form', $current_user[ 'id' ], session_id() ?? md5( $user[ 'email' ] ) ),
                'Content-Disposition: attachment; filename="' . ( $name ?? basename( $url ) ) . '"',
                'Content-Type: ' . image_type_to_mime_type( $exif )
            ),
            CURLOPT_POSTFIELDS => file_get_contents( $url )
        ) );
        $response = curl_exec( $this->curl );
        curl_close( $this->curl );
        $decoded_response = json_decode( $response, true );
        if(
            ! $decoded_response ||
            ! empty( $decoded_response[ 'code' ] )
        ) {
            if( $this->is_api ) self::send( array(
                "message" => "WordPress error. (624c5a9e06be0fc3faa1a76ba54fdb3d)",
                "code" => $decoded_response[ 'code' ],
                "wp_message" => $decoded_response[ 'message' ]
            ), false );
            return array();
        }
        return $decoded_response;
    }

    /**
     * Get the current WordPress used user
     *
     * @return array
     */
    public function get_current_user() : array {
        curl_setopt_array( $this->curl, array(
            CURLOPT_URL => 'https://' . $this->domain . $this->schema . "/users/me",
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic ' . $this->auth_token
            )
        ) );
        $response = curl_exec( $this->curl );
        curl_close( $this->curl );
        $decoded_response = json_decode( $response, true );
        if(
            ! isset( $decoded_response[ 'id' ] ) &&
            ! isset( $decoded_response[ 'name' ] )
        ) {
            return array();
        } else {
            return $decoded_response;
        }
    }

    /**
     * Test site connection
     *
     * @return bool
     */
    public function test_connection() : bool {
        $users = $this->list_users( 1 );
        if( empty( $users ) ) {
            return false;
        }
        return true;
    }

}