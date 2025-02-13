<?php

namespace src\classes;

class Stripe extends API {

    private string $api_key;
    private string $api_url = "https://api.stripe.com/v1";
    private \CurlHandle|null $curl = null;
    private bool $is_api;

    public function __construct( string $api_key, ?string $api_url=null, bool $is_api=false ) {
        $this->is_api = $is_api;
        $this->api_key = $api_key;
        if( ! empty( $api_url ) ) $this->api_url = $api_url;
        if( empty( $this->curl ) ) $this->prepare_curl();
        if( ( $temp = curl_init() ) ) $this->curl = $temp;
        return $this;
    }

    private function prepare_curl() : void {
        if(
            ! function_exists( 'curl_init' ) ||
            ! function_exists( 'curl_setopt_array' ) ||
            ! function_exists( 'curl_exec' ) ||
            ! function_exists( 'curl_close' )
        ) {
            if( $this->is_api ) self::send( "Fonctions cURL requises non disponibles. (6dcbd80f404dba0618330b484bbb3a6e)", false );
            die( "Fonctions cURL requises non disponibles. (6dcbd80f404dba0618330b484bbb3a6e)" );
        }
        if( ( $temp = curl_init() ) ) $this->curl = $temp;
    }

    /**
     * Retrieve Stripe session's content
     *
     * @param string $session_id
     * @return array
     */
    public function retrieve_session( string $session_id ) : array {
        if( empty( $this->curl ) ) $this->prepare_curl();
        if( ! empty( $this->curl ) ) {
            curl_setopt_array( $this->curl, array(
                CURLOPT_URL => $this->api_url . "/checkout/sessions/$session_id",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $this->api_key,
                )
            ) );
            $response = curl_exec( $this->curl );
            curl_close( $this->curl );
            $json_response = json_decode( $response, true );
            if(
                ! empty( $json_response[ 'amount_total' ] ) &&
                ! empty( $json_response[ 'customer_details' ][ 'email' ] )
            ) {
                return array(
                    'session' => $session_id,
                    'invoice' => $json_response[ 'invoice' ],
                    'amount' => intval( $json_response[ 'amount_total' ] ) / 100,
                    'email' => $json_response[ 'customer_details' ][ 'email' ],
                    'current_time' => time(),
                    'micro_time' => microtime()
                );
            }
            return array();
        } else return array();
    }

    /**
     * Retrieve Stripe invoice's content
     *
     * @param string $invoice_id
     * @return array
     */
    public function retrieve_invoice( string $invoice_id ) : array {
        if( empty( $this->curl ) ) $this->prepare_curl();
        if( ! empty( $this->curl ) ) {
            curl_setopt_array( $this->curl, array(
                CURLOPT_URL => $this->api_url . "/invoices/$invoice_id",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $this->api_key,
                )
            ) );
            $response = curl_exec( $this->curl );
            curl_close( $this->curl );
            $json_response = json_decode( $response, true );
            if(
                ! empty( $json_response[ 'id' ] ) &&
                ! empty( $json_response[ 'account_name' ] ) &&
                ! empty( $json_response[ 'charge' ] ) &&
                ! empty( $json_response[ 'lines' ][ 'data' ][ 0 ][ 'id' ] ) &&
                ! empty( $json_response[ 'lines' ][ 'data' ][ 0 ][ 'amount' ] ) &&
                ! empty( $json_response[ 'lines' ][ 'data' ][ 0 ][ 'description' ] ) &&
                ! empty( $json_response[ 'lines' ][ 'data' ][ 0 ][ 'quantity' ] ) &&
                ! empty( $json_response[ 'lines' ][ 'data' ][ 0 ][ 'plan' ][ 'id' ] ) &&
                ! empty( $json_response[ 'lines' ][ 'data' ][ 0 ][ 'plan' ][ 'interval' ] ) &&
                ! empty( $json_response[ 'lines' ][ 'data' ][ 0 ][ 'plan' ][ 'product' ] )
            ) {
                return array(
                    'invoice' => $invoice_id,
                    'charge' => $json_response[ 'charge' ],
                    'line_id' => $json_response[ 'lines' ][ 'data' ][ 0 ][ 'id' ],
                    'product_id' => $json_response[ 'lines' ][ 'data' ][ 0 ][ 'plan' ][ 'product' ],
                    'price_id' => $json_response[ 'lines' ][ 'data' ][ 0 ][ 'plan' ][ 'id' ],
                    'customer_name' => $json_response[ 'account_name' ],
                    'amount' => $json_response[ 'lines' ][ 'data' ][ 0 ][ 'amount' ] / 100,
                    'description' => $json_response[ 'lines' ][ 'data' ][ 0 ][ 'description' ],
                    'interval' => $json_response[ 'lines' ][ 'data' ][ 0 ][ 'plan' ][ 'interval' ],
                    'quantity' => $json_response[ 'lines' ][ 'data' ][ 0 ][ 'quantity' ]
                );
            }
            return array();
        } else return array();
    }

}