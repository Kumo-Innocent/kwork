<?php

namespace src\classes;

class Cache {

    private string $dirname;
    private int $duration_minutes;
    private mixed $buffer;

    public function __construct( string $dirname, int $duration_minutes ) {
        $this->dirname = $dirname;
        $this->duration_minutes = $duration_minutes;
        return $this;
    }

    /**
     * Write cache to file at Cache folder
     *
     * @param string $filename
     * @param mixed $content
     * @return boolean
     */
    public function write( string $filename, mixed $content ) : bool {
        if( ! is_dir( $this->dirname ) ) mkdir( $this->dirname );
        return file_put_contents( $this->dirname . '/' . $filename, is_array( $content ) ? serialize( $content ) : $content );
    }

    /**
     * Read cache file from Cache folder
     *
     * @param string $filename
     * @return string|null
     */
    public function read( string $filename ) : ?string {
        $file = $this->dirname . '/' . $filename;
        if( ! file_exists( $file ) ) return null;
        $lifetime = ( time() - filemtime( $file ) ) / 60;
        if( $lifetime > $this->duration_minutes ) {
            $this->delete( $filename );
            return null;
        }
        return file_get_contents( $file );
    }

    /**
     * Delete cache file from Cache folder
     *
     * @param string $filename
     * @return void
     */
    public function delete( string $filename ) : void {
        $file = $this->dirname . '/' . $filename;
        if( file_exists( $file ) ) unlink( $file );
    }

    /**
     * Delete all cached files
     *
     * @return void
     */
    public function clear() : void {
        $files = glob( $this->dirname . '/*' );
        foreach( $files as $file ) {
            if( str_contains( $file, '-no-delete' ) ) continue;
            unlink( $file );
        }
    }

    /**
     * Include specific file to the Cache
     *
     * @param string $file
     * @param string|null $cachename
     * @return bool
     */
    public function include( string $file, ?string $cachename=null ) : bool {
        if( ! file_exists( $file ) ) return false;
        if( ! $cachename ) $cachename = basename( $file );
        if( $content = $this->read( $cachename ) ) {
            echo $content;
            return true;
        }
        ob_start();
        include $file;
        $content = ob_get_clean();
        $this->write( $cachename, $content );
        echo $content;
        return true;
    }

    /**
     * Start recording Cache file
     *
     * @param string $cachename
     * @param boolean $show
     * @return void
     */
    public function start( string $cachename, bool $show=true ) : void {
        if( $content = $this->read( $cachename ) ) {
            if( $show ) echo $content;
            $this->buffer = false;
            return;
        }
        ob_start();
        $this->buffer = $cachename;
    }

    /**
     * Stop recording Cache file
     *
     * @return void
     */
    public function end() : void {
        if( ! isset( $this->buffer ) ) return;
        $content = ob_get_clean();
        echo $content;
        $this->write( $this->buffer, $content );
    }

}