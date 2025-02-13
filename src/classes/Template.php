<?php

namespace src\classes;

#[\Attribute]
class Template {

    private string $template_path = '';
    private array $templates = array();
    public string $to_render = '';

    public function __construct( string $path ) {
        $this->to_render = $path;
    }

    /**
     * Set template engine main directory to scan
     *
     * @param string $directory
     * @return Template
     */
    public function set_directory( string $directory ) : Template {
        $this->template_path = $directory;
        return $this;
    }

    /**
     * Scan all templates from the templatePath directory (/templates)
     *
     * @param string|null $directory
     * @return Template
     */
    public function scan_templates( string $directory=null ) : Template {
        if( $directory === null ) $directory = $this->template_path;
        if(
            empty( $directory ) ||
            ! is_dir( $directory )
        ) return $this;
        $files = array_map(
            fn( $x ) => "$directory/$x",
            array_diff( scandir( $directory ), array( '.', '..' ) )
        );
        foreach( $files as $file ) {
            if( is_dir( $file ) ) {
                $this->scan_templates( $file );
            } else if( file_exists( $file ) && is_file( $file ) ) {
                if( dirname( $file ) === $this->template_path ) {
                    $this->templates[ pathinfo( $file, PATHINFO_FILENAME ) ] = $file;
                } else {
                    $dirname = str_replace(
                        '/',
                        '-',
                        trim( str_replace( $this->template_path, '', dirname( $file ) ), '/' )
                    );
                    $this->templates[ "$dirname-" . pathinfo( $file, PATHINFO_FILENAME ) ] = $file;
                }
            }
        }
        return $this;
    }

    /**
     * Page when no templates implemented yet
     *
     * @return void
     */
    private function no_templates() : void {
        echo "No templates implemented yet. Create one to continue.";
    }

    /**
     * Load template from path name if exists
     *
     * @param string $path
     * @param bool $once
     * @return void
     */
    public function load_template( string $path, bool $once=true ) : void {
        if( in_array( $path, array_keys( $this->templates ) ) ) {
            if( $once ) require_once $this->templates[ $path ];
            else require $this->templates[ $path ];
        } else {
            $this->no_templates();
        }
    }

}