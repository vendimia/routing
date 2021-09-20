<?php
namespace Vendimia\Routing\MethodRoute;

use Attribute;

#[Attribute]
class Delete extends MethodRouteAbstract
{
    public function getHttpMethod()
    {
        return 'DELETE';
    }
}