<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utilities;

use DOMDocument;
use Exception;

/**
 * Wrapper around some XML parsing.
 */
class XmlParser
{
    /**
     * Options passed to LibXML.
     */
    public const LIBXML_OPTS = \LIBXML_COMPACT | \LIBXML_PARSEHUGE;

    /**
     * Block size for streaming.
     */
    public const BLOCKSIZE = 64 * 1024;

    /**
     * List of errors in parsing.
     * @var string[]
     */
    private array $errors;

    /**
     * Build the parser.
     */
    public function __construct()
    {
        $this->errors = [];
    }

    /**
     * Check if the parse generated errors.
     */
    public function hasErrors(): bool
    {
        return \count($this->errors) > 0;
    }

    /**
     * Filter out any invalid UTF-8 data in $from and write the result to $to.
     */
    public function filter(string $from, string $to): int
    {
        $fromHandle = fopen($from, 'r') ?: throw new Exception("Failed to open '{$from}'");
        $toHandle = fopen($to, 'w') ?: throw new Exception("Failed to open '{$to}'");
        $changes = 0;
        while ($buffer = fread($fromHandle, self::BLOCKSIZE)) {
            $filtered = iconv('UTF-8', 'UTF-8//IGNORE', $buffer);
            if (false === $filtered) {
                throw new Exception('Failed to convert text to UTF-8');
            }
            $changes += (\strlen($buffer) - \strlen($filtered));
            fwrite($toHandle, $filtered);
        }

        return $changes;
    }

    /**
     * Load the XML document into a DOM and return it.
     * Errors are appended to the $report parameter.
     * The export may contain
     * invalid UTF-8 characters. If the file cannot be parsed as XML, the
     * function will attempt to filter out invalid UTF-8 characters and then
     * try to load the XML again.
     * Other errors in the XML, beyond the bad UTF-8, will not be tolerated.
     *
     * @throws Exception
     */
    public function fromFile(string $filename): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $originalResult = $dom->load($filename, self::LIBXML_OPTS);
        if (true === $originalResult) {
            return $dom;
        }
        $error = libxml_get_last_error();
        if ($error && false === strpos($error->message, 'Input is not proper UTF-8')) {
            throw new Exception("{$error->message} at {$error->file}:{$error->line}:{$error->column}.");
        }
        $filteredFilename = tempnam(sys_get_temp_dir(), 'pn-') ?: throw new Exception('Failed to acquire temporary file');
        $changes = $this->filter($filename, $filteredFilename);
        $this->errors[] = basename($filename) . " contains {$changes} invalid "
        . 'UTF-8 characters, which have been removed.';
        $filteredResult = $dom->load($filteredFilename, self::LIBXML_OPTS);
        if (true === $filteredResult) {
            return $dom;
        }

        $filteredError = ($error = libxml_get_last_error()) ? " {$error->message} at "
            . "{$error->file}:{$error->line}:{$error->column}." : '';
        throw new Exception("Filtered XML cannot be parsed.{$filteredError}");
    }

    /**
     * Attempts to fix mal-formed XML content and drop trailing data
     */
    public static function cleanXml(string $xml): string
    {
        if (\function_exists('tidy_repair_string')) {
            return tidy_repair_string($xml, ['input-xml' => 1, 'output-xml' => 1, 'wrap' => 0, 'add-xml-decl' => 1, 'input-encoding' => 'utf8', 'output-encoding' => 'utf8']) ?: $xml;
        }
        $document = new DOMDocument();
        $document->strictErrorChecking = false;
        $document->recover = true;
        $document->loadXml($xml, \LIBXML_HTML_NODEFDTD | \LIBXML_HTML_NOIMPLIED);
        return $document->saveXml() ?: $xml;
    }
}
