<?php
namespace Vendimia\Routing;

use Vendimia\DataContainer\DataContainer;

class MatchedRoute extends DataContainer
{
    /**
     * Route rule name, if exists
     */
    public $name = '';

    /**
     * Arguments from matched rule and URL
     */
    public $args = [];

    /**
     * Target type: class, callable, view
     */
    public $target_type;

    /**
     * Target information
     */
    public $target;
}
