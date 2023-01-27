<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command\Processing;

use App\Entity\Deposit;
use App\Services\Processing\Harvester;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Harvest deposits from journals.
 */
class HarvestCommand extends AbstractProcessingCmd
{
    /**
     * Harvester service.
     */
    private Harvester $harvester;

    /**
     * Build the command.
     */
    public function __construct(EntityManagerInterface $em, Harvester $harvester)
    {
        parent::__construct($em);
        $this->harvester = $harvester;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('pn:harvest');
        $this->setDescription('Harvest deposits.');
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function processDeposit(Deposit $deposit): null|bool|string
    {
        return $this->harvester->processDeposit($deposit);
    }

    /**
     * {@inheritdoc}
     */
    public function nextState(): string
    {
        return 'harvested';
    }

    /**
     * {@inheritdoc}
     */
    public function errorState(): string
    {
        return 'harvest-error';
    }

    /**
     * {@inheritdoc}
     */
    public function processingState(): string
    {
        return 'depositedByJournal';
    }

    /**
     * {@inheritdoc}
     */
    public function failureLogMessage(): string
    {
        return 'Deposit harvest failed.';
    }

    /**
     * {@inheritdoc}
     */
    public function successLogMessage(): string
    {
        return 'Deposit harvest succeeded.';
    }
}
