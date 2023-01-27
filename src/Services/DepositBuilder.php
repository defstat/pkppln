<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Services;

use App\Entity\Deposit;
use App\Entity\Journal;
use App\Repository\Repository;
use App\Utilities\UrlValidator;
use App\Utilities\Xpath;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Exception;
use Psr\Log\LoggerAwareTrait;
use SimpleXMLElement;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Description of DepositBuilder.
 */
class DepositBuilder
{
    use LoggerAwareTrait;

    /**
     * Entity manager.
     */
    private EntityManagerInterface $em;

    /**
     * File paths.
     */
    private FilePaths $filePaths;

    /**
     * Local hostname
     */
    private string $hostname;

    /**
     * Build the service.
     */
    public function __construct(EntityManagerInterface $em, FilePaths $filePaths, string $hostname)
    {
        $this->em = $em;
        $this->filePaths = $filePaths;
        $this->hostname = $hostname;
    }

    /**
     * Find and return the deposit with $uuid or create a new deposit.
     */
    protected function findDeposit(string $uuid): Deposit
    {
        $deposit = Repository::deposit()->findOneBy(['depositUuid' => strtoupper($uuid)]);
        if (!$deposit) {
            return (new Deposit())->setDepositUuid($uuid)
                ->setAction('add')
                ->addToProcessingLog('Deposit received.');
        }
        \assert($deposit instanceof Deposit);

        // Clear outdated files
        $fs = new Filesystem();
        $paths = [
            $this->filePaths->getHarvestFile($deposit),
            $this->filePaths->getProcessingBagPath($deposit),
            $this->filePaths->getStagingBagPath($deposit)
        ];
        foreach ($paths as $path) {
            try {
                $fs->remove($path);
            } catch (Exception $e) {
                $this->logger?->error($e->getMessage());
            }
        }
        return $deposit->setAction('edit')
            ->setHarvestAttempts(0)
            ->addToProcessingLog('Deposit edited or reset by journal manager.');
    }

    /**
     * Build a deposit from XML.
     */
    public function fromXml(Journal $journal, SimpleXMLElement $xml): Deposit
    {
        $id = Xpath::getXmlValue($xml, '//atom:id') ?: throw new Exception('Invalid ATOM ID');
        $url = html_entity_decode((string) Xpath::getXmlValue($xml, 'pkp:content'));
        if (!UrlValidator::isValid($url, [$this->hostname])) {
            throw new DomainException("Invalid journal URL \"{$url}\".");
        }

        $deposit = $this->findDeposit(substr($id, 9, 36));
        $deposit->setState('depositedByJournal');
        $deposit->setChecksumType((string) Xpath::getXmlValue($xml, 'pkp:content/@checksumType'));
        $deposit->setChecksumValue((string) Xpath::getXmlValue($xml, 'pkp:content/@checksumValue'));
        $deposit->setFileType('');
        $deposit->setIssue((string) Xpath::getXmlValue($xml, 'pkp:content/@issue'));
        $deposit->setVolume((string) Xpath::getXmlValue($xml, 'pkp:content/@volume'));
        $deposit->setPubDate(new DateTime((string) Xpath::getXmlValue($xml, 'pkp:content/@pubdate')));
        $deposit->setJournal($journal);
        $deposit->setSize((int) Xpath::getXmlValue($xml, 'pkp:content/@size'));
        $deposit->setUrl($url);

        $deposit->setVersion((string) Xpath::getXmlValue($xml, 'pkp:content/@ojsVersion', Deposit::DEFAULT_JOURNAL_VERSION));
        $nodes = $xml->xpath('//pkp:license/node()');
        \assert(is_iterable($nodes));
        foreach ($nodes as $node) {
            $deposit->addLicense($node->getName(), (string) $node);
        }
        $this->em->persist($deposit);

        return $deposit;
    }
}
