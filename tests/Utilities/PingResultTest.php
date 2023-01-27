<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utilities;

use App\Utilities\PingResult;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * Description of PingResultTest.
 */
class PingResultTest extends TestCase
{
    /**
     * @var PingResult
     */
    private $result;

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

    public function testInstance(): void
    {
        $this->assertInstanceOf(PingResult::class, $this->result);
    }

    public function testConstructorException(): void
    {
        $response = new Response(200, [], '<n>&foo;</n>');
        $this->result = new PingResult($response);
        $this->assertTrue($this->result->hasError());
    }

    public function testHttpStatus(): void
    {
        $this->assertSame(200, $this->result->getHttpStatus());
    }

    public function testError(): void
    {
        $this->assertFalse($this->result->hasError());
        $this->assertEmpty($this->result->getError());
    }

    public function testAddError(): void
    {
        $this->assertFalse($this->result->hasError());
        $this->result->addError('bad things happened.');
        $this->assertTrue($this->result->hasError());
    }

    public function testGetBody(): void
    {
        $this->assertStringContainsStringIgnoringCase('1.2.0.0', $this->result->getBody());
        $this->assertStringContainsStringIgnoringCase('1.2.0.0', $this->result->getBody(true));
        $this->assertStringContainsStringIgnoringCase('1.2.0.0', $this->result->getBody(false));
    }

    public function testXml(): void
    {
        $this->assertTrue($this->result->hasXml());
        $this->assertInstanceOf(SimpleXMLElement::class, $this->result->getXml());
    }

    public function testGetHeader(): void
    {
        $this->assertSame('Validated', $this->result->getHeader('foo')[0]);
    }

    public function testGetApplicationVersion(): void
    {
        $this->assertSame('2.4.8.1', $this->result->getApplicationVersion());
    }

    public function testGetPluginReleaseVersion(): void
    {
        $this->assertSame('1.2.0.0', $this->result->getPluginReleaseVersion());
    }

    public function testPluginReleaseDate(): void
    {
        $this->assertSame('2015-07-13', $this->result->getPluginReleaseDate());
    }

    public function testPluginCurrent(): void
    {
        $this->assertSame('1', $this->result->isPluginCurrent());
    }

    public function testTermsAccepted(): void
    {
        $this->assertSame('yes', $this->result->areTermsAccepted());
    }

    public function testJournalTitle(): void
    {
        $this->assertSame('Publication of Soft Cheeses', $this->result->getJournalTitle());
    }

    public function testArticleCount(): void
    {
        $this->assertSame(12, $this->result->getArticleCount());
    }

    public function testArticleTitles(): void
    {
        $expected = [
            ['date' => '2017-12-26 13:56:20', 'title' => 'Brie'],
            ['date' => '2017-12-26 13:56:20', 'title' => 'Coulommiers'],
        ];
        $this->assertSame($expected, $this->result->getArticleTitles());
    }

    protected function setup(): void
    {
        parent::setUp();
        $response = new Response(200, ['foo' => ['Validated']], $this->getXml());
        $this->result = new PingResult($response);
    }
}
