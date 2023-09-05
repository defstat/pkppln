<?php

declare(strict_types=1);

/*
 * (c) 2020 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command\Shell;

use App\Entity\Journal;
use App\Repository\Repository;
use App\Services\Ping;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Nines\UserBundle\Entity\User;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Ping all the journals that haven't contacted the PKP Preservation Network in a while, and send
 * notifications to interested users.
 */
class HealthCheckCommand extends Command
{
    use LoggerAwareTrait;

    protected Ping $ping;
    private Environment $templating;
    private ContainerInterface $container;
    private EntityManagerInterface $em;
    private MailerInterface $mailer;

    /**
     * Set the service container, and initialize the command.
     */
    public function __construct(LoggerInterface $logger, Ping $ping, Environment $environment, ContainerInterface $container, EntityManagerInterface $em, MailerInterface $mailer, )
    {
        parent::__construct();
        $this->templating = $environment;
        $this->logger = $logger;
        $this->ping = $ping;
        $this->container = $container;
        $this->em = $em;
        $this->mailer = $mailer;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('pn:health:check');
        $this->setDescription('Find journals that have gone silent.');
        $this->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Do not update journal status');
        parent::configure();
    }

    /**
     * Send the notifications.
     *
     * @param iterable<User> $users
     * @param iterable<Journal> $journals
     * @throws \Twig\Error\Error
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    protected function sendNotifications(int $days, iterable $users, iterable $journals): void
    {
        $notification = $this->templating->render('App:HealthCheck:notification.txt.twig', [
            'journals' => $journals,
            'days' => $days,
        ]);
        foreach ($users as $user) {
            $email = (new Email())
                ->from('noreplies@pkp-pn.lib.sfu.ca')
                ->to(new Address((string) $user->getEmail(), (string) $user->getFullname()))
                ->subject('Automated notification from the PKP Preservation Network')
                ->text($notification);

            $this->mailer->send($email);
        }
    }

    /**
     * Request a ping from a journal.
     */
    protected function pingJournal(Journal $journal): bool
    {
        $result = $this->ping->ping($journal);
        if ($result->hasError()) {
            $this->logger?->error($result->getError());
        }
        return strtolower($result->areTermsAccepted()) === 'yes';
    }

    /**
     * @throws \Twig\Error\Error
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $this->container->get('days_silent');
        $journals = Repository::journal()->findSilent($days);
        $count = \count($journals);
        $this->logger?->notice("Found {$count} silent journals.");
        if (0 === \count($journals)) {
            return 0;
        }

        /** @todo The method Repository::user()->findUserToNotify() and the "notify" field have been dropped */
        $users = Repository::user()->findBy(['email' => 'admin@pkp.sfu.ca']);
        if (0 === \count($users)) {
            $this->logger?->error('No users to notify.');

            return 0;
        }
        $this->sendNotifications($days, $users, $journals);

        foreach ($journals as $journal) {
            if ($this->pingJournal($journal)) {
                $this->logger?->notice("Ping Success {$journal->getUrl()})");
                $journal->setStatus('healthy');
                $journal->setContacted(new DateTime());
            } else {
                $journal->setStatus('unhealthy');
                $journal->setNotified(new DateTime());
            }
        }

        if (! $input->getOption('dry-run')) {
            $this->em->flush();
        }
        return 0;
    }
}
