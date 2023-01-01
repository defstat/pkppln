<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\EventListener;

use App\Entity\TermOfUse;
use App\Entity\TermOfUseHistory;
use App\Repository\Repository;
use App\Tests\TestCase\BaseControllerTestCase;

/**
 * Description of TermsOfUseListenerTest.
 */
class TermsOfUseListenerTest extends BaseControllerTestCase
{
    protected function fixtures(): array
    {
        return [];
    }

    public function testCreate(): void
    {
        $term = new TermOfUse();
        $term->setContent('test 1');
        $term->setKeyCode('t1');
        $term->setWeight(1);

        $this->em->persist($term);
        $this->em->flush();

        $history = Repository::termOfUseHistory()->findOneBy([
            'termId' => $term->getId(),
        ]);
        $this->assertNotNull($history);
        $this->assertSame('create', $history->getAction());
        $changeset = $history->getChangeSet();
        $this->assertSame([null, 1], $changeset['id']);
        $this->assertSame([null, 1], $changeset['weight']);
        $this->assertSame([null, 't1'], $changeset['keyCode']);
        $this->assertSame([null, 'test 1'], $changeset['content']);
    }

    public function testUpdate(): void
    {
        $term = new TermOfUse();
        $term->setContent('test 1');
        $term->setKeyCode('t1');
        $term->setWeight(1);

        $this->em->persist($term);
        $this->em->flush();

        $term->setContent('updated');
        $term->setKeyCode('u1');
        $term->setWeight(3);
        $this->em->flush();

        $history = Repository::termOfUseHistory()->findOneBy([
            'termId' => $term->getId(),
            'action' => 'update',
        ]);
        $this->assertNotNull($history);
        $this->assertSame('update', $history->getAction());

        $changeset = $history->getChangeSet();
        $this->assertSame([1, 3], $changeset['weight']);
        $this->assertSame(['t1', 'u1'], $changeset['keyCode']);
        $this->assertSame(['test 1', 'updated'], $changeset['content']);
    }

    public function testDelete(): void
    {
        $term = new TermOfUse();
        $term->setContent('test 1');
        $term->setKeyCode('t1');
        $term->setWeight(1);

        $this->em->persist($term);
        $this->em->flush();

        // save for later.
        $termId = $term->getId();

        $this->em->remove($term);
        $this->em->flush();

        $history = Repository::termOfUseHistory()->findOneBy([
            'termId' => $termId,
            'action' => 'delete',
        ]);
        $this->assertNotNull($history);
        $this->assertSame('delete', $history->getAction());

        $changeset = $history->getChangeSet();
        $this->assertSame([1, null], $changeset['weight']);
        $this->assertSame(['t1', null], $changeset['keyCode']);
        $this->assertSame(['test 1', null], $changeset['content']);
    }
}