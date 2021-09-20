<?php
namespace Vendimia\Routing\MethodRoute;

use Attribute;

#[Attribute]
class Post extends MethodRouteAbstract
{
    public function getHttpMethod()
    {
        return 'POST';
    }
}