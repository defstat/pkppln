<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utilities;

use App\Utilities\UrlValidator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the UrlValidator.
 */
class UrlValidatorTest extends TestCase
{
    private UrlValidator $validator;

    /**
     * @dataProvider urlDataProvider
     */
    public function testFilter(string $url, bool $expected, array $blockedDomains = []): void
    {
        $isValid = $this->validator->isValid($url, $blockedDomains);
        $this->assertSame($expected, $isValid);
    }

    private function urlDataProvider(): array
    {
        $blockedDomains = ['unavailable-domain.net', 'nono.com'];
        return [
            ['https://sfu.ca', true],
            ['http://sfu.ca', true],
            ['http://sfu.ca/test/file.htm', true],
            ['https://google.com:443', true],
            ['http://unavailable-domain.net', false, $blockedDomains],
            ['http://nono.com', false, $blockedDomains],
            ['http://unavailable-domain.net', true],
            ['http://nono.com', true],
            ['http://localhost', false],
            ['https://localhost', false],
            ['http://top-level-domain', false],
            ['http://127.0.0.1', false],
            ['http://255.255.255.255', false],
            ['http://255.255.255.255', false],
            ['ftp://ftp.test', false],
            ['invalid', false],
            ['http://[::1]/IPv6', false]
        ];
    }

    protected function setup(): void
    {
        parent::setUp();
        $this->validator = new UrlValidator();
    }
}
