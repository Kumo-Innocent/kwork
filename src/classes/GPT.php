<?php

namespace src\classes;

class GPT extends API {

    private string $auth_token;
    private bool $is_api;
    private string $api_url;

    protected bool|\CurlHandle $curl;

    public function __construct(
        string $auth_token,
        ?string $api_url="https://api.openai.com/v1/chat/completions",
        ?bool $is_api=false,
    ) {
        $this->api_url = $api_url;
        $this->is_api = $is_api;
        $this->auth_token = $auth_token;
    }

    /**
     * Initialize cURL with headers and parameters.
     *
     * @param array $post_content Content send to API
     * @return void
     */
    private function _init_curl( array $post_content ) : void {
        if( $this->auth_token == '' ) {
            // @TODO raise error
            if( $this->is_api ) self::send( "Empty auth token.", false );
            die( "Can't initialize cURL." );
        }
        if(
            ! function_exists( 'curl_init' ) ||
            ! function_exists( 'curl_setopt_array' ) ||
            ! function_exists( 'curl_setopt' ) ||
            ! function_exists( 'curl_exec' ) ||
            ! function_exists( 'curl_close' )
        ) {
            // @TODO raise error
            if( $this->is_api ) self::send( "Missing required functions. (63d354499c8dd78eb52f7d27e1e80159)", false );
            die( "Can't initialize cURL." );
        }
        $this->curl = curl_init( $this->api_url );
        if( ! $this->curl ) {
            // @TODO raise error
            if( $this->is_api ) self::send( "Error initializing cURL.", false );
            die( "Error initializing cURL." );
        }
        curl_setopt_array( $this->curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->auth_token
            ),
            CURLOPT_POSTFIELDS => json_encode( $post_content )
        ) );
    }

    /**
     * Prepare data array to POST
     *
     * @param string $prompt Final prompt
     * @param string $model Default gpt-4
     * @param string $role Default user
     * @param float $temperature Default 0.5
     * @param integer $max_tokens Default 1024
     * @param integer $top_p Default 1
     * @param integer $frequency_penalty Default 0
     * @param integer $presence_penalty Default 0
     * @return array
     */
    private function _prepare_content(
        string $prompt,
        string $model='gpt-4',
        string $role='user',
        float $temperature=0.5,
        int $max_tokens=1024,
        int $top_p=1,
        int $frequency_penalty=0,
        int $presence_penalty=0
    ) : array {
        return array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => $role,
                    'content' => $prompt
                )
            ),
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
            'top_p' => $top_p,
            'frequency_penalty' => $frequency_penalty,
            'presence_penalty' => $presence_penalty
        );
    }

    /**
     * Get SEO from input article
     *
     * @param string $client_uuid
     * @param string $site_uuid
     * @param string $scope
     * @param string $input
     * @param array $pla_config
     * @return string
     */
    public function get_seo( string $client_uuid, string $site_uuid, string $scope, string $input, array $pla_config ) : string {
        if( ! ( @$pla_config[ 'content' ][ 'prompt' ] ?? 1 ) ) return '';
        $temp = str_replace(
            '[input]',
            $input,
            $pla_config[ 'content' ][ 'prompt' ]
        );
        $this->_init_curl(
            $this->_prepare_content(
                $temp
            )
        );
        return $this->_fetch_content(
            $client_uuid,
            $site_uuid,
            $pla_config[ 'uuid' ],
            serialize( $pla_config[ 'content' ] ),
            strlen( $temp ),
            $pla_config[ 'content' ][ 'model' ]
        );
    }

    /**
     * Get content from OpenAI API request
     *
     * @param string $client_uuid
     * @param string $site_uuid
     * @param string $prompt_uuid
     * @param string $input
     * @param array $prompt_config
     * @param array $content
     * @param string &$uuid_stat
     * @return string
     */
    public function get_content(
        string $client_uuid,
        string $site_uuid,
        string $prompt_uuid,
        string $input,
        array $prompt_config,
        array $content,
        string &$uuid_stat
    ) : string {
        foreach( $prompt_config[ 'config' ] as $name => $config ) {
            if( in_array( $name, array_keys( $content ) ) ) {
                if( is_array( $content[ $name ] ) ) {
                    $content[ $name ] = implode( ", ", $content[ $name ] );
                }
                $input = str_replace( "[$name]", $content[ $name ], $input );
            }
        }
        $this->_init_curl(
            $this->_prepare_content(
                $input
            )
        );
        $prompt_config[ 'content' ] = $input;
        return $this->_fetch_content(
            $client_uuid,
            $site_uuid,
            $prompt_uuid,
            serialize( $prompt_config ),
            strlen( $input ),
            $prompt_config[ 'model' ],
            $uuid_stat
        );
    }

    /**
     * Fetch content and store stats
     *
     * @param string $client_uuid
     * @param string $site_uuid
     * @param string $prompt_uuid
     * @param string $prompt_config
     * @param int $in_letters
     * @param string $model
     * @param null|string &$uuid_stat
     * @return string
     */
    private function _fetch_content( string $client_uuid, string $site_uuid, string $prompt_uuid, string $prompt_config, int $in_letters, string $model, ?string &$uuid_stat=null ) : string {
        global $hooks_engine;
        $config = array();
        list( $config, $validity ) = $hooks_engine->do_hook(
            'gpt-fetch-content-client-validity',
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
            add_info( "Crédit IA insuffisant.", tag: "no-credit" );
            return '';
        }
        if(
            ! empty( $config[ 'content' ][ 'credit' ] ) &&
            $config[ 'content' ][ 'credit' ] - 1 < 0
        ) return '';
        $response = curl_exec( $this->curl );
        curl_close( $this->curl );
        $decoded_response = json_decode( $response, true );
        if(
            ! isset( $decoded_response[ 'id' ] ) ||
            ! isset( $decoded_response[ 'model' ] ) ||
            ! isset( $decoded_response[ 'created' ] ) ||
            ! isset( $decoded_response[ 'choices' ] ) ||
            ! isset( $decoded_response[ 'choices' ][ 0 ] ) ||
            ! isset( $decoded_response[ 'choices' ][ 0 ][ 'message' ] ) ||
            ! isset( $decoded_response[ 'choices' ][ 0 ][ 'message' ][ 'content' ] ) ||
            $decoded_response[ 'choices' ][ 0 ][ 'finish_reason' ] !== 'stop'
        ) {
            // @TODO raise error
            return '';
        }
        $result = trim( $decoded_response[ 'choices' ][ 0 ][ 'message' ][ 'content' ], '"' );
        $hooks_engine->add_hook( 'gpt-fetch-content-after-fetching', array(
            'config' => $config,
            'site_uuid' => $site_uuid,
            'prompt_uuid' => $prompt_uuid,
            'prompt_config' => $prompt_config,
            'decoded_response' => $decoded_response,
            'in_letters' => $in_letters,
            'out_letters' => strlen( $result ),
            'model' => $model
        ) );
        return $result;
    }

}