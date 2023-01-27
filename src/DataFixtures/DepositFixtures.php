<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\DataFixtures;

use App\Entity\AuContainer;
use App\Entity\Deposit;
use App\Entity\Journal;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * LoadDeposit form.
 */
class DepositFixtures extends Fixture implements DependentFixtureInterface
{
    public const UUIDS = [
        'F93A8108-B705-4763-A592-B718B00BD4EA',
        '4ECC5D8B-ECC9-435C-A072-6DCF198ACD6D',
        '92ED9A27-A584-4487-A3F9-997379FBA182',
        '978EA2B4-01DB-4F37-BD74-871DDBE71BF5',
    ];

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $em): void
    {
        for ($i = 0; $i < 4; $i++) {
            $fixture = new Deposit();
            $fixture->setVersion('2.4.8.' . $i);
            $fixture->setLicense(['Creative Commons' => 'License text']);
            $fixture->setFileType('application/zip');
            $fixture->setDepositUuid(self::UUIDS[$i]);
            $fixture->setAction('add');
            $fixture->setVolume('1');
            $fixture->setIssue((string) ($i + 1));
            $fixture->setPubDate(new DateTime("2016-{$i}-{$i}T12:00:00"));
            $fixture->setChecksumType('sha1');
            $fixture->setChecksumValue(sha1(self::UUIDS[$i]));
            $fixture->setUrl("http://example.com/path/to/{$i}.zip");
            $fixture->setSize(1000 + $i * 1000);
            $fixture->setState('depositedByJournal');
            $fixture->setErrorLog([]);
            $fixture->setDepositReceipt("http://example.com/receipt/{$i}");
            $fixture->setProcessingLog('');
            $journal = $this->getReference('journal.1');
            \assert($journal instanceof Journal);
            $fixture->setJournal($journal);
            $auContainer = $this->getReference('aucontainer');
            \assert($auContainer instanceof AuContainer);
            $fixture->setAuContainer($auContainer);

            $em->persist($fixture);
            $this->setReference('deposit.' . $i, $fixture);
        }

        $em->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [
            JournalFixtures::class,
            AuContainerFixtures::class,
        ];
    }
}
