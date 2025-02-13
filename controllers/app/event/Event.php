<?php

namespace controllers\app\event;

use src\classes\Route;
use src\classes\Template;

class Event {

    #[Template('index')]
    #[Route(
        array(
            '/get',
            '/get/(?P<event_id>(\d+))'
        ),
        security: array()
    )]
    public function homeEvent( ?int $event_id=null ) : void {
        if( ! $event_id ) {
            echo "Please provide event_id, example : /app/event/get/56.";
        } else {
            echo "This is the event id passed : $event_id";
        }
    }

}