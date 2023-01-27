<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utilities;

use App\Entity\Deposit;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

/**
 * Description of PingResult.
 */
class PingResult
{
    /**
     * HTTP request response.
     */
    private ?ResponseInterface $response;

    /**
     * Content of the response body.
     */
    private string $content;

    /**
     * Parsed XML from the response.
     */
    private ?SimpleXMLElement $xml = null;

    /**
     * Error from parsing the XML response.
     * @var array|string[]
     */
    private array $errors;

    /**
     * Construct a ping result from an HTTP request.
     */
    public function __construct(ResponseInterface $response = null, string $errors = null)
    {
        $this->response = $response;
        if ($response) {
            $this->content = $response->getBody()->getContents();
        } else {
            $this->content = '';
        }
        $this->errors = [];
        if ($errors) {
            $this->errors[] = $errors;
        }
        $this->xml = null;

        if ($response) {
            $oldErrors = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($this->content);
            if (false === $xml) {
                foreach (libxml_get_errors() as $error) {
                    $this->errors[] = "{$error->line}:{$error->column}:{$error->code}:{$error->message}";
                }
            } else {
                $this->xml = $xml;
            }
            libxml_use_internal_errors($oldErrors);
        }
    }

    /**
     * Get the HTTP response status.
     */
    public function getHttpStatus(): int
    {
        if ($this->response) {
            return $this->response->getStatusCode();
        }

        return 500;
    }

    /**
     * Return true if the request generated an error.
     */
    public function hasError(): bool
    {
        return \count($this->errors) > 0;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * Get the XML processing error.
     */
    public function getError(): string
    {
        return implode("\n", $this->errors);
    }

    /**
     * Get the response body.
     *
     * Optionally strips out the tags.
     */
    public function getBody(bool $stripTags = true): ?string
    {
        if (! $this->content) {
            return null;
        }
        if ($stripTags) {
            return strip_tags($this->content);
        }

        return $this->content;
    }

    /**
     * Check if the http response was XML.
     */
    public function hasXml(): bool
    {
        return (bool) $this->xml;
    }

    /**
     * Get the response XML.
     */
    public function getXml(): ?SimpleXMLElement
    {
        return $this->xml;
    }

    /**
     * Get an HTTP header.
     * @return string[]
     */
    public function getHeader(string $name): array
    {
        return $this->response?->getHeader($name) ?? [];
    }

    /**
     * Get the release version.
     */
    public function getApplicationVersion(): ?string
    {
        if (! $this->xml) {
            return null;
        }

        return Xpath::getXmlValue($this->xml, '//ojsInfo/release', Deposit::DEFAULT_JOURNAL_VERSION);
    }

    /**
     * Get the plugin release version.
     */
    public function getPluginReleaseVersion(): ?string
    {
        if (! $this->xml) {
            return null;
        }

        return Xpath::getXmlValue($this->xml, '//pluginInfo/release');
    }

    /**
     * Get the plugin release date.
     */
    public function getPluginReleaseDate(): ?string
    {
        if (! $this->xml) {
            return null;
        }

        return Xpath::getXmlValue($this->xml, '//pluginInfo/releaseDate');
    }

    /**
     * Check if the plugin thinks its current.
     */
    public function isPluginCurrent(): ?string
    {
        if (! $this->xml) {
            return null;
        }

        return Xpath::getXmlValue($this->xml, '//pluginInfo/current');
    }

    /**
     * Check if the terms of use have been accepted.
     */
    public function areTermsAccepted(): ?string
    {
        if (! $this->xml) {
            return null;
        }

        return Xpath::getXmlValue($this->xml, '//terms/@termsAccepted');
    }

    /**
     * Get the journal title from the response.
     */
    public function getJournalTitle(?string $default = null): ?string
    {
        if (! $this->xml) {
            return null;
        }

        return Xpath::getXmlValue($this->xml, '//journalInfo/title', $default);
    }

    /**
     * Get the number of articles the journal has published.
     */
    public function getArticleCount(): ?int
    {
        if (! $this->xml) {
            return null;
        }

        return (int) Xpath::getXmlValue($this->xml, '//articles/@count');
    }

    /**
     * Get a list of article titles reported in the response.
     *
     * @return array<array{date: string, title: string}>
     *                 Array of associative array data.
     */
    public function getArticleTitles(): array
    {
        if (! $this->xml) {
            return [];
        }
        $articles = [];
        $nodes = Xpath::query($this->xml, '//articles/article');
        \assert(is_iterable($nodes));
        foreach ($nodes as $node) {
            $articles[] = [
                'date' => (string) $node['pubDate'],
                'title' => trim((string) $node),
            ];
        }

        return $articles;
    }
}
