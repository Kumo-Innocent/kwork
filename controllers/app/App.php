<?php

namespace controllers\app;

use src\classes\Route;
use src\classes\Template;

class App {

    #[Template('index')]
    #[Route('/', security: array())]
    public function appHome() : void {
        echo "Example of route !!";
    }

    #[Template('index')]
    #[Route('/test', security: array())]
    public function appTest() : void {
        echo "This is the second example of route for the APP.";
    }

}