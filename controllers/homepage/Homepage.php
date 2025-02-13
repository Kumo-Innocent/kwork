<?php

namespace controllers\homepage;

use src\classes\Route;
use src\classes\Template;

class Homepage {

    #[Template('index')]
    #[Route('/', security: array())]
    public function home() : void {
        global $hooks_engine;
        $hooks_engine->add_hook( 'impresive-text', 'Ceci est un test impressionnant.' );
    }

}