<?php
namespace Vendimia\Routing\MethodRoute;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class Patch extends MethodRouteAbstract
{
    public function getHttpMethods(): ?array
    {
        return ['PATCH'];
    }
}
