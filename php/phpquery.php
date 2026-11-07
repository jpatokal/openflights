<?php

/**
 * Minimal, PHP 8-compatible replacement for the tiny subset of phpQuery that
 * OpenFlights' import feature relies on.
 *
 * phpQuery (rubobaquero/phpquery 0.9.15) is an unmaintained ~2008-era library
 * and is not safe on PHP 8. The import code only needs:
 *
 *   - phpQuery::newDocumentFileHTML($file, $encoding)
 *   - pq($selector_or_node)
 *   - ->elements, ->text(), ->find($selector), ->attr($name)
 *   - array-style access: $obj['selector']
 *
 * Everything here is implemented on top of the standard DOMDocument / DOMXPath
 * extensions.
 */

class PhpQueryObject implements ArrayAccess
{
    /** @var DOMNode[] */
    public $elements = [];

    /** @param DOMNode[] $elements */
    public function __construct(array $elements = [])
    {
        $this->elements = $elements;
    }

    /**
     * Concatenated text content of all matched nodes.
     *
     * @return string
     */
    public function text()
    {
        $out = '';
        foreach ($this->elements as $node) {
            $out .= $node->textContent;
        }
        return $out;
    }

    /**
     * Find descendant (or child, when the selector starts with ">") nodes that
     * match a CSS selector, relative to the current matched set.
     *
     * @param string $selector
     * @return PhpQueryObject
     */
    public function find($selector)
    {
        $xpath = $this->toXpath($selector);
        $results = [];

        foreach ($this->elements as $context) {
            $doc = $context instanceof DOMDocument
                ? $context
                : $context->ownerDocument;
            if (!$doc) {
                continue;
            }

            $domXpath = new DOMXPath($doc);
            $nodes = @$domXpath->query($xpath, $context);
            if ($nodes === false) {
                continue;
            }

            foreach ($nodes as $node) {
                $results[] = $node;
            }
        }

        return new self($results);
    }

    /**
     * Return the named attribute from the first matched element.
     *
     * @param string $name
     * @return string|null
     */
    public function attr($name)
    {
        foreach ($this->elements as $node) {
            if ($node instanceof DOMElement && $node->hasAttribute($name)) {
                return $node->getAttribute($name);
            }
        }
        return null;
    }

    // ---- ArrayAccess -------------------------------------------------------
    //
    // The parameters are deliberately untyped, and offsetGet() has no return
    // type. The `mixed` type only exists from PHP 8.0; on PHP 7.x it is read
    // as a class name, and the class declaration fails with a fatal
    // "must be compatible with ArrayAccess" error the moment this file is
    // loaded. That broke every upload to php/import.php with an HTTP 500 on a
    // PHP 7 runtime (https://github.com/jpatokal/openflights/issues/1539).
    // #[\ReturnTypeWillChange] silences the PHP 8.1+ deprecation notice and is
    // an ordinary comment on PHP 7.

    public function offsetExists($offset): bool
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->find((string) $offset);
    }

    public function offsetSet($offset, $value): void
    {
        // Read-only subset; not used.
    }

    public function offsetUnset($offset): void
    {
        // Read-only subset; not used.
    }

    // ---- CSS selector subset -> XPath --------------------------------------

    /**
     * Convert a (comma-separated) CSS selector to an XPath expression that is
     * relative to a context node.
     *
     * @param string $selector
     * @return string
     */
    private function toXpath($selector)
    {
        $parts = array_map('trim', explode(',', $selector));
        $xpaths = array_map([$this, 'selectorToXpath'], $parts);
        return implode(' | ', $xpaths);
    }

    /**
     * @param string $selector
     * @return string
     */
    private function selectorToXpath($selector)
    {
        $selector = trim($selector);

        // Leading ">" means "direct child"; otherwise "descendant".
        $firstSeparator = '//';
        if (isset($selector[0]) && $selector[0] === '>') {
            $firstSeparator = '/';
            $selector = trim(substr($selector, 1));
        }

        $parts = [];
        $combinators = [];
        $buffer = '';
        $length = strlen($selector);

        for ($i = 0; $i < $length; $i++) {
            $char = $selector[$i];

            if ($char === '>') {
                if (trim($buffer) !== '') {
                    $parts[] = trim($buffer);
                    $buffer = '';
                }
                $combinators[] = '>';
                while ($i + 1 < $length && ctype_space($selector[$i + 1])) {
                    $i++;
                }
            } elseif (ctype_space($char)) {
                if (trim($buffer) !== '') {
                    $parts[] = trim($buffer);
                    $buffer = '';
                }
                $combinators[] = ' ';
                while ($i + 1 < $length && ctype_space($selector[$i + 1])) {
                    $i++;
                }
            } else {
                $buffer .= $char;
            }
        }
        if (trim($buffer) !== '') {
            $parts[] = trim($buffer);
        }

        if (empty($parts)) {
            return './/*';
        }

        $xpath = '.' . $firstSeparator . $this->simpleToXpath($parts[0]);
        for ($j = 1, $n = count($parts); $j < $n; $j++) {
            $separator = (isset($combinators[$j - 1]) && $combinators[$j - 1] === '>')
                ? '/'
                : '//';
            $xpath .= $separator . $this->simpleToXpath($parts[$j]);
        }

        return $xpath;
    }

    /**
     * Convert a single compound selector (tag + [attr=value] predicates) to an
     * XPath location step.
     *
     * @param string $simple
     * @return string
     */
    private function simpleToXpath($simple)
    {
        $simple = trim($simple);
        $attrs = [];

        if (preg_match_all('/\[([a-zA-Z0-9_:.-]+)(?:=([^\]]*))?\]/', $simple, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attrs[$match[1]] = array_key_exists(2, $match) ? $match[2] : null;
            }
            $simple = trim(preg_replace('/\[[^\]]*\]/', '', $simple));
        }

        $tag = ($simple === '' || $simple === '*') ? '*' : $simple;
        $xpath = $tag;

        foreach ($attrs as $name => $value) {
            if ($value === null) {
                $xpath .= "[@$name]";
            } else {
                $xpath .= "[@$name='" . addslashes($value) . "']";
            }
        }

        return $xpath;
    }
}

class phpQuery
{
    /** @var DOMDocument|null Last document loaded by newDocumentFileHTML(). */
    private static $document = null;

    /**
     * Load an HTML file into a queryable document.
     *
     * @param string $file
     * @param string|null $encoding Source encoding (e.g. "ISO-8859-1").
     * @return PhpQueryObject
     */
    public static function newDocumentFileHTML($file, $encoding = null)
    {
        $html = @file_get_contents($file);
        if ($html === false) {
            $html = '<html><body></body></html>';
        } elseif ($encoding !== null && strcasecmp($encoding, 'UTF-8') !== 0) {
            if (function_exists('mb_convert_encoding')) {
                $html = mb_convert_encoding($html, 'UTF-8', $encoding);
            }
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // Force libxml to treat the (converted) string as UTF-8.
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        self::$document = $doc;
        return new PhpQueryObject([$doc]);
    }

    /**
     * @return DOMDocument|null
     */
    public static function getDocument()
    {
        return self::$document;
    }
}

/**
 * phpQuery-compatible entry point.
 *
 * @param DOMNode|string $arg A DOM node to wrap, or a CSS selector to query.
 * @return PhpQueryObject
 */
function pq($arg)
{
    if ($arg instanceof DOMNode) {
        return new PhpQueryObject([$arg]);
    }

    $doc = phpQuery::getDocument();
    if ($doc === null) {
        return new PhpQueryObject([]);
    }

    return (new PhpQueryObject([$doc]))->find((string) $arg);
}
