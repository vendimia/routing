<?php
namespace Vendimia\Routing\MethodRoute;

use Attribute;

#[Attribute]
class Any extends MethodRouteAbstract
{
    public function getHttpMethod()
    {
        return '';
    }
}
