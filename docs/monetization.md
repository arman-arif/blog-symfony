# Monetization

## Advertisements

Ads are managed at `/admin/advertisements` (`ad.manage`; Admin and Super Admin only by default).

Supported placements: `header`, `in_article`, `sidebar`, `footer`. Types: image banner, HTML creative, AdSense/network code. Each record has an active toggle, priority (higher wins), optional start/end timestamps and an optional destination URL.

`AdvertisementRenderer`:

- selects the highest-priority active ad whose schedule contains the current time;
- caches placement IDs for 5 minutes in the tag-aware `cache.content` pool (`ads` tag) and invalidates on save/toggle/delete;
- renders `front/partials/ad.html.twig` (image + destination with `rel="sponsored nofollow noopener"`, or the stored HTML/network snippet);
- inserts the in-article ad after `afterParagraph` closing paragraphs (defaults to 1) via the `article_ads` filter.

Public templates call `render_ad('header')`, `render_ad('sidebar')` (article aside) and `render_ad('footer')`. In-article ads are inserted into the sanitized article HTML.

**Trust boundary:** HTML and AdSense creatives are admin-authored raw content and are rendered as-is. Only grant `ad.manage` to trusted administrators. CSP and moderation of third-party scripts are addressed in M14.

## Sponsored content and affiliate links

- Articles have `isSponsored`, rendered as a visible “Sponsored” label and partnership disclosure on article pages and cards.
- `affiliateLinks` causes `ContentProcessor` to add `rel="sponsored nofollow noopener"` to every outbound link when the article is saved or autosaved.
- The editor exposes both toggles.

## Extension points (architecture only)

- `App\Monetization\AccessPolicyInterface` + `DefaultAccessPolicy`: premium articles (`article.isPremium`) are hidden from public queries until a policy allows access. No membership policy is shipped.
- `App\Monetization\PaymentGatewayInterface` + `DisabledPaymentGateway`: checkout/webhook contract for a future provider. No credentials, gateway, plans, subscriptions or donations are implemented.
- Premium/member-facing schemas are documented as future work rather than half-built modules.

## Tests

`MonetizationTest` covers placement priority and scheduling, cache invalidation, in-article paragraph insertion, sponsored disclosure, affiliate `rel`, premium hiding, and admin ad create/toggle/delete.
