<?php
namespace Vendimia\Routing;

use Vendimia\Routing\MethodRoute\MethodRouteInterface;
use Vendimia\Interface\Path\ResourceLocatorInterface;

use Closure;
use ReflectionClass;
use ReflectionAttribute;
use InvalidArgumentException;

/**
 * Routing rule definition.
 *
 * @author Oliver Etchebarne <yo@drmad.org>
 */
class Rule
{
    const TARGET_VIEW = 'view';
    const TARGET_CONTROLLER = 'controller';
    const TARGET_CALLABLE = 'callable';

    // Regexp for PHP identifier, from
    // https://www.php.net/manual/en/language.variables.basics.php
    private const PHP_IDENTIFIER = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*';

    /** Optional ResourceLocatorInterface implementation */
    private static ?ResourceLocatorInterface $resource_locator = null;

    private $rule = [
        // Allowed request methods. Default any.
        'methods' => [],

        // Request hostname. Default any.
        'hostname' => null,

        // Should be an AJAX request? true = yes, false = no, null = don't care
        'ajax' => null,

        // Raw rule path
        'path' => '',

        // Generated regexp rule
        'regexp' => '',

        // Name of the rule or property.
        'name' => null,

        // Target definition: array for class, string or callable for callable or view
        'target' => null,

        // If this rule is a property, name => value of the property
        'property' => null,

        // Target type: view, callable, class
        'target_type' => self::TARGET_CONTROLLER,

        // Extra arguments to the controller/callable
        'args' => [],
    ];

    // Include()d rules
    private $included_rules = [];

    /**
     * Create a rule specifying the rule details
     */
    public function __construct(...$details)
    {
        $this->rule = array_merge($this->rule, $details);
    }

    /**
     * Sets the allowed HTTP methods for this rule.
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function method(string ...$methods)
    {
        $this->rule['methods'] = array_map('strtoupper', $methods);
        return $this;
    }

    /**
     * Alias of self::method
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function methods(string ...$methods): self
    {
        return $this->method(...$methods);
    }

    /**
     * Sets and preprocesses the path
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function setPath(?string $path): self
    {
        if ($path) {
            $this->rule['path'] = trim($path, '/');
        }

        return $this;
    }

    /**
     * Returns the route path
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     *
     */
    public function getPath(): string
    {
        return $this->rule['path'];
    }

    /**
     * Return the rule data
     */
    public function getRule(): array
    {
        return $this->rule;
    }

    /**
     * Sets a target for this rule.
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function setTarget($target): self
    {
        // Esto es sólo cuando no se añade un target en algún método estático.
        // Simplemente una facilidad para no repetirla en cada método.
        if (is_null($target)) {
            return $this;
        }

        if (is_array($target)) {
            // Es un controller
            $this->controller(...$target);
        } elseif ($target instanceof Closure) {
            // Es un closure
            $this->callable($target);
        } else {
            // Lo que debe quedar es un string. Asumimos que es un controller
            // con el método 'default'
            $this->controller($target);
        }

        return $this;
    }

    /**
     * Matches an AJAX connection
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function ajax(bool $active = true): self
    {
        $this->rule['ajax'] = $active;

        return $this;
    }

    /**
     * Sets this rule name
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function name(string $name): self
    {
        $this->rule['name'] = $name;

        return $this;
    }

    /**
     * Adds arguments to this rule
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function args(...$args): self
    {
        $this->rule['args'] = $args + $this->rule['args'];
        return $this;
    }

    /**
     * Sets the target to a controller class
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function controller(
        string $controller,
        string $method = 'default',
    ): self
    {
        $this->rule['target_type'] = self::TARGET_CONTROLLER;
        $this->rule['target'] = [$controller, $method];

        return $this;
    }

    /**
     * Sets the target to a callable function or method
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function callable(Callable $callable): self
    {
        $this->rule['target_type'] = self::TARGET_CALLABLE;
        $this->rule['target'] = $callable;

        return $this;
    }

    /**
     * Sets the target to a view
     */
    public function view(string $string): self
    {
        $this->rule['target_type'] = self::TARGET_VIEW;
        $this->rule['target'] = $string;

        return $this;
    }

    /**
     * Adds new rules using this rule path as prefix
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function include(string|array $rules): self
    {
        // Si es string, es un fichero.
        if (is_string($rules)) {
            $source = $rules;

            // Si hay definido un resource locator, lo usamos. De lo contrario,
            // el string apunta directo a un fichero.
            if (self::$resource_locator) {
                $source = self::$resource_locator->find($rules);
                if (is_null($source)) {
                    throw new InvalidArgumentException("Rule source '$rules' not found");
                }
            }
            $rules = require $source;
        }

        /*
        foreach ($rules as $rule) {
            $r = $this->included_rules[] = $rule->prependPathFromRule($this);
            var_dump($r->getPath());
        }*/

        // Grabamos las reglas sin modificar
        $this->included_rules = array_merge($this->included_rules, $rules);

        return $this;
    }

    /**
     * Include routing rules from this class' method attributes.
     */
    public function includeFromClass($class_name): self
    {
        $rules = [];
        $rc = new ReflectionClass($class_name);
        foreach ($rc->getMethods() as $rm) {
            $attrs = $rm->getAttributes(
                MethodRouteInterface::class,
                ReflectionAttribute::IS_INSTANCEOF
            );

            foreach ($attrs as $attr) {
                $rule = $attr->newInstance();
                $rule->setTarget($rm->class, $rm->name);
            }
            $rules[] = $rule->getRule();
        }
        $this->included_rules = array_merge($this->included_rules, $rules);

        return $this;
    }

    /**
     * Prepends a rule path to this path,
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function prependPathFromRule(Rule $rule): self
    {
        $this->rule['path'] = trim($rule->getPath() . '/' . $this->rule['path'], '/');

        return $this;
    }

    /**
     * Returns the processed rule info
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function getProcessedData(): array
    {
        // Las reglas que incluyen otras no son parte de la tabla de ruteo, solo
        // retornamos las reglas incluidas, ya procesadas
        if ($this->included_rules) {
            $return = [];
            foreach ($this->included_rules as $rule) {
                $rule->prependPathFromRule($this);
                $return = array_merge($return, $rule->getProcessedData()); // Removemos el array
            }
            return $return;
        } else {
            $this->buildRegexp();
            return [$this->rule];
        }
    }

    /**
     * Builds the Regexp from the path
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function buildRegexp()
    {
        $path = $this->rule['path'];

        // Reemplazamos la variables por subpatrones
        $offset = 0;
        $result = preg_match_all(
            '/\{(\*|\*?' . self::PHP_IDENTIFIER . '?)\}/',
            $path,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        if ($result) {
            foreach ($matches as $match) {
                // $match[1] tiene solo el contenido del subpatron
                $variable = $match[1][0];

                if ($variable[0] == '*') {
                    $variable = substr($variable, 1);
                    if ($variable) {
                        // Esta es una variable catch-all
                        $preg_subpat = "(?<{$variable}>.+?)";
                    } else {
                        // Si no hay variable, es un paréntesis que no captura
                        $preg_subpat = "(?:.+?)";
                    }
                } else {
                    $preg_subpat = "(?<{$variable}>[^/]+?)";
                }


                // $match[0] tiene todo el match completo, incluyendo los {}
                $start = $match[0][1] + $offset;
                $length = mb_strlen($match[0][0]);

                $path = mb_substr($path, 0, $start) .
                    $preg_subpat .
                    mb_substr($path, $start + $length);

                // PREG_OFFSET_CAPTURE devuelve el offset _en bytes_, no en
                // caracteres, a pesar que estamos usando el modificador Unicode.
                // Esta línea compensa por los bytes no contados en el offset.
                $offset -= strlen($preg_subpat) - mb_strlen($preg_subpat);

                // Añadimos la diferencia entre la variable, y el patron regexp.
                $offset += mb_strlen($preg_subpat) - mb_strlen($match[0][0]);
            }
        }

        $this->rule['regexp'] = "%^{$path}$%u";
    }

    /**
     * Sets the ResourceLocator object
     */
    public static function setResourceLocator(
        ResourceLocatorInterface $resource_locator
    )
    {
        self::$resource_locator = $resource_locator;
    }

    /**
     * Returns a new HTTP rule without method
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public static function path($path = null, $target = null): self
    {
        return (new self)->setPath($path)->setTarget($target);
    }

    /**
     * Returns a new HTTP rule without method, with an empty path, for the
     * default rule
     */
    public static function default($target = null): self
    {
        return (new self)->method('GET')->setPath('')->setTarget($target);
    }

    /**
     * Returns a new HTTP GET rule
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public static function get($path = null, $target = null): self
    {
        return (new self)->method('GET')->setPath($path)->setTarget($target);
    }

    /**
     * Returns a new HTTP POST rule
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public static function post($path = null, $target = null): self
    {
        return (new self)->method('POST')->setPath($path)->setTarget($target);
    }

    /**
     * Returns a new HTTP PUT rule
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public static function put($path = null, $target = null): self
    {
        return (new self)->method('PUT')->setPath($path)->setTarget($target);
    }

    /**
     * Returns a new HTTP PATCH rule
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public static function patch($path = null, $target = null): self
    {
        return (new self)->method('PATCH')->setPath($path)->setTarget($target);
    }

    /**
     * Returns a new HTTP DELETE rule
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public static function delete($path = null, $target = null): self
    {
        return (new self)->method('DELETE')->setPath($path)->setTarget($target);
    }

    /**
     * Returns a new HTTP OPTIONS rule
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public static function options($path = null, $target = null): self
    {
        return (new self)->method('OPTIONS')->setPath($path)->setTarget($target);
    }
}
