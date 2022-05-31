<?php

namespace Vendimia\Routing;

use Stringable;
use Vendimia\DataContainer\DataContainer;

class MatchedRoute extends DataContainer implements Stringable
{
    /**
     * Route rule name, if exists
     */
    public $name = '';

    /**
     * Matched rule information
     */
    public $rule = [];

    /**
     * Arguments from matched rule and URL
     */
    public $args = [];

    /**
     * Target type: class, callable, view
     */
    public $target_type;

    /**
     * Target information
     */
    public $target;

    /**
     * Returns the matched rule representation
     */
    public function __toString(): string
    {
        if (!$this->rule) {
            return '';
        }

        if ($this->rule['methods']) {
            $methods = join(',', $this->rule['methods']);
        } else {
            $methods = 'ANY';
        }
        $parts = [
            $methods,
            "'" . $this->rule['path'] . "'",
            '🡢',
            strtoupper($this->rule['target_type']),
            is_array($this->rule['target']) ? join('::', $this->rule['target']) : '??',
        ];

        if ($this->rule['name']) {
            $parts[] = "({$this->rule['name']})";
        }

        return join(' ', $parts);
    }
}
