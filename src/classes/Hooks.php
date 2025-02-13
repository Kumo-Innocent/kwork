<?php

namespace src\classes;

class Hooks {

    private array $hooks = array();

    /**
     * Remove hook using domain
     *
     * @param string $domain
     * @return void
     */
    public function remove_hook( string $domain ) : void {
        if( isset( $this->hooks[ $domain ] ) ) {
            unset( $this->hooks[ $domain ] );
        }
    }

    /**
     * Add hook to domain hooks list
     *
     * @param string $domain
     * @param mixed $callback
     * @param mixed $args
     * @param mixed $default
     * @return void
     */
    public function add_hook( string $domain, mixed $callback, mixed $args=null, mixed $default=null ) : void {
        if( ! in_array( $domain, array_keys( $this->hooks ) ) ) {
            $this->hooks[ $domain ] = array(
                array(
                    'callback' => $callback,
                    'args' => $args,
                    'default' => $default
                )
            );
        } else {
            $this->hooks[ $domain ][] = array(
                'callback' => $callback,
                'args' => $args,
                'default' => $default
            );
        }
    }

    /**
     * Start all hooks for the specified domain
     *
     * @param string $domain
     * @param mixed $default
     * @param mixed $args
     * @return mixed
     */
    public function do_hook( string $domain, mixed $default=null, mixed $args=null ) : mixed {
        if( ! in_array( $domain, array_keys( $this->hooks ) ) ) return null ?? $default;
        foreach( $this->hooks[ $domain ] as $hook ) {
            if(
                is_string( $hook[ 'callback' ] ) &&
                str_contains( $hook[ 'callback' ], '@' )
            ) {
                list( $scope, $method ) = explode( '@', $hook[ 'callback' ], 2 );
                if(
                    class_exists( $scope ) &&
                    method_exists( $scope, $method ) &&
                    ( new \ReflectionMethod( $scope, $method ) )->isStatic()
                ) {
                    return $scope::$method( $hook[ 'args' ] ?? $args ) ?? $hook[ 'default' ] ?? $default;
                }
            } else if(
                (
                    is_string( $hook[ 'callback' ] ) &&
                    function_exists( $hook[ 'callback' ] )
                ) ||
                is_callable( $hook[ 'callback' ] )
            ) {
                return $hook[ 'callback' ]( $hook[ 'args' ] ?? $args ) ?? $hook[ 'default' ] ?? $default;
            } else {
                return $hook[ 'callback' ] ?? $hook[ 'default' ] ?? $default;
            }
        }
        return null ?? $default;
    }

}

// Silence is golden