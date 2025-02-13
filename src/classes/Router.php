<?php

namespace src\classes;

use JetBrains\PhpStorm\NoReturn;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class Router {

    private array $controllers = array();
    private array $routes = array();

    /**
     * Register new controller to the app
     *
     * @param string $namespace
     * @param string $domain
     * @throws ReflectionException
     * @return void
     */
    public function register( string $namespace, string $domain ) : void {
        $this->controllers[] = $namespace;
        $reflection = new ReflectionClass( $namespace );
        $methods = $reflection->getMethods( ReflectionMethod::IS_PUBLIC );
        foreach( $methods as $loop_method ) {
            $method_annotations = $loop_method->getAttributes( Route::class );
            $template_annotation = $loop_method->getAttributes( Template::class );
            $template_instance = null;
            if( ! empty( $template_annotation ) ) {
                $template_annotation = end( $template_annotation );
                $template_instance = $template_annotation?->newInstance();
            }
            foreach( $method_annotations as $loop_annotation ) {
                $route_instance = $loop_annotation?->newInstance();
                foreach( $route_instance->path as $loop_path ) {
                    $this->routes[ $domain . ":::$loop_path" ] = array(
                        'class_method' => $loop_method,
                        'method' => $route_instance->method,
                        'security' => $route_instance->security,
                        'callback' => $loop_method,
                        'token' => array(
                            'bearer' => $route_instance->bearer_token,
                            'required' => $route_instance->token_required,
                        ),
                        'template' => $template_instance?->to_render,
                        'instance' => $route_instance
                    );
                }
            }
        }
    }

    /**
     * Match route using the method's route settings
     *
     * @param array $route Current parsed route
     * @return array
     */
    private function match( array $route ) : array {
        foreach(
            array_filter( $this->routes, fn( $x ) => str_contains( $x, $route[ 'domain' ] . ':::' ), ARRAY_FILTER_USE_KEY )
            as $loop_uri => $loop_route
        ) {
            $loop_uri = str_replace( $route[ 'domain' ] . ':::', '', $loop_uri );
            if( preg_match( "#^$loop_uri$#i", $route[ 'uri' ], $match ) ) {
                unset( $match[ 0 ] );
                return array(
                    'route' => $loop_route,
                    'args' => $match
                );
            }
        }
        if( preg_match( '|^/(?P<domain>\w+){1}.*$|i', $route[ 'uri' ], $match ) ) {
            $domain = $match[ 'domain' ];
            $route[ 'uri' ] = str_replace( "/$domain", '', $route[ 'uri' ] );
            if( empty( $route[ 'uri' ] ) ) $route[ 'uri' ] = "/";
            $route[ 'domain' ] = implode( '\\', array( ...explode( '\\', $route[ 'domain' ] ), $domain ) );
            return $this->match( $route );
        }
        return array();
    }

    /**
     * Strict check between two arrays to change the state
     *
     * @param array $content The base content
     * @param array $strict_check The checking array content
     * @param bool &$state The state to update
     * @return void
     */
    private function strict_check( array $content, array $strict_check, bool &$state ) : void {
        if(
            ! empty( $content ) &&
            ! empty( $strict_check )
        ) {
            foreach( $strict_check as $name => $value ) {
                if(
                    isset( $content[ $name ] ) &&
                    (
                        gettype( $content[ $name ] ) !== gettype( $value ) ||
                        $content[ $name ] !== $value
                    )
                ) {
                    $state = false;
                    break;
                } else {
                    $state = true;
                }
            }
        }
    }

    /**
     * Replace route data before performing strict check
     *
     * @param array $content Security's route content
     * @param null|array $user The user content
     * @param bool $state
     * @param array $sql_used
     * @return void
     */
    private function prepare_strict_check( array $content, null|array $user=array(), bool &$state=false, array &$sql_used=array() ) : void {
        global $database;
        if(
            ! empty( $content[ 'check:sql' ] ) &&
            ! empty( $user )
        ) {
            if( ! in_array( $content[ 'check:sql' ], array_keys( $sql_used ) ) ) {
                $binding = null;
                $parameters = null;
                $temp_sql = $content[ 'check:sql' ];
                if( preg_match_all( '/\[(\w+)\]/im', $content[ 'check:sql' ], $match ) ) {
                    $binding = '';
                    $parameters = array();
                    foreach( $match[ 1 ] as $to_replace ) {
                        if( isset( $user[ $to_replace ] ) ) {
                            switch( gettype( $user[ $to_replace ] ) ) {
                                case 'string':
                                    $binding .= 's';
                                    $temp_sql = str_replace( "[$to_replace]", '?', $temp_sql );
                                    $parameters[] = $user[ $to_replace ];
                                    break;
                                default:
                                    log_it( "Intégrer type dans switch routeur" );
                                    break;
                            }
                        }
                    }
                }
                $temp_data = $database->prepared_query(
                    $temp_sql,
                    $binding,
                    ...$parameters
                );
                if( ! empty( $temp_data ) ) $temp_data = end( $temp_data );
                $sql_used[ $content[ 'check:sql' ] ] = (array)$temp_data;
            } else {
                $temp_data = $sql_used[ $content[ 'check:sql' ] ];
            }
            if(
                ! empty( $temp_data )
            ) {
                $this->strict_check( $temp_data, $content[ 'check:check' ], $state );
            } else if(
                isset( $content[ 'check:ok_if_null' ] ) &&
                gettype( $content[ 'check:ok_if_null' ] ) === 'boolean' &&
                $content[ 'check:ok_if_null' ]
            ) {
                $state = true;
            } else if( ! empty( $content[ 'check:else' ] ) ) {
                header( 'Location: ' . get_url(
                        $content[ 'check:else' ]
                    ) );
                exit;
            }
        }
    }

    /**
     * Redirect page correctly
     *
     * @param string $where
     * @param string|null $to_add
     * @return void
     */
    #[NoReturn] public static function redirect( string $where, ?string $to_add=null ) : void {
        header( 'Location: ' . ( ( $temp = get_url(
                match( $where ) {
                    '_referrer' => ! empty( ( $temp = $_SERVER[ 'HTTP_REFERER' ] ) )
                        ? (
                        ( $final = str_replace(
                            ( ( @$_SERVER[ 'HTTPS' ] ? 'https://' : 'http://' ) . ( $_SERVER[ 'HTTP_HOST' ] . KUMO_ALT ?? '' ) ),
                            '',
                            $_SERVER[ 'HTTP_REFERER' ]
                        ) )[ 0 ] === '/' ? $final : "/$final"
                        )
                        : '/',
                    default => $where
                } . ( $to_add ?: "" )
            ) ) === ( $current = get_url( '+' ) ) ? get_url( '/' ) : $temp ) );
        exit;
    }

    /**
     * Correct redirect
     *
     * @param array $security
     * @param string $scope
     * @return void
     */
    #[NoReturn] private function correct_redirect( array $security, string $scope ) : void {
        if(
            ! empty( $security[ $scope ] ) &&
            gettype( $security[ $scope ] ) === 'string'
        ) {
            self::redirect( $security[ $scope ] );
        } else {
            header( 'Location: ' . get_url( '/' ) );
        }
        exit;
    }

    /**
     * Check route security
     *
     * @param array $user
     * @param array $security
     * @param bool &$state
     * @return void
     */
    private function check_roles( array $user, array $security, bool &$state ) : void {
        global $roles_rights, $hooks_engine;
        $roles_rights = array(
            'No rights'
        );
        $user_roles = array();
        if( ! empty( $user ) ) list( $roles_rights, $user_roles ) = $hooks_engine->do_hook(
            'router-check-roles-get-roles',
            default: array( $roles_rights, $user_roles ),
            args: array( $user[ 'uuid' ], $roles_rights, $user_roles )
        );
        if(
            isset( $security[ 'roles' ] ) &&
            (
                gettype( $security[ 'roles' ] ) === 'string' ||
                is_array( $security[ 'roles' ] )
            ) &&
            $security[ 'roles' ] &&
            ! empty( $user )
        ) {
            $temp_roles = is_array( $security[ 'roles' ] ) ? $security[ 'roles' ] : array( $security[ 'roles' ] );
            $state = $hooks_engine->do_hook(
                'router-check-roles-check-roles',
                default: true,
                args: array(
                    is_array( $security[ 'roles' ] ) ? $security[ 'roles' ] : array( $security[ 'roles' ] ),
                    $user_roles
                )
            );
            if( ! $state ) {
                add_info( "Vous n'avez pas les droits pour accéder à cet espace.", tag: "pdo-error" );
                $this->correct_redirect( $security, 'redirect_noroles_path' );
            }
        } else if(
            isset( $security[ 'roles' ] ) &&
            (
                gettype( $security[ 'roles' ] ) === 'string' ||
                is_array( $security[ 'roles' ] )
            ) &&
            $security[ 'roles' ] &&
            empty( $user )
        ) {
            $state = false;
            add_info( "Vous n'avez pas les droits pour accéder à cet espace.", tag: "pdo-error" );
            $this->correct_redirect( $security, 'redirect_noroles_path' );
        }
    }

    /**
     * Perform route's security checks
     *
     * @param array $route Current route parsed
     * @return bool
     */
    private function check_security( array $route ) : bool {
        if( ! isset( $route[ 'security' ] ) ) {
            return true;
        }
        global $user, $client;
        $security = $route[ 'security' ];
        $state = true;
        $user = get_connected_user();
        $client = get_current_client( $user );
        $this->check_roles( $user ?? array(), $security, $state );
        if(
            isset( $security[ 'need_login' ] ) &&
            gettype( $security[ 'need_login' ] ) === 'boolean' &&
            $security[ 'need_login' ] &&
            empty( $user )
        ) {
            $state = false;
            if(
                isset( $security[ 'redirect_on_nologin' ] ) &&
                gettype( $security[ 'redirect_on_nologin' ] ) === 'boolean' &&
                $security[ 'redirect_on_nologin' ]
            ) {
                $this->correct_redirect( $security, 'redirect_nologin_path' );
                return false;
            }
        } else if(
            isset( $security[ 'need_nologin' ] ) &&
            gettype( $security[ 'need_nologin' ] ) === 'boolean' &&
            $security[ 'need_nologin' ] &&
            ! empty( $user )
        ) {
            $state = false;
            if(
                isset( $security[ 'redirect_on_login' ] ) &&
                gettype( $security[ 'redirect_on_login' ] ) === 'boolean' &&
                isset( $security[ 'redirect_login_path' ] )
            ) {
                if( gettype( $security[ 'redirect_login_path' ] ) === 'string' ) {
                    $this->correct_redirect( $security, 'redirect_login_path' );
                    return false;
                } else if( is_array( $security[ 'redirect_login_path' ] ) ) {
                    $sql_used = array();
                    foreach( $security[ 'redirect_login_path' ] as $loop_url => $loop_check ) {
                        $this->prepare_strict_check(
                            $loop_check,
                            $user,
                            $state,
                            $sql_used
                        );
                        if( $state ) {
                            header( 'Location: ' . get_url(
                                    ! empty( $loop_url ) ? $loop_url : '/'
                                ) );
                            return false;
                        }
                    }
                }
            }
        }
        if(
            isset( $security[ 'need_client' ] ) &&
            gettype( $security[ 'need_client' ] ) === 'boolean' &&
            $security[ 'need_client' ] &&
            empty( $client )
        ) {
            $state = false;
            if(
                isset( $security[ 'redirect_on_noclient' ] ) &&
                gettype( $security[ 'redirect_on_noclient' ] ) === 'boolean' &&
                $security[ 'redirect_on_noclient' ]
            ) {
                $this->correct_redirect( $security, 'redirect_noclient_path' );
                return false;
            }
        }
        $this->prepare_strict_check(
            (array)@$security[ 'strict_check' ],
            $user,
            $state
        );
        if(
            ! $state
        ) {
            $this->correct_redirect( $security, 'redirect_noright_path' );
            return false;
        }
        return $state;
    }

    /**
     * Start router logic
     *
     * @param array $route Current route parsed (on file start.php)
     * @param array &$current_route
     * @return void
     */
    public function start( array $route, array &$current_route=array() ) : void {
        @session_start();
        if(
            empty( $_SERVER[ 'HTTP_REFERER' ] ) &&
            ! empty( $_SESSION[ '_referrer' ] )
        ) $_SERVER[ 'HTTP_REFERER' ] = $_SESSION[ '_referrer' ];
        if(
            $route[ 'method' ] === 'POST' &&
            isset( $_POST ) &&
            empty( $_POST )
        ) {
            $_POST = json_decode( file_get_contents( 'php://input' ), true );
        }
        $temp_args = array();
        if( in_array( $route[ 'domain' ] . ":::" . $route[ 'uri' ], array_keys( $this->routes ) ) ) {
            $temp_match = $this->routes[ $route[ 'domain' ] . ":::" . $route[ 'uri' ] ];
        } else {
            $temp_match = $this->match( $route );
            if( empty( $temp_match ) ) {
                //@TODO raise error
                echo 'raise error';
                exit;
            }
            $temp_args = $temp_match[ 'args' ];
            $temp_match = $temp_match[ 'route' ];
        }
        if( empty( $temp_match ) ) {
            //@TODO raise error
            echo 'raise error';
        } else {
            if( ! in_array( $route[ 'method' ], $temp_match[ 'method' ] ) ) {
                //@TODO raise error
                echo 'raise error';
                exit;
            }
            $security_check = $this->check_security( $temp_match );
            if( ! $security_check ) {
                //@TODO raise error no rights
                echo 'raise error no rights';
                exit;
            }
            $current_route = $temp_match;
            $method_name = $temp_match[ 'callback' ]->name;
            $named_args = array_filter( $temp_args, fn( $x ) => is_string( $x ), ARRAY_FILTER_USE_KEY );
            if( ! empty( $named_args ) ) {
                ( new $temp_match[ 'callback' ]->class )->$method_name( ...$named_args );
            } else {
                if( empty( $temp_args ) ) {
                    ( new $temp_match[ 'callback' ]->class )->$method_name();
                } else {
                    ( new $temp_match[ 'callback' ]->class )->$method_name( ...$temp_args );
                }
            }
            $_SESSION[ '_referrer' ] = get_url( '+' );
            if( ! empty( $temp_match[ 'template' ] ) ) load_template( $temp_match[ 'template' ] );
        }
    }

}

// Silence is golden