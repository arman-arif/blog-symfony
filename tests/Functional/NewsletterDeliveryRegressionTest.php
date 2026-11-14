<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\NewsletterCampaign;
use App\Entity\NewsletterDelivery;
use App\Enum\NewsletterCampaignStatus;
use App\Enum\NewsletterDeliveryStatus;
use App\Enum\NewsletterSubscriberStatus;
use App\Message\SendScheduledNewsletterCampaignsMessage;
use App\Repository\NewsletterCampaignRepository;
use App\Repository\NewsletterDeliveryRepository;
use App\Repository\UserRepository;
use App\Service\Newsletter\NewsletterCampaignService;
use App\Service\Settings\DatabaseSettingsService;
use App\Tests\Factory\NewsletterSubscriberFactory;
use App\Tests\Support\RecordingSmtpFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class NewsletterDeliveryRegressionTest extends FrontTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        self::getContainer()->get('cache.content')->clear();
        self::ensureKernelShutdown();
        RecordingSmtpFactory::reset();
    }

    protected function tearDown(): void
    {
        self::getContainer()->get('cache.content')->clear();
        RecordingSmtpFactory::reset();
        parent::tearDown();
    }

    public function testQueueDoesNotDeliverUntilTheRealBatchWorkerRuns(): void
    {
        self::createClient();
        NewsletterSubscriberFactory::createMany(51, ['status' => NewsletterSubscriberStatus::Confirmed]);
        $campaign = $this->campaign();
        self::assertSame(51, $this->service()->queue($campaign));
        self::assertEmailCount(0);
        self::assertCount(2, $this->transport()->getSent());
        self::assertSame('queued', $campaign->getStatus()->value);
        self::assertNull($campaign->getSentAt());
        self::assertSame(0, $campaign->getStats()['sent']);

        $this->consume(2);
        self::assertEmailCount(51);
        $campaign = $this->reload($campaign);
        self::assertSame(NewsletterCampaignStatus::Sent, $campaign->getStatus());
        self::assertSame(51, $campaign->getStats()['sent']);
        $this->assertLedgerMatches($campaign);
    }

    public function testWorkerRechecksEligibilityAndIgnoresRecipientsOutsideTheQueuedAudience(): void
    {
        self::createClient();
        $unsubscribed = NewsletterSubscriberFactory::createOne(['status' => NewsletterSubscriberStatus::Confirmed]);
        $pending = NewsletterSubscriberFactory::createOne(['status' => NewsletterSubscriberStatus::Confirmed]);
        $campaign = $this->campaign();
        self::assertSame(2, $this->service()->queue($campaign));
        $unsubscribed->setStatus(NewsletterSubscriberStatus::Unsubscribed)->setUnsubscribedAt(new \DateTimeImmutable());
        $pending->setStatus(NewsletterSubscriberStatus::Pending);
        $outsider = NewsletterSubscriberFactory::createOne(['status' => NewsletterSubscriberStatus::Confirmed]);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->service()->sendBatch((int) $campaign->getId(), [$outsider->getId()]);
        $this->consume();
        self::assertEmailCount(0);
        self::assertSame(0, $this->reload($campaign)->getStats()['sent']);
        self::assertSame(2, $this->reload($campaign)->getStats()['skipped']);
        $this->assertLedgerMatches($this->reload($campaign));
    }

    public function testReplayedBatchAndRepeatedQueueDoNotResendOrResetStatistics(): void
    {
        self::createClient();
        $subscriber = NewsletterSubscriberFactory::createOne(['status' => NewsletterSubscriberStatus::Confirmed]);
        $campaign = $this->campaign();
        $this->service()->queue($campaign);
        $this->consume();
        self::assertEmailCount(1);

        $this->service()->sendBatch((int) $campaign->getId(), [$subscriber->getId(), $subscriber->getId()]);
        $this->service()->queue($this->reload($campaign));
        self::assertEmailCount(1);
        self::assertSame(1, $this->reload($campaign)->getStats()['sent']);
    }

    public function testSmtpFailureIsNotCountedAsSentAndDoesNotAbortOtherRecipientsOrReplay(): void
    {
        self::createClient();
        RecordingSmtpFactory::$enabled = true;
        RecordingSmtpFactory::$failTo = ['fails@example.test'];
        self::getContainer()->get(DatabaseSettingsService::class)->saveGroup('smtp', [
            'mail_transport' => 'smtp', 'mail_host' => 'smtp.example.test',
            'mail_from_address' => 'letters@example.test', 'mail_from_name' => 'A considered journal',
        ]);
        $fails = NewsletterSubscriberFactory::createOne(['email' => 'fails@example.test', 'status' => NewsletterSubscriberStatus::Confirmed]);
        $succeeds = NewsletterSubscriberFactory::createOne(['email' => 'accepts@example.test', 'status' => NewsletterSubscriberStatus::Confirmed]);
        $campaign = $this->campaign();
        $this->service()->queue($campaign);
        $this->consume();
        self::assertCount(1, $this->transport()->getAcknowledged());
        self::assertSame(['fails@example.test', 'accepts@example.test'], RecordingSmtpFactory::attempts());
        self::assertCount(1, RecordingSmtpFactory::$accepted);
        $message = RecordingSmtpFactory::$accepted[0]->getOriginalMessage();
        self::assertInstanceOf(\Symfony\Component\Mime\Email::class, $message);
        self::assertSame('letters@example.test', $message->getFrom()[0]->getAddress());
        self::assertSame('A considered journal', $message->getFrom()[0]->getName());
        $campaign = $this->reload($campaign);
        self::assertSame(NewsletterCampaignStatus::Failed, $campaign->getStatus());
        self::assertSame(1, $campaign->getStats()['sent']);
        self::assertSame(1, $campaign->getStats()['failed']);
        $this->assertLedgerMatches($campaign);
        $this->service()->sendBatch((int) $campaign->getId(), [$fails->getId(), $succeeds->getId()]);
        $this->service()->queue($campaign);
        self::assertCount(2, RecordingSmtpFactory::attempts());
    }

    public function testLostDispatchIsRecoveredWithoutAddingNewAudienceMembers(): void
    {
        self::createClient();
        NewsletterSubscriberFactory::createMany(2, ['status' => NewsletterSubscriberStatus::Confirmed]);
        $campaign = $this->campaign();
        $this->service()->queue($campaign);
        $this->transport()->reset(); // Simulate broker messages lost after the DB commit.
        NewsletterSubscriberFactory::createOne(['status' => NewsletterSubscriberStatus::Confirmed]);
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new SendScheduledNewsletterCampaignsMessage());
        self::assertCount(1, $this->transport()->getSent());
        $this->consume();
        self::assertEmailCount(2);
        $campaign = $this->reload($campaign);
        self::assertSame(2, $campaign->getStats()['total']);
        $this->assertLedgerMatches($campaign);
    }

    public function testPartiallyHandledBatchReplaysOnlyPendingRecipients(): void
    {
        self::createClient();
        $recipients = NewsletterSubscriberFactory::createMany(3, ['status' => NewsletterSubscriberStatus::Confirmed]);
        $campaign = $this->campaign();
        $this->service()->queue($campaign);
        $this->service()->sendBatch((int) $campaign->getId(), [$recipients[0]->getId()]);
        self::assertEmailCount(1);
        $campaign = $this->reload($campaign);
        self::assertSame(NewsletterCampaignStatus::Sending, $campaign->getStatus());
        self::assertNull($campaign->getSentAt());
        self::assertSame(2, $campaign->getStats()['pending']);
        $this->assertLedgerMatches($campaign);
        $this->consume(); // Replays the original three-recipient batch, including its completed first row.
        self::assertEmailCount(3);
        $campaign = $this->reload($campaign);
        self::assertSame(NewsletterCampaignStatus::Sent, $campaign->getStatus());
        $this->assertLedgerMatches($campaign);
    }

    /** @return iterable<string, array{?string, bool}> */
    public static function interruptedAttempts(): iterable
    {
        yield 'recent attempt' => ['-1 minute', false];
        yield 'stale attempt' => ['-16 minutes', true];
        yield 'unknown attempt timestamp' => [null, true];
    }

    #[DataProvider('interruptedAttempts')]
    public function testInterruptedAttemptsAreNeverAutomaticallyResent(?string $when, bool $uncertain): void
    {
        self::createClient();
        $subscriber = NewsletterSubscriberFactory::createOne(['status' => NewsletterSubscriberStatus::Confirmed]);
        $campaign = $this->campaign();
        $this->service()->queue($campaign);
        // Emulate the durable pre-SMTP checkpoint left behind by an interrupted worker.
        $delivery = self::getContainer()->get(NewsletterDeliveryRepository::class)->findOneBy(['campaign' => $campaign]);
        self::assertNotNull($delivery);
        $delivery->setStatus(NewsletterDeliveryStatus::Sending)->setAttemptedAt(null === $when ? null : new \DateTimeImmutable($when));
        $stats = $campaign->getStats();
        $stats['pending'] = 0;
        $stats['sending'] = 1;
        $campaign->setStatus(NewsletterCampaignStatus::Sending)->setStats($stats);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->transport()->reset();
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new SendScheduledNewsletterCampaignsMessage());
        if ($uncertain) {
            self::assertCount(1, $this->transport()->getSent());
            $this->consume();
        } else {
            self::assertCount(0, $this->transport()->getSent());
            $this->service()->sendBatch((int) $campaign->getId(), [$subscriber->getId()]);
        }
        self::assertEmailCount(0);
        $campaign = $this->reload($campaign);
        self::assertSame($uncertain ? NewsletterCampaignStatus::Failed : NewsletterCampaignStatus::Sending, $campaign->getStatus());
        self::assertSame($uncertain ? 1 : 0, $campaign->getStats()['uncertain']);
        $this->assertLedgerMatches($campaign);
    }

    public function testSubscriberDeletionRetainsTheLedgerAndCountsASkip(): void
    {
        self::createClient();
        $subscriber = NewsletterSubscriberFactory::createOne(['status' => NewsletterSubscriberStatus::Confirmed]);
        $id = $subscriber->getId();
        $campaign = $this->campaign();
        $this->service()->queue($campaign);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->remove($subscriber);
        $em->flush();
        $em->clear();
        $this->consume();
        self::assertEmailCount(0);
        $campaign = $this->reload($campaign);
        $delivery = self::getContainer()->get(NewsletterDeliveryRepository::class)->findOneBy(['campaign' => $campaign, 'recipientId' => $id]);
        self::assertNotNull($delivery);
        self::assertNull($delivery->getSubscriber());
        self::assertSame(NewsletterDeliveryStatus::Skipped, $delivery->getStatus());
        self::assertSame(1, $campaign->getStats()['skipped']);
        $this->assertLedgerMatches($campaign);
    }

    public function testRejectedFormCannotMutateAQueuedCampaignOrLeakHtmlIntoAnotherFlush(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $this->seed();
        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($admin);
        $client->loginUser($admin);
        $campaign = $this->campaign();
        $url = '/admin/newsletter/campaigns/'.$campaign->getId().'/edit';
        $form = $client->request('GET', $url)->selectButton('Save campaign')->form();
        $form['newsletter_campaign[subject]'] = '';
        $form['newsletter_campaign[html]'] = '<p>Rejected content</p><script>alert(1)</script>';
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorNotExists('main script');
        self::assertSelectorTextContains('main', 'Rejected content');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        $campaign = $this->reload($campaign);
        self::assertSame('Delivery regression', $campaign->getSubject());
        self::assertSame('<p>A considered newsletter.</p>', $campaign->getHtml());

        $staleForm = $client->request('GET', $url)->selectButton('Save campaign')->form();
        $this->service()->queue($campaign);
        $staleForm['newsletter_campaign[subject]'] = 'Attempted queued edit';
        $client->submit($staleForm);
        self::assertResponseStatusCodeSame(409);
        $em->flush();
        $campaign = $this->reload($campaign);
        self::assertSame('Delivery regression', $campaign->getSubject());
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[name="newsletter_campaign"]');
        self::assertSelectorTextContains('main', 'Queued content is read-only');
    }

    public function testEmptyAudienceCompletesWithoutQueueingAndOversizedBatchesAreRejected(): void
    {
        self::createClient();
        $campaign = $this->campaign();
        self::assertSame(0, $this->service()->queue($campaign));
        self::assertCount(0, $this->transport()->getSent());
        self::assertSame(NewsletterCampaignStatus::Sent, $campaign->getStatus());
        $this->assertLedgerMatches($campaign);
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->sendBatch((int) $campaign->getId(), range(1, 51));
    }

    public function testAudienceFailureAfterAFlushedChunkRollsBackQueueStateAndAllRecipients(): void
    {
        self::createClient();
        NewsletterSubscriberFactory::createMany(51, ['status' => NewsletterSubscriberStatus::Confirmed]);
        $campaign = $this->campaign();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        $em->getEventManager()->addEventListener(['postPersist'], new class {
            private int $insertions = 0;

            public function postPersist(PostPersistEventArgs $event): void
            {
                if ($event->getObject() instanceof NewsletterDelivery && 51 === ++$this->insertions) {
                    throw new \RuntimeException('Injected audience persistence failure');
                }
            }
        });
        try {
            $this->service()->queue($campaign);
            self::fail('Expected the injected failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Injected audience persistence failure', $exception->getMessage());
        }
        self::assertFalse($em->isOpen());
        self::assertSame('draft', $connection->fetchOne('SELECT status FROM newsletter_campaign WHERE id = ?', [$campaign->getId()]));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM newsletter_delivery'));
        self::assertCount(0, $this->transport()->getSent());
    }

    public function testDatabaseFailureAfterSmtpAcceptancePreservesTheAttemptInsteadOfClaimingSuccess(): void
    {
        self::createClient();
        RecordingSmtpFactory::$enabled = true;
        self::getContainer()->get(DatabaseSettingsService::class)->saveGroup('smtp', ['mail_transport' => 'smtp', 'mail_host' => 'smtp.example.test']);
        $subscriber = NewsletterSubscriberFactory::createOne(['status' => NewsletterSubscriberStatus::Confirmed]);
        $campaign = $this->campaign();
        $this->service()->queue($campaign);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        $em->getEventManager()->addEventListener(['postUpdate'], new class {
            public function postUpdate(PostUpdateEventArgs $event): void
            {
                $entity = $event->getObject();
                if ($entity instanceof NewsletterDelivery && NewsletterDeliveryStatus::Sent === $entity->getStatus()) {
                    throw new \RuntimeException('Injected post-SMTP database failure');
                }
            }
        });
        try {
            $this->service()->sendBatch((int) $campaign->getId(), [$subscriber->getId()]);
            self::fail('Expected the injected failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Injected post-SMTP database failure', $exception->getMessage());
        }
        self::assertFalse($em->isOpen());
        self::assertCount(1, RecordingSmtpFactory::$accepted);
        $row = $connection->fetchAssociative('SELECT status, attempted_at, sent_at FROM newsletter_delivery WHERE campaign_id = ?', [$campaign->getId()]);
        self::assertIsArray($row);
        self::assertSame('sending', $row['status']);
        self::assertNotNull($row['attempted_at']);
        self::assertNull($row['sent_at']);
        $stats = json_decode((string) $connection->fetchOne('SELECT stats FROM newsletter_campaign WHERE id = ?', [$campaign->getId()]), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(0, $stats['sent']);
        self::assertSame(1, $stats['sending']);
    }

    public function testSchedulerCannotSendAPostponedCampaignAndUnstartedDraftsIgnoreForgedBatches(): void
    {
        self::createClient();
        $subscriber = NewsletterSubscriberFactory::createOne(['status' => NewsletterSubscriberStatus::Confirmed]);
        $campaign = $this->campaign();
        $this->service()->sendBatch((int) $campaign->getId(), [$subscriber->getId()]);
        self::assertEmailCount(0);
        $campaign->setStatus(NewsletterCampaignStatus::Scheduled)->setScheduledAt(new \DateTimeImmutable('+1 hour'));
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        self::assertSame(0, $this->service()->queue($campaign, new \DateTimeImmutable()));
        self::assertSame(NewsletterCampaignStatus::Scheduled, $campaign->getStatus());
        self::assertSame(0, $this->service()->sendDueCampaigns());
        self::assertCount(0, $this->transport()->getSent());
    }

    private function assertLedgerMatches(NewsletterCampaign $campaign): void
    {
        self::assertSame($campaign->getStats(), self::getContainer()->get(NewsletterDeliveryRepository::class)->statistics((int) $campaign->getId()));
    }

    private function campaign(): NewsletterCampaign
    {
        $campaign = (new NewsletterCampaign())->setSubject('Delivery regression')->setHtml('<p>A considered newsletter.</p>');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($campaign);
        $em->flush();

        return $campaign;
    }

    private function reload(NewsletterCampaign $campaign): NewsletterCampaign
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reloaded = self::getContainer()->get(NewsletterCampaignRepository::class)->find($campaign->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }

    private function service(): NewsletterCampaignService
    {
        return self::getContainer()->get(NewsletterCampaignService::class);
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }

    private function consume(int $limit = 1): void
    {
        $command = self::getContainer()->get('console.command.messenger_consume_messages');
        self::assertInstanceOf(Command::class, $command);
        self::assertSame(0, (new CommandTester($command))->execute([
            'receivers' => ['async'], '--limit' => $limit, '--time-limit' => 5, '--no-reset' => null,
        ], ['interactive' => false]));
    }
}
