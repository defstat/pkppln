<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Services\Processing;

use App\Entity\Deposit;
use App\Services\SwordClient;

/**
 * Send a fully processed deposit to LOCKSSOMatic.
 *
 * @see SwordClient
 */
class Depositor
{
    /**
     * Sword client to talk to LOCKSSOMatic.
     */
    private SwordClient $client;

    /**
     * Maximum supported application version or null.
     */
    private ?string $maxAcceptedVersion;

    /**
     * Build the service.
     */
    public function __construct(SwordClient $client, ?string $maxAcceptedVersion)
    {
        $this->client = $client;
        $this->maxAcceptedVersion = $maxAcceptedVersion;
    }

    /**
     * Process one deposit.
     */
    public function processDeposit(Deposit $deposit): null|bool|string
    {
        if ($this->maxAcceptedVersion && version_compare($deposit->getVersion(), $this->maxAcceptedVersion, '>')) {
            return 'hold';
        }

        return $this->client->createDeposit($deposit);
    }
}
