<?php
namespace Vendimia\Routing\MethodRoute;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class Delete extends MethodRouteAbstract
{
    public function getHttpMethods(): ?array
    {
        return ['DELETE'];
    }
}
