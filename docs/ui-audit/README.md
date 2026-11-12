# M1.8 visual review

Captured from the mock preview on 2026-11-12 after fonts, enhancements and the theme settled. These are representative viewport captures, not exhaustive visual-regression baselines.

| Capture | Review focus |
|---|---|
| [Public home, 1440px light](home-desktop-light.jpg) | Editorial hierarchy, hero, navigation |
| [Article, 1024px dark](article-laptop-dark.jpg) | Dark body typography, TOC and rich content |
| [Editor, 768px light](editor-tablet-light.jpg) | Toolbar wrapping, form/editor usability |
| [Media, 360px dark](media-mobile-dark.jpg) | Mobile upload controls and local-only disclosure |
| [Analytics, 1440px dark](analytics-desktop-dark.jpg) | Chart readability and responsive cards |
| [Validation, 360px light](validation-mobile-light.jpg) | Inline errors, first-invalid focus, control boundaries |
| [Table loading, 360px dark](loading-mobile-dark.jpg) | Skeleton/loading announcement |
| [Empty form, 768px light](empty-tablet-light.jpg) | Empty-data presentation and return action |
| [Error, 1440px dark](error-desktop-dark.jpg) | Contrast, retry and simulated-error disclosure |
| [Navigation drawer, 360px dark](navigation-mobile-dark.jpg) | Overlay dimensions, reachable navigation and close control |
| [Removal confirmation, 360px light](confirmation-mobile-light.jpg) | Explicit local-only action, cancel/confirm layout |
| [404, 360px dark](404-mobile-dark.jpg) | Saved theme without a toggle, readable recovery controls |

Engineering review found no unexpected horizontal clipping, unreadable text or page overflow in these captures. Scrolled viewport captures intentionally show only part of a page; sticky navigation remains visible. Mobile tables scroll horizontally inside labelled, focusable regions. Long desktop sidebars scroll independently. The editor remains readable with disabled controls when JavaScript is unavailable.

See [the checklist](../ui-checklist.md) and [machine-readable results](m1.8-results.json) for coverage and limitations. **Human UI sign-off is still required before M2.**
