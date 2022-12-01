<?php
namespace Vendimia\Routing;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Raw routing rules manager.
 *
 * @author Oliver Etchebarne <yo@drmad.org>
 */
class Manager
{
    private array $rules = [];
    private array $properties = [];
    private array $default_rule = [];

    /**
     * Sets the rules from an array pf Rule objects
     */
    public function setRules(array $rules)
    {
        foreach ($rules as $raw_rule) {
            foreach ($raw_rule->getProcessedData() as $rule) {
                if ($rule['property']) {
                    $this->properties = array_merge(
                        $this->properties,
                        $rule['property']
                    );
                    continue;
                }
                $this->rules[] = $rule;
            }
        }
    }

    /**
     * Return the rules
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * Replace $source variables with $args
     */
    private function replaceVariables(array|string $source, array $args)
    {
        $vars = [];
        foreach ($args as $key => $value) {
            $vars['{' . $key . '}'] = $value;
        }

        if (is_array($source)) {
            return array_map(fn($e) => strtr($e, $vars), $source);
        } else {
            return strtr($source, $vars);
        }
    }

    /**
     * Match a request against the rule list
     */
    public function match(ServerRequestInterface $request): ?MatchedRoute
    {
        $http_method = $request->getMethod();
        $hostname = $request->getHeaderLine('Host');
        $ajax = $request->getHeaderLine('X-Requested-With');
        $path = trim($request->getUri()->getDecodedPath(), ' /');

        // Analizamos las rutas
        foreach ($this->rules as $rule) {
            if ($rule['methods'] && !in_array($http_method, $rule['methods'])) {
                continue;
            }
            if ($rule['hostname'] && $rule['hostname'] != $hostname) {
                continue;
            }
            if ($rule['ajax'] && !$ajax) {
                continue;
            }

            if (preg_match($rule['regexp'], $path, $args, PREG_UNMATCHED_AS_NULL)) {
                // Good

                // Nos deshacemos de los $args de índice no numéricos
                $args = array_filter($args, 'is_string', ARRAY_FILTER_USE_KEY)
                    + $rule['args'];


                $target = $rule['target'];

                // Si el target es un string,y hay variables para reemplazar,
                // las reemplazamos
                if (is_string($rule['target']) && $args ) {
                    $target = $this->replaceVariables($rule['target'], $args);
                }

                return new MatchedRoute(
                    name: $rule['name'],
                    rule: $rule,
                    target_type: $rule['target_type'],
                    target:$target,
                    args: $args,
                );
            }
        }

        return null;
    }
}
