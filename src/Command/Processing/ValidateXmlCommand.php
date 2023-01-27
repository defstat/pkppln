<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command\Processing;

use App\Entity\Deposit;
use App\Services\Processing\XmlValidator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Validate XML in a deposit.
 */
class ValidateXmlCommand extends AbstractProcessingCmd
{
    private XmlValidator $xmlValidator;

    /**
     * Build the command.
     */
    public function __construct(EntityManagerInterface $em, XmlValidator $xmlValidator)
    {
        parent::__construct($em);
        $this->xmlValidator = $xmlValidator;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('pn:validate:xml');
        $this->setDescription('Validate XML export files.');
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function processDeposit(Deposit $deposit): null|bool|string
    {
        return $this->xmlValidator->processDeposit($deposit);
    }

    /**
     * {@inheritdoc}
     */
    public function nextState(): string
    {
        return 'xml-validated';
    }

    /**
     * {@inheritdoc}
     */
    public function processingState(): string
    {
        return 'bag-validated';
    }

    /**
     * {@inheritdoc}
     */
    public function failureLogMessage(): string
    {
        return 'XML Validation failed.';
    }

    /**
     * {@inheritdoc}
     */
    public function successLogMessage(): string
    {
        return 'XML validation succeeded.';
    }

    /**
     * {@inheritdoc}
     */
    public function errorState(): string
    {
        return 'xml-error';
    }
}
