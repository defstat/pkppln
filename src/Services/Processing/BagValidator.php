<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Services\Processing;

use App\Entity\Deposit;
use App\Services\FilePaths;
use App\Utilities\BagReader;

/**
 * Validate a bag, according to the bagit spec.
 */
class BagValidator
{
    /**
     * File path service.
     */
    private FilePaths $filePaths;

    /**
     * Bag reader service.
     */
    private BagReader $bagReader;

    /**
     * Build the validator.
     */
    public function __construct(FilePaths $fp)
    {
        $this->filePaths = $fp;
        $this->bagReader = new BagReader();
    }

    /**
     * Override the bag reader.
     */
    public function setBagReader(BagReader $bagReader): void
    {
        $this->bagReader = $bagReader;
    }

    public function processDeposit(Deposit $deposit): bool
    {
        $harvestedPath = $this->filePaths->getHarvestFile($deposit);
        $processingPath = $this->filePaths->getProcessingBagPath($deposit);
        $bag = $this->bagReader->readCompressedBag($harvestedPath, $processingPath);
        if (! $bag->isValid()) {
            foreach ($bag->getErrors() as $error) {
                $deposit->addErrorLog("Bag validation error for {$error['file']} - {$error['message']}");
            }

            return false;
        }
        $version = implode('', $bag->getBagInfoByTag('PKP-PLN-OJS-Version'));
        if ($version && $version !== $deposit->getVersion()) {
            $deposit->addErrorLog("Bag journal version tag {$version} does not match deposit journal version {$deposit->getVersion()}");
        }

        return true;
    }
}
