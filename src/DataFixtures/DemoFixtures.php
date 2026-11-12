<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ActivityLog;
use App\Entity\Advertisement;
use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\Media;
use App\Entity\Menu;
use App\Entity\MenuItem;
use App\Entity\NewsletterCampaign;
use App\Entity\NewsletterSubscriber;
use App\Entity\Page;
use App\Entity\Permission;
use App\Entity\Redirect;
use App\Entity\Role;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\AdvertisementPlacement;
use App\Enum\AdvertisementType;
use App\Enum\ArticleStatus;
use App\Enum\ArticleVisibility;
use App\Enum\CommentStatus;
use App\Enum\MenuItemType;
use App\Enum\MenuLocation;
use App\Enum\NewsletterCampaignStatus;
use App\Enum\NewsletterSubscriberStatus;
use App\Enum\PageStatus;
use App\Mock\MockDataProvider;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Reproduces the M1 mock records as real entities for integration milestones. */
final class DemoFixtures extends Fixture
{
    private const ROLE_CODES = ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_EDITOR', 'ROLE_AUTHOR', 'ROLE_CONTRIBUTOR'];

    public function __construct(
        private readonly MockDataProvider $provider,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $permissions = $this->loadPermissions($manager);
        $roles = $this->loadRoles($manager, $permissions);
        $users = $this->loadUsers($manager, $roles);
        $media = $this->loadMedia($manager, $users);
        $categories = $this->loadCategories($manager, $media);
        $tags = $this->loadTags($manager);
        $articles = $this->loadArticles($manager, $users, $media, $categories, $tags);
        $this->loadComments($manager, $articles);
        $this->loadPages($manager, $users);
        $this->loadMenus($manager);
        $this->loadAdvertisements($manager, $media);
        $this->loadSubscribers($manager);
        $this->loadCampaigns($manager);
        $this->loadActivityLogs($manager, $users);
        $this->loadRedirects($manager);

        $manager->flush();
    }

    /** @return array<string, Permission> */
    private function loadPermissions(ObjectManager $manager): array
    {
        $permissions = [];
        foreach ($this->provider->permissions() as $row) {
            $permission = (new Permission())
                ->setCode($row['code'])
                ->setLabel($row['label'])
                ->setGroupName($row['group']);
            $manager->persist($permission);
            $permissions[$row['code']] = $permission;
        }

        return $permissions;
    }

    /**
     * @param array<string, Permission> $permissions
     *
     * @return array<int, Role>
     */
    private function loadRoles(ObjectManager $manager, array $permissions): array
    {
        $roles = [];
        foreach ($this->provider->roles() as $index => $row) {
            $role = (new Role())
                ->setName($row['name'])
                ->setCode(self::ROLE_CODES[$index])
                ->setDescription($row['description'])
                ->setIsSystem(true);
            foreach ($row['permissions'] as $code) {
                $role->addPermission($permissions[$code]);
            }
            $manager->persist($role);
            $roles[$row['id']] = $role;
        }

        return $roles;
    }

    /**
     * @param array<int, Role> $roles
     *
     * @return array<int, User>
     */
    private function loadUsers(ObjectManager $manager, array $roles): array
    {
        $users = [];
        foreach ($this->provider->users() as $index => $row) {
            $user = (new User())
                ->setEmail($row['email'])
                ->setDisplayName($row['displayName'])
                ->setJobTitle($row['jobTitle'])
                ->setBio($row['bio'])
                ->setIsActive($row['isActive'])
                ->setIsVerified($row['isVerified'])
                ->setLastLoginAt($row['lastLoginAt'])
                ->addAssignedRole($roles[$row['roles'][0]['id']]);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'fieldnotes-demo'));
            $manager->persist($user);
            $users[$row['id']] = $user;
        }

        return $users;
    }

    /**
     * @param array<int, User> $users
     *
     * @return array<int, Media>
     */
    private function loadMedia(ObjectManager $manager, array $users): array
    {
        $media = [];
        foreach ($this->provider->media() as $index => $row) {
            $item = (new Media())
                ->setFilename($row['filename'])
                ->setOriginalName($row['originalName'])
                ->setMimeType($row['mimeType'])
                ->setSize($row['size'])
                ->setWidth($row['width'])
                ->setHeight($row['height'])
                ->setAlt($row['alt'])
                ->setCaption($row['caption'])
                ->setStorageKey($row['url'])
                ->setVariants(['thumbnail' => $row['url'], 'medium' => $row['url'], 'large' => $row['url']])
                ->setUploadedBy($users[1 + $index % 6]);
            $manager->persist($item);
            $media[$row['id']] = $item;
        }

        return $media;
    }

    /**
     * @param array<int, Media> $media
     *
     * @return array<int, Category>
     */
    private function loadCategories(ObjectManager $manager, array $media): array
    {
        $categories = [];
        foreach ($this->provider->categories() as $row) {
            $category = (new Category())
                ->setName($row['name'])
                ->setDescription($row['description'])
                ->setImage($media[$row['image']['id']]);
            $category->getSeo()->setTitle($row['seo']['title'])->setNoindex($row['seo']['noindex']);
            $manager->persist($category);
            $categories[$row['id']] = $category;
        }
        $manager->flush();

        foreach ($this->provider->categories() as $row) {
            if (null !== $row['parent']) {
                $categories[$row['id']]->setParent($categories[$row['parent']['id']]);
            }
        }
        $manager->flush();

        return $categories;
    }

    /** @return array<int, Tag> */
    private function loadTags(ObjectManager $manager): array
    {
        $tags = [];
        foreach ($this->provider->tags() as $row) {
            $tag = (new Tag())->setName($row['name'])->setDescription($row['description']);
            $tag->getSeo()->setTitle($row['seo']['title'])->setNoindex($row['seo']['noindex']);
            $manager->persist($tag);
            $tags[$row['id']] = $tag;
        }

        return $tags;
    }

    /**
     * @param array<int, User>     $users
     * @param array<int, Media>    $media
     * @param array<int, Category> $categories
     * @param array<int, Tag>      $tags
     *
     * @return array<int, Article>
     */
    private function loadArticles(ObjectManager $manager, array $users, array $media, array $categories, array $tags): array
    {
        $articles = [];
        foreach ($this->provider->articles() as $row) {
            $article = (new Article())
                ->setTitle($row['title'])
                ->setSubtitle($row['subtitle'])
                ->setExcerpt($row['excerpt'])
                ->setContent($this->articleContent($row['title'], $row['excerpt']))
                ->setStatus(ArticleStatus::from($row['status']))
                ->setVisibility(ArticleVisibility::from($row['visibility']))
                ->setPublishedAt($row['publishedAt'])
                ->setScheduledAt($row['scheduledAt'])
                ->setAuthor($users[$row['author']['id']])
                ->setFeaturedImage($media[$row['featuredImage']['id']])
                ->setAllowComments($row['allowComments'])
                ->setReadingTime($row['readingTime'])
                ->setViewCount($row['viewCount'])
                ->setLikeCount($row['likeCount'])
                ->addCategory($categories[$row['categories'][0]['id']]);
            foreach ($row['tags'] as $tag) {
                $article->addTag($tags[$tag['id']]);
            }
            $article->getSeo()
                ->setTitle($row['seo']['title'])
                ->setDescription($row['seo']['description'] ?: $row['excerpt'])
                ->setFocusKeyword($row['seo']['focusKeyword'])
                ->setNoindex($row['seo']['noindex']);
            $manager->persist($article);
            $articles[$row['id']] = $article;
        }

        return $articles;
    }

    /** @param array<int, Article> $articles */
    private function loadComments(ObjectManager $manager, array $articles): void
    {
        $comments = [];
        $records = $this->provider->moderationComments();
        foreach ($records as $row) {
            if (null !== $row['parent']) {
                continue;
            }
            $comment = (new Comment())
                ->setArticle($articles[1])
                ->setGuestName($row['guestName'])
                ->setGuestEmail($row['guestEmail'])
                ->setContent($row['content'])
                ->setStatus(CommentStatus::from($row['status']))
                ->setReportCount($row['reportCount']);
            $manager->persist($comment);
            $comments[$row['id']] = $comment;
        }
        $manager->flush();

        foreach ($records as $row) {
            if (null === $row['parent']) {
                continue;
            }
            $comment = (new Comment())
                ->setArticle($articles[1])
                ->setParent($comments[$row['parent']['id']])
                ->setGuestName($row['guestName'])
                ->setGuestEmail($row['guestEmail'])
                ->setContent($row['content'])
                ->setStatus(CommentStatus::from($row['status']))
                ->setReportCount($row['reportCount']);
            $manager->persist($comment);
        }
    }

    /** @param array<int, User> $users */
    private function loadPages(ObjectManager $manager, array $users): void
    {
        foreach ($this->provider->pages() as $slug => $row) {
            $content = '';
            foreach ($row['sections'] as $section) {
                $content .= '<h2>'.htmlspecialchars($section['title'], \ENT_QUOTES).'</h2>';
                $content .= '<p>'.htmlspecialchars($section['text'], \ENT_QUOTES).'</p>';
            }
            $page = (new Page())
                ->setTitle($row['title'])
                ->setSlug($slug)
                ->setContent($content)
                ->setStatus(PageStatus::Published)
                ->setPublishedAt(new \DateTimeImmutable('2026-10-01'))
                ->setAuthor($users[1]);
            $page->getSeo()
                ->setTitle($row['title'])
                ->setDescription($row['description']);
            $manager->persist($page);
        }
    }

    private function loadMenus(ObjectManager $manager): void
    {
        foreach ($this->provider->menus() as $menuRow) {
            $menu = (new Menu())->setName($menuRow['name'])->setLocation(MenuLocation::from($menuRow['location']));
            $manager->persist($menu);
            $manager->flush();

            $items = [];
            foreach ($menuRow['items'] as $itemRow) {
                $item = (new MenuItem())
                    ->setMenu($menu)
                    ->setLabel($itemRow['label'])
                    ->setType(MenuItemType::from($itemRow['type']))
                    ->setTarget($itemRow['target'])
                    ->setPosition($itemRow['position']);
                if (null !== $itemRow['parent']) {
                    $item->setParent($items[$itemRow['parent']['id']]);
                }
                $manager->persist($item);
                $items[$itemRow['id']] = $item;
            }
        }
    }

    /** @param array<int, Media> $media */
    private function loadAdvertisements(ObjectManager $manager, array $media): void
    {
        foreach ($this->provider->advertisements() as $row) {
            $ad = (new Advertisement())
                ->setName($row['name'])
                ->setPlacement(AdvertisementPlacement::from($row['placement']))
                ->setType(AdvertisementType::from($row['type']))
                ->setContent('' === $row['content'] ? null : $row['content'])
                ->setImage($media[$row['image']['id']])
                ->setStartsAt($row['startsAt'])
                ->setEndsAt($row['endsAt'])
                ->setIsActive($row['isActive'])
                ->setPriority($row['priority']);
            $manager->persist($ad);
        }
    }

    private function loadSubscribers(ObjectManager $manager): void
    {
        $statuses = [
            'active' => NewsletterSubscriberStatus::Confirmed,
            'unconfirmed' => NewsletterSubscriberStatus::Pending,
            'unsubscribed' => NewsletterSubscriberStatus::Unsubscribed,
        ];
        foreach ($this->provider->subscribers() as $row) {
            $subscriber = (new NewsletterSubscriber())
                ->setEmail($row['email'])
                ->setStatus($statuses[$row['status']])
                ->setToken(bin2hex(random_bytes(16)))
                ->setConfirmedAt($row['confirmedAt'])
                ->setUnsubscribedAt($row['unsubscribedAt']);
            $manager->persist($subscriber);
        }
    }

    private function loadCampaigns(ObjectManager $manager): void
    {
        foreach ($this->provider->campaigns() as $row) {
            $campaign = (new NewsletterCampaign())
                ->setSubject($row['subject'])
                ->setHtml($row['html'])
                ->setStatus(NewsletterCampaignStatus::from($row['status']))
                ->setScheduledAt($row['scheduledAt'])
                ->setSentAt($row['sentAt'])
                ->setStats($row['stats']);
            $manager->persist($campaign);
        }
    }

    /** @param array<int, User> $users */
    private function loadActivityLogs(ObjectManager $manager, array $users): void
    {
        foreach ($this->provider->activityLogs() as $row) {
            $log = (new ActivityLog())
                ->setUser($users[$row['user']['id']])
                ->setAction($row['action'])
                ->setSubjectType($row['subjectType'])
                ->setSubjectId($row['subjectId'])
                ->setContext($row['context']);
            $manager->persist($log);
        }
    }

    private function loadRedirects(ObjectManager $manager): void
    {
        foreach ($this->provider->settingsPreview()['seo']['redirects'] as $row) {
            $manager->persist((new Redirect())
                ->setSourcePath($row['sourcePath'])
                ->setTargetUrl($row['targetUrl'])
                ->setStatusCode($row['statusCode'])
                ->setHits($row['hits']));
        }
    }

    private function articleContent(string $title, string $excerpt): string
    {
        $safeTitle = htmlspecialchars($title, \ENT_QUOTES);
        $safeExcerpt = htmlspecialchars($excerpt, \ENT_QUOTES);

        return <<<HTML
<p>{$safeExcerpt}</p>
<h2>Paying attention on purpose</h2>
<p>Good work rarely arrives in a straight line. It grows from observation, conversation and the willingness to revise what we thought we knew.</p>
<blockquote><p>Attention is the beginning of everything worth making.</p></blockquote>
<h2>What we learned</h2>
<ul><li>Start with a smaller question.</li><li>Leave room for the unexpected.</li><li>Share the work before it feels finished.</li></ul>
<p>The full story, <em>{$safeTitle}</em>, is part of our continuing exploration of thoughtful work and everyday life.</p>
<pre><code>Make a little room. Notice what happens next.</code></pre>
<table><thead><tr><th>Practice</th><th>What it changes</th></tr></thead><tbody><tr><td>Notice</td><td>More useful questions</td></tr><tr><td>Revise</td><td>Clearer work</td></tr></tbody></table>
HTML;
    }
}
