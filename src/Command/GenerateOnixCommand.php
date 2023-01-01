<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Entity\Journal;
use App\Services\FilePaths;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Internal\Hydration\IterableResult;
use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XMLWriter;

/**
 * Generate an ONIX-PH feed for all the deposits in the PLN.
 *
 * @see http://www.editeur.org/127/ONIX-PH/
 */
class GenerateOnixCommand extends Command
{
    use LoggerAwareTrait;

    public const BATCH_SIZE = 50;

    protected EntityManagerInterface $em;
    private FilePaths $filePaths;

    /**
     * Set the service container, and initialize the command.
     */
    public function __construct(EntityManagerInterface $em, FilePaths $filePaths, LoggerInterface $logger)
    {
        parent::__construct();
        $this->em = $em;
        $this->filePaths = $filePaths;
        $this->setLogger($logger);
    }

    /**
     * Get the journals to process.
     *
     * @return IterableResult|Journal[][]
     */
    protected function getJournals(): IterableResult
    {
        $query = $this->em->createQuery('SELECT j FROM App:Journal j');

        return $query->iterate();
    }

    /**
     * Generate a CSV file at $filePath.
     */
    protected function generateCsv(string $filePath): void
    {
        $handle = fopen($filePath, 'w') ?: throw new Exception("Failed to open file '{$filePath}'");
        $iterator = $this->getJournals();
        fputcsv($handle, ['Generated', date('Y-m-d')]);
        fputcsv($handle, [
            'ISSN',
            'Title',
            'Publisher',
            'Url',
            'Vol',
            'No',
            'Published',
            'Deposited',
        ]);
        $i = 0;
        foreach ($iterator as $row) {
            $journal = $row[0];
            $deposits = $journal->getSentDeposits();
            if (0 === $deposits->count()) {
                continue;
            }
            foreach ($deposits as $deposit) {
                if (null === $deposit->getDepositDate()) {
                    continue;
                }
                fputcsv($handle, [
                    $journal->getIssn(),
                    $journal->getTitle(),
                    $journal->getPublisherName(),
                    $journal->getUrl(),
                    $deposit->getVolume(),
                    $deposit->getIssue(),
                    $deposit->getPubDate()->format('Y-m-d'),
                    $deposit->getDepositDate()->format('Y-m-d'),
                ]);
            }
            $i++;
            $this->em->detach($journal);
            if ($i % self::BATCH_SIZE) {
                $this->em->clear();
            }
        }
    }

    /**
     * Generate an XML file at $filePath.
     */
    protected function generateXml(string $filePath): void
    {
        $iterator = $this->getJournals();

        $writer = new XMLWriter();
        $writer->openUri($filePath);
        $writer->setIndent(true);
        $writer->setIndentString(' ');
        $writer->startDocument();
        $writer->startElement('ONIXPreservationHoldings');
        $writer->writeAttribute('version', '0.2');
        $writer->writeAttribute('xmlns', 'http://www.editeur.org/onix/serials/SOH');

        $writer->startElement('Header');
        $writer->startElement('Sender');
        $writer->writeElement('SenderName', 'Public Knowledge Project PLN');
        $writer->endElement(); // Sender
        $writer->writeElement('SentDateTime', date('Ymd'));
        $writer->writeElement('CompleteFile');
        $writer->endElement(); // Header.

        $writer->startElement('HoldingsList');
        $writer->startElement('PreservationAgency');
        $writer->writeElement('PreservationAgencyName', 'Public Knowledge Project PLN');
        $writer->endElement(); // PreservationAgency

        foreach ($iterator as $row) {
            $journal = $row[0];
            $deposits = $journal->getSentDeposits();
            if (! \count($deposits)) {
                $this->em->detach($journal);

                continue;
            }
            $writer->startElement('HoldingsRecord');

            $writer->startElement('NotificationType');
            $writer->text('00');
            $writer->endElement(); // NotificationType

            $writer->startElement('ResourceVersion');

            $writer->startElement('ResourceVersionIdentifier');
            $writer->writeElement('ResourceVersionIDType', '07');
            $writer->writeElement('IDValue', $journal->getIssn());
            $writer->endElement(); // ResourceVersionIdentifier

            $writer->startElement('Title');
            $writer->writeElement('TitleType', '01');
            $writer->writeElement('TitleText', $journal->getTitle());
            $writer->endElement(); // Title

            $writer->startElement('Publisher');
            $writer->writeElement('PublishingRole', '01');
            $writer->writeElement('PublisherName', $journal->getPublisherName());
            $writer->endElement(); // Publisher

            $writer->startElement('OnlinePackage');

            $writer->startElement('Website');
            $writer->writeElement('WebsiteRole', '05');
            $writer->writeElement('WebsiteLink', $journal->getUrl());
            $writer->endElement(); // Website

            foreach ($deposits as $deposit) {
                $writer->startElement('PackageDetail');
                $writer->startElement('Coverage');

                $writer->writeElement('CoverageDescriptionLevel', '03');
                $writer->writeElement('SupplementInclusion', '04');
                $writer->writeElement('IndexInclusion', '04');

                $writer->startElement('FixedCoverage');
                $writer->startElement('Release');

                $writer->startElement('Enumeration');

                $writer->startElement('Level1');
                $writer->writeElement('Unit', 'Volume');
                $writer->writeElement('Number', $deposit->getVolume());
                $writer->endElement(); // Level1

                $writer->startElement('Level2');
                $writer->writeElement('Unit', 'Issue');
                $writer->writeElement('Number', $deposit->getIssue());
                $writer->endElement(); // Level2

                $writer->endElement(); // Enumeration

                $writer->startElement('NominalDate');
                $writer->writeElement('Calendar', '00');
                $writer->writeElement('DateFormat', '00');
                $writer->writeElement('Date', $deposit->getPubDate()->format('Ymd'));
                $writer->endElement(); // NominalDate

                $writer->endElement(); // Release
                $writer->endElement(); // FixedCoverage
                $writer->endElement(); // Coverage

                $writer->startElement('PreservationStatus');
                $writer->writeElement('PreservationStatusCode', '05');
                $writer->writeElement('DateOfStatus', $deposit->getDepositDate() ? $deposit->getDepositDate()->format('Ymd') : date('Ymd'));
                $writer->endElement(); // PreservationStatus

                $writer->writeElement('VerificationStatus', '01');
                $writer->endElement(); // PackageDetail
                $this->em->detach($deposit);
            }
            $writer->endElement(); // OnlinePackage
            $writer->endElement(); // ResourceVersion
            $writer->endElement(); // HoldingsRecord

            $writer->flush();
            $this->em->detach($journal);
            $this->em->clear();
        }

        $writer->endElement(); // HoldingsList
        $writer->endElement(); // ONIXPreservationHoldings
        $writer->endDocument();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        ini_set('memory_limit', '512M');
        $files = $input->getArgument('file');
        if (! $files || ! \count($files)) {
            $files[] = $this->filePaths->getOnixPath();
        }

        foreach ($files as $file) {
            $this->logger?->info("Writing {$file}");
            match ($ext = pathinfo($file, \PATHINFO_EXTENSION)) {
                'xml' => $this->generateXml($file),
                'csv' => $this->generateCsv($file),
                default => $this->logger?->error("Cannot generate {$ext} ONIX format.")
            };
        }
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(): void
    {
        $this->setName('pln:onix');
        $this->setDescription('Generate ONIX-PH feed.');
        $this->addArgument('file', InputArgument::IS_ARRAY, 'File(s) to write the feed to.');
    }
}