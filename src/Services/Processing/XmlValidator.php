<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Services\Processing;

use App\Entity\Deposit;
use App\Services\DtdValidator;
use App\Services\FilePaths;
use App\Services\SchemaValidator;
use App\Utilities\BagReader;
use App\Utilities\XmlParser;
use DOMElement;

/**
 * Validate the OJS XML export.
 */
class XmlValidator
{
    /**
     * The PKP Public Identifier for OJS export XML.
     */
    public const PKP_PUBLIC_ID = '-//PKP//OJS Articles and Issues XML//EN';

    /**
     * Block size for reading very large files.
     */
    public const BLOCKSIZE = 64 * 1023;

    /**
     * Calculate file path locations.
     */
    private FilePaths $filePaths;

    /**
     * Validator service.
     */
    private DtdValidator $dtdValidator;

    /**
     * Parser for XML files.
     */
    private XmlParser $xmlParser;

    /**
     * Bag Reader.
     */
    private BagReader $bagReader;

    private SchemaValidator $schemaValidator;

    /**
     * Build the validator.
     */
    public function __construct(FilePaths $filePaths, DtdValidator $dtdValidator, SchemaValidator $schemaValidator)
    {
        $this->filePaths = $filePaths;
        $this->dtdValidator = $dtdValidator;
        $this->schemaValidator = $schemaValidator;
        $this->xmlParser = new XmlParser();
        $this->bagReader = new BagReader();
    }

    /**
     * Override the default bag reader.
     */
    public function setBagReader(BagReader $bagReader): void
    {
        $this->bagReader = $bagReader;
    }

    /**
     * Override the default Xml Parser.
     */
    public function setXmlParser(XmlParser $xmlParser): void
    {
        $this->xmlParser = $xmlParser;
    }

    /**
     * Add any errors to the report.
     * @param array<array{message: string,file: string,line: int}> $errors
     */
    public function reportErrors(array $errors, string &$report): void
    {
        foreach ($errors as $error) {
            $report .= "On line {$error['line']}: {$error['message']}\n";
        }
    }

    public function processDeposit(Deposit $deposit): bool
    {
        $harvestedPath = $this->filePaths->getHarvestFile($deposit);
        $bag = $bag = $this->bagReader->readBag($harvestedPath);
        $report = '';

        $issuePath = $bag->getBagRoot() . '/data/' . 'Issue' . $deposit->getDepositUuid() . '.xml';
        $dom = $this->xmlParser->fromFile($issuePath);
        $root = $dom->documentElement;
        assert($root instanceof DOMElement);
        if ($root->hasAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'schemaLocation')) {
            $this->schemaValidator->validate($dom, $bag->getBagRoot() . '/data/');
            $errors = $this->schemaValidator->getErrors();
        } else {
            $this->dtdValidator->validate($dom, $bag->getBagRoot() . '/data/');
            $errors = $this->dtdValidator->getErrors();
        }
        $this->reportErrors($errors, $report);
        if (trim($report)) {
            $deposit->addToProcessingLog($report);

            return false;
        }

        return true;
    }
}