<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utilities;

use SimpleXMLElement;

/**
 * Simplify handling namespaces for SWORD XML documents.
 */
class Namespaces
{
    public const NS = [
        'dcterms' => 'http://purl.org/dc/terms/',
        'sword' => 'http://purl.org/net/sword/',
        'atom' => 'http://www.w3.org/2005/Atom',
        'lom' => 'http://lockssomatic.info/SWORD2',
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'app' => 'http://www.w3.org/2007/app',
    ];

    /**
     * Get the FQDN for the prefix, in a case-insensitive fashion.
     */
    public static function getNamespace(?string $prefix): ?string
    {
        return self::NS[$prefix] ?? null;
    }

    /**
     * Register all the known namespaces in a SimpleXMLElement.
     */
    public static function registerNamespaces(SimpleXMLElement $xml): void
    {
        foreach (array_keys(self::NS) as $key) {
            $xml->registerXPathNamespace($key, self::NS[$key]);
        }
    }
}