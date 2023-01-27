<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Services;

use App\DataFixtures\JournalFixtures;
use App\Entity\Whitelist;
use App\Services\BlackWhiteList;
use App\Services\Ping;
use App\Utilities\PingResult;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use App\Tests\TestCase\BaseControllerTestCase;

/**
 * Description of PingTest.
 */
class PingTest extends BaseControllerTestCase
{
    /**
     * @var Ping
     */
    private $ping;

    /**
     * @var BlackWhiteList
     */
    private $list;

    private function getXml()
    {
        return <<<'ENDXML'
<?xml version="1.0" ?>
<pnPlugin>
  <ojsInfo>
    <release>2.4.8.1</release>
  </ojsInfo>
  <pluginInfo>
    <release>1.2.0.0</release>
    <releaseDate>2015-07-13</releaseDate>
    <current>1</current>
    <prerequisites>
      <phpVersion>5.6.11-1ubuntu3.4</phpVersion>
      <curlVersion>7.43.0</curlVersion>
      <zipInstalled>yes</zipInstalled>
      <tarInstalled>yes</tarInstalled>
      <acron>yes</acron>
      <tasks>no</tasks>
    </prerequisites>
    <terms termsAccepted="yes">
      <term key="foo" updated="2015-11-30 18:34:43+00:00" accepted="2018-02-17T04:00:15+00:00">
        This is a term.
      </term>
    </terms>
  </pluginInfo>
  <journalInfo>
    <title>Publication of Soft Cheeses</title>
    <articles count="12">
      <article pubDate="2017-12-26 13:56:20">
        Brie
      </article>
      <article pubDate="2017-12-26 13:56:20">
        Coulommiers
      </article>
    </articles>
  </journalInfo>
</pnPlugin>
ENDXML;
    }

    protected function fixtures(): array
    {
        return [
            JournalFixtures::class,
        ];
    }

    public function testInstance(): void
    {
        $this->assertInstanceOf(Ping::class, $this->ping);
    }

    public function testProcessFail(): void
    {
        $result = $this->createMock(PingResult::class);
        $result->method('getHttpStatus')->willReturn(404);
        $journal = $this->getReference('journal.1');
        $this->ping->process($journal, $result);
        $this->assertSame('ping-error', $journal->getStatus());
    }

    public function testProcessMissingRelease(): void
    {
        $result = $this->createMock(PingResult::class);
        $result->method('getHttpStatus')->willReturn(200);
        $result->method('getApplicationVersion')->willReturn(null);
        $journal = $this->getReference('journal.1');
        $this->ping->process($journal, $result);
        $this->assertSame('ping-error', $journal->getStatus());
    }

    public function testProcessOldVersion(): void
    {
        $result = $this->createMock(PingResult::class);
        $result->method('getHttpStatus')->willReturn(200);
        $result->method('getApplicationVersion')->willReturn('2.4.0');
        $result->method('getJournalTitle')->willReturn('Yes Minister');
        $result->method('areTermsAccepted')->willReturn('Yes');

        $journal = $this->getReference('journal.1');
        $this->ping->process($journal, $result);
        $this->em->flush();
        $this->assertSame('healthy', $journal->getStatus());
        $this->assertFalse($this->list->isListed($journal->getUuid()));
    }

    public function testProcessListed(): void
    {
        $result = $this->createMock(PingResult::class);
        $result->method('getHttpStatus')->willReturn(200);
        $result->method('getApplicationVersion')->willReturn('2.4.9');
        $result->method('getJournalTitle')->willReturn('Yes Minister');
        $result->method('areTermsAccepted')->willReturn('Yes');

        $journal = $this->getReference('journal.1');
        $whitelist = new Whitelist();
        $whitelist->setUuid($journal->getUuid());
        $whitelist->setComment('testing.');
        $this->em->persist($whitelist);
        $this->em->flush();

        $this->ping->process($journal, $result);
        $this->em->flush();
        $this->assertSame('healthy', $journal->getStatus());
    }

    public function testProcessSuccess(): void
    {
        $result = $this->createMock(PingResult::class);
        $result->method('getHttpStatus')->willReturn(200);
        $result->method('getApplicationVersion')->willReturn('2.4.9');
        $result->method('getJournalTitle')->willReturn('Yes Minister');
        $result->method('areTermsAccepted')->willReturn('Yes');

        $journal = $this->getReference('journal.1');
        $this->ping->process($journal, $result);
        $this->em->clear();
        $this->assertSame('healthy', $journal->getStatus());
        $this->assertTrue($this->list->isListed($journal->getUuid()));
    }

    public function testPingFail(): void
    {
        $mock = new MockHandler([
            new RequestException('Bad mojo.', new Request('GET', 'http://example.com')),
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->ping->setClient($client);
        $journal = $this->getReference('journal.1');
        $this->ping->ping($journal);
        $this->assertSame('ping-error', $journal->getStatus());
    }

    public function testPingSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], $this->getXml()),
        ]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->ping->setClient($client);
        $journal = $this->getReference('journal.1');
        $this->ping->ping($journal);
        $this->em->flush();
        $this->assertSame('healthy', $journal->getStatus());
        $this->assertTrue($this->list->isListed($journal->getUuid()));
    }

    protected function setup(): void
    {
        parent::setUp();
        $this->ping = self::$container->get(Ping::class);
        $this->list = self::$container->get(BlackWhiteList::class);
    }
}
