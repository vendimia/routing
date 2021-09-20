<?php
namespace Vendimia\Routing\MethodRoute;

use Attribute;

#[Attribute]
class Patch extends MethodRouteAbstract
{
    public function getHttpMethod()
    {
        return 'PATCH';
    }
}