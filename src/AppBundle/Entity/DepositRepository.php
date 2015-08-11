<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * DepositRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class DepositRepository extends EntityRepository
{
    /**
     * Find deposits by state.
     * 
     * @param string $state
     * 
     * @return Deposit[]
     */
    public function findByState($state) {
        return $this->findBy(array(
            'state' => $state,
        ));
    }
}