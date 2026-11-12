<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\NewsletterCampaign;
use App\Enum\NewsletterCampaignStatus;
use App\Enum\NewsletterSubscriberStatus;
use App\Message\SendScheduledNewsletterCampaignsMessage;
use App\Repository\NewsletterCampaignRepository;
use App\Repository\NewsletterSubscriberRepository;
use App\Repository\UserRepository;
use App\Tests\Support\InMemorySettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;

final class NewsletterTest extends FrontTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        InMemorySettingsService::resetOverride();
    }

    protected function tearDown(): void
    {
        InMemorySettingsService::resetOverride();
        parent::tearDown();
    }

    public function testDoubleOptInConfirmationAndUnsubscribeFlow(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->setServerParameter('REMOTE_ADDR', $this->randomIp());

        $this->subscribe($client, 'new.reader@example.test');
        self::assertResponseStatusCodeSame(302);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        $client->followRedirect();
        self::assertInstanceOf(Email::class, $email);
        self::assertStringContainsString('Confirm your Fieldnotes newsletter', (string) $email->getSubject());
        $body = html_entity_decode((string) $email->getHtmlBody());
        self::assertMatchesRegularExpression('#/newsletter/confirm/[a-f0-9]{64}#', $body);
        preg_match('#/newsletter/confirm/[a-f0-9]{64}#', $body, $matches);

        $subscriber = self::getContainer()->get(NewsletterSubscriberRepository::class)->findOneByEmail('new.reader@example.test');
        self::assertNotNull($subscriber);
        self::assertSame(NewsletterSubscriberStatus::Pending, $subscriber->getStatus());

        $client->request('GET', $matches[0]);
        self::assertResponseStatusCodeSame(302);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'subscription is confirmed');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $subscriber = self::getContainer()->get(NewsletterSubscriberRepository::class)->findOneByEmail('new.reader@example.test');
        self::assertSame(NewsletterSubscriberStatus::Confirmed, $subscriber?->getStatus());
        self::assertNotNull($subscriber?->getConfirmedAt());

        $unsubscribe = $this->firstMatch($body, '#/newsletter/unsubscribe/[a-f0-9]{64}#');
        $client->request('GET', $unsubscribe);
        self::assertResponseStatusCodeSame(302);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'unsubscribed');
        $em->clear();
        self::assertSame(NewsletterSubscriberStatus::Unsubscribed, self::getContainer()->get(NewsletterSubscriberRepository::class)->findOneByEmail('new.reader@example.test')?->getStatus());
    }

    public function testSingleOptInConfirmsImmediately(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->setServerParameter('REMOTE_ADDR', $this->randomIp());
        InMemorySettingsService::setOverride('newsletter.double_opt_in', false);

        $this->subscribe($client, 'single.optin@example.test');
        self::assertResponseStatusCodeSame(302);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertStringContainsString('Welcome to the Fieldnotes Sunday letter', (string) $email->getSubject());

        $subscriber = self::getContainer()->get(NewsletterSubscriberRepository::class)->findOneByEmail('single.optin@example.test');
        self::assertSame(NewsletterSubscriberStatus::Confirmed, $subscriber?->getStatus());
    }

    public function testInvalidEmailIsRejected(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->setServerParameter('REMOTE_ADDR', $this->randomIp());

        $this->subscribe($client, 'not-an-email');
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'valid email');
        self::assertNull(self::getContainer()->get(NewsletterSubscriberRepository::class)->findOneByEmail('not-an-email'));
    }

    public function testSubscribeRateLimitRejectsBursts(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->setServerParameter('REMOTE_ADDR', $this->randomIp());

        for ($index = 1; $index <= 6; ++$index) {
            $this->subscribe($client, 'burst'.$index.'@example.test');
            $client->followRedirect();
        }
        self::assertSelectorTextContains('[role="status"]', 'Too many subscription attempts');
    }

    public function testAdminSubscriberListSearchStatusAndExport(): void
    {
        $client = self::createClient();
        $this->seed();
        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/newsletter');
        self::assertResponseIsSuccessful();
        file_put_contents('/tmp/nl-admin.html', (string) $client->getResponse()->getContent());
        self::assertSelectorTextContains('main', 'reader1@example.test');

        $client->request('GET', '/admin/newsletter?q=reader3');
        self::assertSelectorTextContains('main', 'reader3@example.test');
        self::assertSelectorNotExists('tbody tr[data-id="1"]');

        $pending = self::getContainer()->get(NewsletterSubscriberRepository::class)->findOneBy(['status' => NewsletterSubscriberStatus::Pending]);
        self::assertNotNull($pending);
        $crawler = $client->request('GET', '/admin/newsletter');
        $form = $crawler->filter('form[action$="/subscribers/'.$pending->getId().'/confirmed"]')->form();
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertSame(NewsletterSubscriberStatus::Confirmed, self::getContainer()->get(NewsletterSubscriberRepository::class)->find($pending->getId())?->getStatus());

        $client->request('GET', '/admin/newsletter/export');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/csv', (string) $client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('newsletter-subscribers-', (string) $client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('reader1@example.test', (string) $client->getResponse()->getContent());
    }

    public function testCampaignDraftTestSendAndBatchDelivery(): void
    {
        $client = self::createClient();
        $this->seed();
        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($admin);
        $client->loginUser($admin);
        $confirmed = self::getContainer()->get(NewsletterSubscriberRepository::class)->countByStatus(NewsletterSubscriberStatus::Confirmed);

        $crawler = $client->request('GET', '/admin/newsletter/campaigns/new');
        $form = $crawler->selectButton('Save campaign')->form();
        $form['newsletter_campaign[subject]'] = 'A considered week';
        $form['newsletter_campaign[html]'] = '<p>Three quiet stories for your week.</p>';
        $form['newsletter_campaign[status]'] = 'draft';
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);
        $campaign = self::getContainer()->get(NewsletterCampaignRepository::class)->findOneBy(['subject' => 'A considered week']);
        self::assertNotNull($campaign);
        self::assertSame(NewsletterCampaignStatus::Draft, $campaign->getStatus());

        $crawler = $client->request('GET', '/admin/newsletter/campaigns/'.$campaign->getId().'/edit');
        file_put_contents('/tmp/nl-campaign.html', (string) $client->getResponse()->getContent());
        self::assertResponseIsSuccessful('Campaign edit failed: '.$client->getResponse()->getStatusCode());
        $testForm = $crawler->filter('form[action$="/test"]')->form();
        $testForm['email'] = 'tester@example.test';
        $client->submit($testForm);
        self::assertResponseStatusCodeSame(302);
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertStringContainsString('[Test] A considered week', (string) $message->getSubject());

        $crawler = $client->request('GET', '/admin/newsletter/campaigns/'.$campaign->getId().'/edit');
        $client->submit($crawler->filter('form[action$="/send"]')->form());
        self::assertResponseStatusCodeSame(302);
        self::assertEmailCount($confirmed);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $campaign = self::getContainer()->get(NewsletterCampaignRepository::class)->find($campaign->getId());
        self::assertSame(NewsletterCampaignStatus::Sent, $campaign?->getStatus());
        self::assertSame($confirmed, $campaign?->getStats()['sent']);
    }

    public function testScheduledCampaignIsQueuedWhenDue(): void
    {
        $client = self::createClient();
        $this->seed();
        $campaign = (new NewsletterCampaign())
            ->setSubject('Scheduled letter')
            ->setHtml('<p>Arriving right on time.</p>')
            ->setStatus(NewsletterCampaignStatus::Scheduled)
            ->setScheduledAt(new \DateTimeImmutable('-1 minute'));
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($campaign);
        $em->flush();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(new SendScheduledNewsletterCampaignsMessage());

        $em->clear();
        self::assertSame(NewsletterCampaignStatus::Sent, self::getContainer()->get(NewsletterCampaignRepository::class)->find($campaign->getId())?->getStatus());
        self::assertEmailCount(self::getContainer()->get(NewsletterSubscriberRepository::class)->countByStatus(NewsletterSubscriberStatus::Confirmed));
    }

    private function subscribe(object $client, string $email): void
    {
        $crawler = $client->request('GET', '/');
        $form = $crawler->filter('form[action="/newsletter/subscribe"]')->first()->form();
        $form['email'] = $email;
        $client->submit($form);
    }

    private function firstMatch(string $body, string $pattern): string
    {
        preg_match($pattern, $body, $matches);
        self::assertNotEmpty($matches[0] ?? null);

        return $matches[0];
    }

    private function randomIp(): string
    {
        return '10.20.'.random_int(0, 250).'.'.random_int(1, 250);
    }
}
