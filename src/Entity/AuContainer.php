<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nines\UtilBundle\Entity\AbstractEntity;

/**
 * AuContainer organizes the deposits by size to abstract the responsibility
 * away from LOCKSSOMatic.
 *
 * @ORM\Table
 * @ORM\HasLifecycleCallbacks
 * @ORM\Entity(repositoryClass="App\Repository\AuContainerRepository")
 * @ORM\HasLifecycleCallbacks
 */
class AuContainer extends AbstractEntity
{
    /**
     * List of deposits in one AU (Archival Unit).
     *
     * @var Collection<int,Deposit>|Deposit[]
     * @ORM\OneToMany(targetEntity="Deposit", mappedBy="auContainer", fetch="EXTRA_LAZY")
     */
    private Collection $deposits;

    /**
     * True if the container can accept more deposits.
     *
     * @ORM\Column(type="boolean")
     */
    private bool $open;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->deposits = new ArrayCollection();
        $this->open = true;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return (string) $this->id;
    }

    /**
     * Add deposits.
     */
    public function addDeposit(Deposit $deposit): static
    {
        $this->deposits[] = $deposit;

        return $this;
    }

    /**
     * Remove deposits.
     */
    public function removeDeposit(Deposit $deposits): void
    {
        $this->deposits->removeElement($deposits);
    }

    /**
     * Get deposits.
     * @return Collection<int,Deposit>
     */
    public function getDeposits(): Collection
    {
        return $this->deposits;
    }

    /**
     * Set open. An open container can be made closed, but a closed container cannot be reopened.
     */
    public function setOpen(bool $open): static
    {
        if ($this->open) {
            // Only change an open container to closed.
            $this->open = $open;
        }

        return $this;
    }

    /**
     * Get open.
     */
    public function isOpen(): bool
    {
        return $this->open;
    }

    /**
     * Get the size of the container in bytes.
     */
    public function getSize(): int
    {
        $size = 0;
        foreach ($this->deposits as $deposit) {
            $size += $deposit->getPackageSize();
        }

        return $size;
    }

    /**
     * Count the deposits in the container.
     */
    public function countDeposits(): int
    {
        return $this->deposits->count();
    }
}
