<?php
namespace Vendimia\Routing\MethodRoute;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class Get extends MethodRouteAbstract
{
    public function getHttpMethods(): ?array
    {
        return ['GET'];
    }
}
