<?php
namespace Vendimia\Routing\MethodRoute;

use Attribute;

#[Attribute]
class Put extends MethodRouteAbstract
{
    public function getHttpMethod()
    {
        return 'PUT';
    }
}