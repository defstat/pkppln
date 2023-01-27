<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utilities;

use App\Utilities\XmlParser;
use Exception;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

/**
 * Description of XpathTest.
 */
class XmlParserTest extends TestCase
{
    /**
     * @var XmlParser
     */
    private $parser;

    /**
     * @var vfsStreamDirectory
     */
    private $root;

    /**
     * @dataProvider badUtf8Data
     *
     * @param mixed $expected
     * @param mixed $data
     */
    public function testFilter($expected, $data): void
    {
        $sourceFile = vfsStream::newFile('bad.xml')->withContent($data)->at($this->root);
        $destFile = vfsStream::newFile('filtered.xml')->at($this->root);
        $this->assertSame($expected, $this->parser->filter($sourceFile->url(), $destFile->url()));
    }

    /**
     * @dataProvider badUtf8Data
     *
     * @param mixed $removed
     * @param mixed $data
     */
    public function testFromFile($removed, $data): void
    {
        $sourceFile = vfsStream::newFile('bad.xml')
            ->withContent("<a>{$data}</a>")
            ->at($this->root)
        ;
        $dom = $this->parser->fromFile($sourceFile->url());
        $this->assertNotNull($dom);
        if ($removed) {
            $this->assertTrue($this->parser->hasErrors());
        } else {
            $this->assertFalse($this->parser->hasErrors());
        }
    }

    public function testInvalidXml(): void
    {
        $this->expectException(Exception::class);
        $sourceFile = vfsStream::newFile('bad.xml')
            ->withContent('<a>chicanery</b>')
            ->at($this->root)
        ;
        $dom = $this->parser->fromFile($sourceFile->url());
        $this->fail();
    }

    public function testInvalidXmlAndUtf8(): void
    {
        $this->expectException(Exception::class);
        $sourceFile = vfsStream::newFile('bad.xml')
            ->withContent("<a>chic\xc3\x28nery</b>")
            ->at($this->root)
        ;
        $dom = $this->parser->fromFile($sourceFile->url());
        $this->fail();
    }

    /**
     * Yes, that's really a valid 6 octet sequence that isn't unicode.
     * Yes, we've really seen stuff like this.
     */
    public function testSuperInvalidUtf8(): void
    {
        $this->expectException(Exception::class);
        $sourceFile = vfsStream::newFile('bad.xml')
            ->withContent("<a>chic\xfc\xa1\xa1\xa1\xa1\xa1nery</a>")
            ->at($this->root)
        ;
        $dom = $this->parser->fromFile($sourceFile->url());
        $this->fail();
    }

    /**
     * @see https://stackoverflow.com/a/3886015/9316
     */
    public function badUtf8Data()
    {
        return [
            [0, 'Valid ASCII a'],
            [0, "Valid 2 Octet Sequence \xc3\xb1"],
            [1, "Invalid 2 Octet Sequence \xc3\x28"],
            [2, "Invalid Sequence Identifier \xa0\xa1"],
            [0, "Valid 3 Octet Sequence \xe2\x82\xa1"],
            [2, "Invalid 3 Octet Sequence (in 2nd Octet) \xe2\x28\xa1"],
            [2, "Invalid 3 Octet Sequence (in 3rd Octet) \xe2\x82\x28"],
            [0, "Valid 4 Octet Sequence \xf0\x90\x8c\xbc"],
            [3, "Invalid 4 Octet Sequence (in 2nd Octet) \xf0\x28\x8c\xbc"],
            [3, "Invalid 4 Octet Sequence (in 3rd Octet) \xf0\x90\x28\xbc"],
            [2, "Invalid 4 Octet Sequence (in 4th Octet) \xf0\x28\x8c\x28"],
        ];
    }

    public function testMalformedXml(): void
    {
        $xml = <<<'ENDXML'
        <?xml bad prolog?>
        trailing data
        <test>
            <element bad escaped="attribute""></element>
            Invalid character ahead < which could be a bad tag>
        </test>
        more trailing data
ENDXML;
        $expected = <<<'ENDXML'
<?xml version="1.0"?>
<test>
<element bad="" escaped="attribute"/>Invalid character ahead &lt; which could be a bad tag&gt;</test>

ENDXML;
        $cleanXml = $this->parser->cleanXml($xml);
        $xml = simplexml_load_string($cleanXml);
        $this->assertNotFalse($xml);
        $this->assertEquals($expected, $xml->saveXML());
    }

    protected function setup(): void
    {
        parent::setUp();
        $this->parser = new XmlParser();
        $this->root = vfsStream::setup();
    }
}
