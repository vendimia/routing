<?php
namespace Vendimia\Routing\MethodRoute;

use Vendimia\Routing\Rule;

abstract class MethodRouteAbstract implements MethodRouteInterface
{
    /** Target is always a Controller */
    private $target = [];

    public function __construct(
        private string $path = '',
        private ?string $name = null,
    )
    {

    }

    public function setTarget(...$args)
    {
        $this->target = $args;
    }

    public function getRule()
    {
        return new Rule(
            methods: $this->getHttpMethods(),
            path: $this->path,
            name: $this->name,
            target: $this->target,
        );
    }
}
