<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Services;

use App\Entity\Blacklist;
use App\Entity\Whitelist;
use App\Repository\Repository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Description of BlackWhiteList.
 */
class BlackWhiteList
{
    /**
     * Return true if the uuid is whitelisted.
     */
    public function isWhitelisted(string $uuid): bool
    {
        return null !== Repository::whitelist()->findOneBy(['uuid' => strtoupper($uuid)]);
    }

    /**
     * Return true if the uuid is blacklisted.
     */
    public function isBlacklisted(string $uuid): bool
    {
        return null !== Repository::blacklist()->findOneBy(['uuid' => strtoupper($uuid)]);
    }

    /**
     * Check if a journal is whitelisted or blacklisted.
     */
    public function isListed(string $uuid): bool
    {
        return $this->isWhitelisted($uuid) || $this->isBlacklisted($uuid);
    }
}