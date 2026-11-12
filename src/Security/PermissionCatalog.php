<?php

declare(strict_types=1);

namespace App\Security;

/** Canonical permission and default-role catalog used by the idempotent sync command. */
final class PermissionCatalog
{
    /** @return array<string, array{label: string, group: string}> */
    public static function permissions(): array
    {
        return [
            'admin.access' => ['label' => 'Access the workspace', 'group' => 'Workspace'],
            'profile.manage.own' => ['label' => 'Manage own profile', 'group' => 'Workspace'],
            'article.create' => ['label' => 'Create articles', 'group' => 'Editorial'],
            'article.edit.own' => ['label' => 'Edit own articles', 'group' => 'Editorial'],
            'article.edit.any' => ['label' => 'Edit any article', 'group' => 'Editorial'],
            'article.delete.own' => ['label' => 'Delete own articles', 'group' => 'Editorial'],
            'article.delete.any' => ['label' => 'Delete any article', 'group' => 'Editorial'],
            'article.submit' => ['label' => 'Submit for review', 'group' => 'Editorial'],
            'article.review' => ['label' => 'Review submissions', 'group' => 'Editorial'],
            'article.publish' => ['label' => 'Publish articles', 'group' => 'Editorial'],
            'article.schedule' => ['label' => 'Schedule articles', 'group' => 'Editorial'],
            'category.manage' => ['label' => 'Manage categories', 'group' => 'Editorial'],
            'tag.manage' => ['label' => 'Manage tags', 'group' => 'Editorial'],
            'media.upload' => ['label' => 'Upload media', 'group' => 'Editorial'],
            'media.manage.any' => ['label' => 'Manage any media', 'group' => 'Editorial'],
            'comment.moderate' => ['label' => 'Moderate comments', 'group' => 'Editorial'],
            'newsletter.manage' => ['label' => 'Manage newsletter', 'group' => 'Audience'],
            'analytics.view' => ['label' => 'View analytics', 'group' => 'Audience'],
            'page.manage' => ['label' => 'Manage pages', 'group' => 'Site'],
            'menu.manage' => ['label' => 'Manage menus', 'group' => 'Site'],
            'ad.manage' => ['label' => 'Manage advertisements', 'group' => 'Site'],
            'user.manage' => ['label' => 'Manage users', 'group' => 'System'],
            'role.manage' => ['label' => 'Manage roles', 'group' => 'System'],
            'permission.manage' => ['label' => 'Manage permissions', 'group' => 'System'],
            'settings.general.manage' => ['label' => 'Manage general settings', 'group' => 'System'],
            'settings.appearance.manage' => ['label' => 'Manage appearance', 'group' => 'System'],
            'settings.seo.manage' => ['label' => 'Manage SEO', 'group' => 'System'],
            'settings.smtp.manage' => ['label' => 'Manage SMTP', 'group' => 'System'],
            'settings.system.manage' => ['label' => 'Manage system settings', 'group' => 'System'],
            'settings.code.manage' => ['label' => 'Manage custom code', 'group' => 'System'],
            'activity_log.view' => ['label' => 'View activity logs', 'group' => 'System'],
        ];
    }

    /** @return array<string, array{name: string, description: string, permissions: list<string>}> */
    public static function roles(): array
    {
        $all = array_keys(self::permissions());

        return [
            'ROLE_SUPER_ADMIN' => [
                'name' => 'Super Administrator',
                'description' => 'Full system access, including roles, permissions and sensitive settings.',
                'permissions' => $all,
            ],
            'ROLE_ADMIN' => [
                'name' => 'Administrator',
                'description' => 'Manages content, users, audience and site settings except sensitive system controls.',
                'permissions' => array_values(array_diff($all, [
                    'role.manage', 'permission.manage', 'settings.smtp.manage', 'settings.system.manage', 'settings.code.manage',
                ])),
            ],
            'ROLE_EDITOR' => [
                'name' => 'Editor',
                'description' => 'Manages the editorial pipeline, taxonomy and comment moderation.',
                'permissions' => [
                    'admin.access', 'profile.manage.own', 'article.create', 'article.edit.own', 'article.edit.any',
                    'article.delete.own', 'article.delete.any', 'article.submit', 'article.review', 'article.publish',
                    'article.schedule', 'category.manage', 'tag.manage', 'media.upload', 'media.manage.any',
                    'comment.moderate', 'analytics.view',
                ],
            ],
            'ROLE_AUTHOR' => [
                'name' => 'Author',
                'description' => 'Writes and submits own articles and moderates comments on them.',
                'permissions' => [
                    'admin.access', 'profile.manage.own', 'article.create', 'article.edit.own', 'article.delete.own',
                    'article.submit', 'media.upload', 'comment.moderate', 'analytics.view',
                ],
            ],
            'ROLE_CONTRIBUTOR' => [
                'name' => 'Contributor',
                'description' => 'Creates drafts and submits them for editorial review.',
                'permissions' => [
                    'admin.access', 'profile.manage.own', 'article.create', 'article.edit.own', 'article.delete.own',
                    'article.submit', 'media.upload',
                ],
            ],
        ];
    }
}
