<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Services;

use App\Entity\Journal;
use App\Entity\Whitelist;
use App\Utilities\PingResult;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Client;

/**
 * Ping service.
 */
class Ping
{
    /**
     * Http client configuration.
     */
    public const CONF = [
        'allow_redirects' => true,
        'headers' => [
            'User-Agent' => 'PreservationNetwork/1.0 https://pkp.sfu.ca/pkp-pn',
            'Accept' => 'application/xml,text/xml,*/*;q=0.1',
        ],
    ];

    /**
     * Minimum expected application version.
     */
    private string $minVersion;

    /**
     * Doctrine instance.
     */
    private EntityManagerInterface $em;

    /**
     * Black and white service.
     */
    private BlackWhiteList $list;

    /**
     * Guzzle http client.
     */
    private Client $client;

    /**
     * Construct the ping service.
     */
    public function __construct(string $minVersion, EntityManagerInterface $em, BlackWhiteList $list)
    {
        $this->minVersion = $minVersion;
        $this->em = $em;
        $this->list = $list;
        $this->client = new Client(['verify' => false, 'connect_timeout' => 15]);
    }

    /**
     * Set the HTTP client.
     */
    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * Process a ping response.
     */
    public function process(Journal $journal, PingResult $result): void
    {
        if (! $result->getApplicationVersion()) {
            $journal->setStatus('ping-error');
            $result->addError('Journal version information missing in ping result.');

            return;
        }
        $journal->setContacted(new DateTime());
        $journal->setTitle($result->getJournalTitle());
        $journal->setVersion($result->getApplicationVersion());
        $journal->setTermsAccepted('yes' === $result->areTermsAccepted());
        $journal->setStatus('healthy');
        if (version_compare($result->getApplicationVersion(), $this->minVersion, '<')) {
            return;
        }
        if ($this->list->isListed($journal->getUuid())) {
            return;
        }
        $whitelist = new Whitelist();
        $whitelist->setUuid($journal->getUuid());
        $whitelist->setComment("{$journal->getUrl()} added by ping.");
        $this->em->persist($whitelist);
        $this->em->flush();
    }

    /**
     * Ping $journal and return the result.
     */
    public function ping(Journal $journal): PingResult
    {
        try {
            $response = $this->client->get($journal->getGatewayUrl(), self::CONF);
            $result = new PingResult($response);
            $this->process($journal, $result);

            return $result;
        } catch (Exception $e) {
            $journal->setStatus('ping-error');
            $message = strip_tags($e->getMessage());

            return new PingResult(null, $message);
        }
    }
}
