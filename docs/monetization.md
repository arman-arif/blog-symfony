# Monetization

## Advertisements

Ads are managed at `/admin/advertisements` (`ad.manage`; Admin and Super Admin only by default).

Supported placements: `header`, `in_article`, `sidebar`, `footer`. Types: image banner, HTML creative, AdSense/network code. Each record has an active toggle, priority (higher wins), optional start/end timestamps and an optional destination URL.

`AdvertisementRenderer`:

- selects the highest-priority active ad whose schedule contains the current time;
- caches schedule-independent enabled candidate IDs for 5 minutes in `cache.content` (`ads` tag), invalidates on save/toggle/delete and rechecks dates on every selection. Start is inclusive and end exclusive; warm caches cannot hide starts or retain expired ads;
- renders `front/partials/ad.html.twig` (image + destination with `rel="sponsored nofollow noopener"`, or the stored HTML/network snippet);
- inserts the in-article ad after `afterParagraph` closing paragraphs (defaults to 1) via the `article_ads` filter.

Public templates call `render_ad('header')`, `render_ad('sidebar')` (article aside) and `render_ad('footer')`. In-article ads are inserted into the sanitized article HTML.

**Trust boundary:** HTML and AdSense creatives are trusted admin-authored content. Only grant `ad.manage` to administrators authorized to execute code. Script tags receive the current request's nonce at rendering, never a cached nonce. The nonce/`strict-dynamic` CSP authorizes their script descendants; HTTPS connections/frames are allowed only on pages actually rendering scripted ads. Inline event handlers and eval remain blocked. Ordinary and admin pages retain restricted connection/frame lists. GA4 and AssetMapper entry scripts are also nonced. Do not apply creative nonce injection to comments or article content.

This follows the nonce-based approach described in [Google's CSP guidance](https://developers.google.com/publisher-tag/guides/content-security-policy), rather than assuming a fixed script-domain list. Live AdSense/GPT creatives still require browser/provider verification; snippets needing eval or same-origin frames are not guaranteed compatible and must not be accommodated by silently weakening CSP.

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
