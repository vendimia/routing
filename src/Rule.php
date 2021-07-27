<?php
namespace Vendimia\Routing;

use Closure;

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
    const TARGET_CLASS = 'class';

    // Regexp for PHP identifier, from
    // https://www.php.net/manual/en/language.variables.basics.php
    private const PHP_IDENTIFIER = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*';

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
        'target_type' => self::TARGET_CLASS,

        // Extra arguments to the controller/callable
        'args' => [],
    ];

    // Include()d rules
    private $included_rules = [];

    /**
     * Sets the allowed HTTP methods for this rule.
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function method(string ...$methods)
    {
        $this->rule['methods'] = $methods;
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
     * Sets a target for this rule.
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function setTarget($target = null): self
    {
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
    public function name(str $name): self
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
    public function include($rules): self
    {
        if (is_array($rules)) {
            foreach ($rules as $rule) {
                $this->included_rules[] = $rule->combine($this);
            }
        }

        return $this;
    }

    /**
     * Merges this rule with another,
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function combine(Rule $rule): self
    {
        $this->rule['path'] = trim($rule->getPath() . '/' . $this->rule['path'], '/');

        // Tambien combinamos las rutas incluidas
        foreach ($this->included_rules as $included_rule) {
            $included_rule->combine($rule);
        }

        return $this;
    }

    /**
     * Returns the processed rule info
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function getProcessedData(): array
    {
        // Las rutas que incluyen otras no son parte de la tabla de ruteo
        if ($this->included_rules) {
            $return = [];
            foreach ($this->included_rules as $rule) {
                $return[] = $rule->getProcessedData()[0]; // Removemos el array
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
        if (preg_match_all('/\{(\*?' . self::PHP_IDENTIFIER . '?)\}/', $path, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $variable = $match[1][0];

                if ($variable[0] == '*') {
                    // Esta es una variable catch-all
                    $variable = substr($variable, 1);
                    $preg_subpat = "(?<{$variable}>.+?)";
                } else {
                    $preg_subpat = "(?<{$variable}>[^/]+?)";
                }


                $start = $match[0][1] + $offset;
                $length = mb_strlen($match[0][0]);
                $path = mb_substr($path, 0, $start) .
                    $preg_subpat .
                    mb_substr($path, $start + $length);

                $offset += mb_strlen($preg_subpat) - mb_strlen($match[0][0]) - 1;
            }
        }

        $this->rule['regexp'] = "%^{$path}$%u";
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
}