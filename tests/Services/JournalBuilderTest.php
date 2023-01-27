<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Services;

use App\DataFixtures\JournalFixtures;
use App\Entity\Journal;
use App\Services\JournalBuilder;
use App\Utilities\Namespaces;
use App\Tests\TestCase\BaseControllerTestCase;
use DateTimeInterface;
use SimpleXMLElement;

/**
 * Description of JournalBuilderTest.
 */
class JournalBuilderTest extends BaseControllerTestCase
{
    private JournalBuilder $builder;
    private Journal $journal;

    public function fixtures(): array
    {
        return [
            JournalFixtures::class,
        ];
    }

    private function getXml(): array
    {
        $xml = <<<'ENDXML'
<?xml version="1.0" encoding="utf-8"?>
<entry xmlns="http://www.w3.org/2005/Atom" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:pkp="http://pkp.sfu.ca/SWORD">
    <email>user@example.com</email>
    <title>Intl J Test</title>
    <pkp:journal_url>http://example.com/ijt</pkp:journal_url>
    <pkp:publisherName>Publisher institution</pkp:publisherName>
    <pkp:publisherUrl>http://publisher.example.com</pkp:publisherUrl>
    <pkp:issn>0000-0000</pkp:issn>
    <id>urn:uuid:00FD6D96-0155-43A4-97F7-2C6EE8EBFF09</id>
    <updated>1996-12-31T16:00:00Z</updated>
    <pkp:content size="3613" volume="44" issue="4" pubdate="2015-07-14"
            checksumType="SHA-1" checksumValue="25b0bd51bb05c145672617fced484c9e71ec553b">
            http://ojs.dv/index.php/ijt/pn/deposits/00FD6D96-0155-43A4-97F7-2C6EE8EBFF09
        </pkp:content>
    <pkp:license>
            <pkp:openAccessPolicy>Yes.</pkp:openAccessPolicy>
            <pkp:licenseURL>http://creativecommons.org/licenses/by-nc-sa/4.0</pkp:licenseURL>
            <pkp:publishingMode mode="0">Open</pkp:publishingMode>
            <pkp:copyrightNotice>This is a copyright notice.</pkp:copyrightNotice>
            <pkp:copyrightBasis>article</pkp:copyrightBasis>
            <pkp:copyrightHolder>author</pkp:copyrightHolder>
    </pkp:license>
</entry>
ENDXML;
        $xml = simplexml_load_string($xml);
        Namespaces::registerNamespaces($xml);
        return [['B99FE131-48B5-440A-A552-4F1BF2BFDE82', $xml]];
    }

    public function testInstance(): void
    {
        $this->assertInstanceOf(JournalBuilder::class, self::$container->get(JournalBuilder::class));
    }

    /**
     * @dataProvider getXml
     */
    public function testResultInstance(string $uid, SimpleXMLElement $xml): void
    {
        $this->journal = $this->builder->fromXml($xml, $uid);
        $this->assertInstanceOf(Journal::class, $this->journal);
    }

    /**
     * @dataProvider getXml
     */
    public function testGetContacted(string $uid, SimpleXMLElement $xml): void
    {
        $this->journal = $this->builder->fromXml($xml, $uid);
        $this->assertInstanceOf(DateTimeInterface::class, $this->journal->getContacted());
    }

    /**
     * @dataProvider journalXmlData
     *
     * @param mixed $expected
     * @param mixed $method
     */
    public function testFromXml(string $uid, SimpleXMLElement $xml, array $valueMethodMap): void
    {
        $this->journal = $this->builder->fromXml($xml, $uid);
        foreach ($valueMethodMap as [$expected, $method]) {
            $this->assertSame($expected, $this->journal->{$method}());
        }
    }

    public function journalXmlData(): array
    {
        $data = $this->getXml();
        foreach ($data as &$item) {
            $item[] = [
                ['B99FE131-48B5-440A-A552-4F1BF2BFDE82', 'getUuid'],
                [null, 'getVersion'],
                [null, 'getNotified'],
                ['Intl J Test', 'getTitle'],
                ['0000-0000', 'getIssn'],
                ['http://example.com/ijt', 'getUrl'],
                ['new', 'getStatus'],
                [false, 'getTermsAccepted'],
                ['user@example.com', 'getEmail'],
                ['Publisher institution', 'getPublisherName'],
                ['http://publisher.example.com', 'getPublisherUrl'],
            ];
        }
        return $data;
    }

    /**
     * @dataProvider journalRequestData
     *
     * @param mixed $expected
     * @param mixed $method
     */
    public function testFromRequest(string $uid, SimpleXMLElement $xml, array $valueMethodMap): void
    {
        $this->journal = $this->builder->fromRequest($uid, 'http://example.com/journal');
        foreach ($valueMethodMap as [$expected, $method]) {
            $this->assertSame($expected, $this->journal->{$method}());
        }
    }

    public function journalRequestData(): array
    {
        $data = $this->getXml();
        foreach ($data as &$item) {
            $item[] = [
                ['B99FE131-48B5-440A-A552-4F1BF2BFDE82', 'getUuid'],
                [null, 'getVersion'],
                [null, 'getNotified'],
                [null, 'getTitle'],
                [null, 'getIssn'],
                ['http://example.com/journal', 'getUrl'],
                ['new', 'getStatus'],
                [false, 'getTermsAccepted'],
                ['', 'getEmail'],
                [null, 'getPublisherName'],
                [null, 'getPublisherUrl'],
            ];
        }
        return $data;
    }

    public function testFromRequestExisting(): void
    {
        $this->journal = $this->builder->fromRequest(JournalFixtures::UUIDS[1], 'http://example.com/journal/0');
        $this->assertSame('healthy', $this->journal->getStatus());
    }

    protected function setup(): void
    {
        parent::setUp();
        $this->builder = self::$container->get(JournalBuilder::class);
    }
}
