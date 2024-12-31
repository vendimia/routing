<?php
namespace Vendimia\Routing\MethodRoute;

interface MethodRouteInterface
{
    /**
     * Returns this MethodRoute HTTP Method
     */
    public function getHttpMethods(): ?array;

    /**
     * Returns a Rule built with this method's information.
     */
    public function getRule();
}
