<?php

namespace App\Controllers;

use App\traits\DatabaseErrorHandler;

/**
 * This is the base controller for your Leaf MVC Project.
 * You can initialize packages or define methods here to use
 * them across all your other controllers which extend this one.
 */
class Controller extends \Leaf\Controller {
    use DatabaseErrorHandler;

}
