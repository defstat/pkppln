<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Entity\Deposit;
use App\Entity\Journal;
use App\Repository\Repository;
use App\Services\BlackWhiteList;
use App\Services\DepositBuilder;
use App\Services\JournalBuilder;
use App\Services\Ping;
use App\Utilities\Namespaces;
use App\Utilities\ResponseFixer;
use App\Utilities\XmlParser;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Exception;
use Knp\Bundle\PaginatorBundle\Definition\PaginatorAwareInterface;
use Nines\UtilBundle\Controller\PaginatorTrait;
use Psr\Log\LoggerAwareTrait;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use SimpleXMLElement;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * An implementation of the SWORD v2 protocol.
 *
 * @Route("/api/sword/2.0")
 */
class SwordController extends AbstractController implements PaginatorAwareInterface
{
    use LoggerAwareTrait;
    use PaginatorTrait;

    /**
     * Black and white list service.
     */
    private BlackWhiteList $blackwhitelist;

    /**
     * Doctrine entity manager.
     */
    private EntityManagerInterface $em;

    /**
     * Build the controller.
     */
    public function __construct(BlackWhiteList $blackwhitelist, EntityManagerInterface $em)
    {
        $this->blackwhitelist = $blackwhitelist;
        $this->em = $em;
    }

    /**
     * Fetch an HTTP header.
     *
     * Checks the HTTP headers for $key and X-$key variant. If the app
     * is in the dev environment, will also check the query parameters for
     * $key.
     *
     * If $required is true and the header is not present BadRequestException
     * will be thrown.
     *
     * @throws BadRequestHttpException
     */
    private function fetchHeader(Request $request, string $key, bool $required = false): ?string
    {
        if ($request->headers->has($key)) {
            return $request->headers->get($key);
        }
        if ('dev' === $this->getParameter('kernel.environment') && $request->query->has($key)) {
            return (string) $request->query->get($key);
        }
        if ($required) {
            throw new BadRequestHttpException("HTTP header {$key} is required.", null, Response::HTTP_BAD_REQUEST);
        }

        return '';
    }

    /**
     * Check if a journal's uuid is whitelised or blacklisted.
     *
     * The rules are:
     *
     * If the journal uuid is whitelisted, return true
     * If the journal uuid is blacklisted, return false
     * Return the pn_accepting parameter from parameters.yml
     */
    private function checkAccess(string $uuid): bool
    {
        if (! $uuid) {
            return false;
        }
        if ($this->blackwhitelist->isWhitelisted($uuid)) {
            return true;
        }
        if ($this->blackwhitelist->isBlacklisted($uuid)) {
            return false;
        }

        return (bool) $this->getParameter('pn.accepting');
    }

    /**
     * Figure out which message to return for the network status widget.
     */
    private function getNetworkMessage(Journal $journal): string
    {
        if (null === $journal->getVersion()) {
            $networkDefault = $this->getParameter('pn.network_default');
            \assert(\is_string($networkDefault));
            return $networkDefault;
        }
        $minVersion = $this->getParameter('pn.min_accepted_version');
        \assert(\is_string($minVersion));
        if (version_compare($journal->getVersion(), $minVersion, '>=')) {
            $networkAccepting = $this->getParameter('pn.network_accepting');
            \assert(\is_string($networkAccepting));
            return $networkAccepting;
        }
        $unsupportedVersionWarning = $this->getParameter('pn.network_unsupported_version');
        \assert(\is_string($unsupportedVersionWarning));
        return sprintf($unsupportedVersionWarning, $minVersion);
    }

    /**
     * Get the XML from an HTTP request.
     *
     * @throws BadRequestHttpException
     */
    private function getXml(Request $request): SimpleXMLElement
    {
        $content =  XmlParser::cleanXml((string) $request->getContent());
        if (! $content || ! \is_string($content)) {
            throw new BadRequestHttpException('Expected request body. Found none.', null, Response::HTTP_BAD_REQUEST);
        }

        try {
            $xml = simplexml_load_string($content) ?: throw new Exception('Failed to load XML');
            Namespaces::registerNamespaces($xml);

            return $xml;
        } catch (Exception $e) {
            throw new BadRequestHttpException('Cannot parse request XML.', $e, Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Return a SWORD service document for a journal.
     *
     * Requires On-Behalf-Of and Journal-Url HTTP headers.
     *
     * @Template
     * @Route("/sd-iri.{_format}", methods={"GET"},
     *     name="sword_service_document",
     *     defaults={"_format": "xml"},
     *     requirements={"_format": "xml"}
     * )
     * @return array<string,mixed>
     */
    public function serviceDocumentAction(Request $request, JournalBuilder $builder, Ping $ping): array
    {
        $obh = strtoupper((string) $this->fetchHeader($request, 'On-Behalf-Of'));
        $journalUrl = $this->fetchHeader($request, 'Journal-Url');
        $accepting = $this->checkAccess($obh);
        $this->logger?->notice("{$request->getClientIp()} - service document - {$obh} - {$journalUrl} - accepting: " . ($accepting ? 'yes' : 'no'));
        if (! $obh) {
            throw new BadRequestHttpException('Missing On-Behalf-Of header.', null, 400);
        }
        if (! $journalUrl) {
            throw new BadRequestHttpException('Missing Journal-Url header.', null, 400);
        }

        $journal = null;
        try {
            $journal = $builder->fromRequest($obh, $journalUrl);
        } catch (DomainException $e) {
            throw new BadRequestHttpException($e->getMessage(), null, 400);
        }

        $this->em->flush();

        // Attempts to ping (whitelist the journal), in order to enable the client to start depositing right away
        $ping->ping($journal);

        if (! $journal->getTermsAccepted()) {
            $accepting = false;
        }

        $termsRepo = Repository::termOfUse();

        return [
            'onBehalfOf' => $obh,
            'accepting' => $accepting ? 'Yes' : 'No',
            'maxUpload' => $this->getParameter('pn.max_upload'),
            'checksumType' => $this->getParameter('pn.checksum_type'),
            'message' => $this->getNetworkMessage($journal),
            'journal' => $journal,
            'terms' => $termsRepo->getTerms(),
            'termsUpdated' => $termsRepo->getLastUpdated(),
        ];
    }

    /**
     * Create a deposit.
     *
     * @Route("/col-iri/{uuid}", methods={"POST"}, name="sword_create_deposit", requirements={
     *     "uuid": ".{36}",
     * })
     * @ParamConverter("journal", options={"mapping": {"uuid": "uuid"}})
     */
    public function createDepositAction(Request $request, Journal $journal, JournalBuilder $journalBuilder, DepositBuilder $depositBuilder): Response
    {
        $accepting = $this->checkAccess($journal->getUuid());
        if (! $journal->getTermsAccepted()) {
            $accepting = false;
        }
        $this->logger?->notice("{$request->getClientIp()} - create deposit - {$journal->getUuid()} - accepting: " . ($accepting ? 'yes' : 'no'));

        if (! $accepting) {
            throw new BadRequestHttpException('Not authorized to create deposits.', null, 400);
        }

        $xml = $this->getXml($request);

        $deposit = null;
        try {
            // Update the journal metadata.
            $journalBuilder->fromXml($xml, $journal->getUuid());
            $deposit = $depositBuilder->fromXml($journal, $xml);
        } catch (DomainException $e) {
            throw new BadRequestHttpException($e->getMessage(), null, 400);
        }

        $this->em->flush();

        $response = $this->statementAction($request, $journal, $deposit);
        $response->headers->set('Location', $this->generateUrl('sword_statement', [
            'journal_uuid' => $journal->getUuid(),
            'deposit_uuid' => $deposit->getDepositUuid(),
        ], UrlGeneratorInterface::ABSOLUTE_URL));
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }

    /**
     * Check that status of a deposit by fetching the sword statemt.
     *
     * @Route("/cont-iri/{journal_uuid}/{deposit_uuid}/state", methods={"GET"}, name="sword_statement", requirements={
     *     "journal_uuid": ".{36}",
     *     "deposit_uuid": ".{36}"
     * })
     * @ParamConverter("journal", options={"mapping": {"journal_uuid": "uuid"}})
     * @ParamConverter("deposit", options={"mapping": {"deposit_uuid": "depositUuid"}})
     */
    public function statementAction(Request $request, Journal $journal, Deposit $deposit): Response
    {
        $accepting = $this->checkAccess($journal->getUuid());
        $this->logger?->notice("{$request->getClientIp()} - statement - {$journal->getUuid()} - {$deposit->getDepositUuid()} - accepting: " . ($accepting ? 'yes' : 'no'));
        if (! $accepting && ! $this->isGranted('ROLE_USER')) {
            throw new BadRequestHttpException('Not authorized to request statements.', null, 400);
        }
        if ($journal !== $deposit->getJournal()) {
            throw new BadRequestHttpException('Deposit does not belong to journal.', null, 400);
        }
        $journal->setContacted(new DateTime());
        $journal->setStatus('healthy');
        $this->em->flush();
        $response = $this->render('sword/statement.xml.twig', [
            'deposit' => $deposit,
        ]);
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }

    /**
     * Edit a deposit with an HTTP PUT.
     *
     * @Route("/cont-iri/{journal_uuid}/{deposit_uuid}/edit", methods={"PUT"}, name="sword_edit", requirements={
     *     "journal_uuid": ".{36}",
     *     "deposit_uuid": ".{36}"
     * })
     * @ParamConverter("journal", options={"mapping": {"journal_uuid": "uuid"}})
     * @ParamConverter("deposit", options={"mapping": {"deposit_uuid": "depositUuid"}})
     */
    public function editAction(Request $request, Journal $journal, Deposit $deposit, DepositBuilder $builder): Response
    {
        $accepting = $this->checkAccess($journal->getUuid());
        $this->logger?->notice("{$request->getClientIp()} - edit deposit - {$journal->getUuid()} - {$deposit->getDepositUuid()} - accepting: " . ($accepting ? 'yes' : 'no'));
        if (! $accepting) {
            throw new BadRequestHttpException('Not authorized to create deposits.', null, 400);
        }
        if ($journal !== $deposit->getJournal()) {
            throw new BadRequestHttpException('Deposit does not belong to journal.', null, 400);
        }
        $xml = $this->getXml($request);
        $newDeposit = $builder->fromXml($journal, $xml);
        $newDeposit->setAction('edit');
        $this->em->flush();

        $response = $this->statementAction($request, $journal, $deposit);
        $response->headers->set('Location', $this->generateUrl('sword_statement', [
            'journal_uuid' => $journal->getUuid(),
            'deposit_uuid' => $deposit->getDepositUuid(),
        ], UrlGeneratorInterface::ABSOLUTE_URL));
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }
}
