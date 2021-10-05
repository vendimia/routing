<?php
namespace Vendimia\Routing\MethodRoute;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class Post extends MethodRouteAbstract
{
    public function getHttpMethod()
    {
        return 'POST';
    }
}