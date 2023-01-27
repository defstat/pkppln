<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command\Shell;

use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use DOMXPath;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Extract the content of a deposit, including the embedded and encoded
 * content in the deposit's export XML.
 *
 * @author mjoyce
 */
class ExtractDepositCommand extends Command
{
    protected EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(): void
    {
        $this->setName('pn:extract');
        $this->setDescription('Extract the content of a deposit XML file.');
        $this->addArgument('file', InputArgument::REQUIRED, 'UUID of the deposit to extract.');
        $this->addArgument('path', InputArgument::OPTIONAL, 'Path to extract to. Defaults to current directory.', getcwd());
        $this->addOption('source-names', null, InputOption::VALUE_NONE, 'Use original source file names - use with extreme care.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        $path = $input->getArgument('path');
        $fs = new Filesystem();
        $useSrcNames = $input->getOption('source-names');

        if ('/' !== substr($path, -1, 1)) {
            $path .= '/';
        }
        if (! $fs->exists($path)) {
            $fs->mkdir($path);
        }
        ini_set('memory_limit', '128M');

        $dom = new DOMDocument();
        $valid = $dom->load($file, \LIBXML_COMPACT | \LIBXML_PARSEHUGE);
        if (! $valid) {
            throw new Exception("{$file} is not a valid XML file.");
        }
        $xp = new DOMXPath($dom);
        gc_enable();
        $embeddedList = $xp->query('//embed');
        \assert(is_iterable($embeddedList));
        foreach ($embeddedList as $embedded) {
            $attrs = $embedded->attributes;
            if (! $attrs) {
                $output->writeln('Embedded element has no attributes. Skipping.');

                continue;
            }
            $filename = $attrs->getNamedItem('filename')?->nodeValue;
            if (! $filename) {
                $output->writeln('Embedded element has no file name. Skipping.');

                continue;
            }
            $ext = pathinfo($filename, \PATHINFO_EXTENSION);
            if ($ext) {
                $ext = '.' . $ext;
            }

            $tmpPath = '';
            if ($useSrcNames) {
                $tmpPath = $path . $filename;
                $ext = '';
            } else {
                $tmpPath = tempnam($path, 'pn-') ?: throw new Exception('Failed to create temporary file');
            }
            $tmpName = basename($tmpPath);
            $output->writeln("Extracting {$filename} as {$path}{$tmpName}{$ext}.");
            $fh = fopen($tmpPath, 'w') ?: throw new Exception('Failed to open temporary file');
            $chunkSize = 1024 * 1024; // 1MB chunks.
            $length = $xp->evaluate('string-length(./text())', $embedded);
            $offset = 1; // xpath string offsets start at 1, not zero.
            while ($offset < $length) {
                $end = $offset + $chunkSize;
                $chunk = base64_decode($xp->evaluate("substring(./text(), {$offset}, {$chunkSize})", $embedded), true);
                \assert(\is_string($chunk));
                fwrite($fh, $chunk);
                $offset = $end;
                $output->write('.');
            }
            $output->writeln('');
            fclose($fh);
            if ($ext && ! $useSrcNames) {
                $fs->rename($tmpPath, $tmpPath . $ext);
            }
        }
        return 0;
    }
}
