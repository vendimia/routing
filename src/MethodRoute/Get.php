<?php
namespace Vendimia\Routing\MethodRoute;

use Attribute;

#[Attribute]
class Get extends MethodRouteAbstract
{
    public function getHttpMethod()
    {
        return 'GET';
    }
}