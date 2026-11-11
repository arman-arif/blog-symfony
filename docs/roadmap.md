# Development roadmap

`PROMPT.md` and `IMPLEMENTATION_PLAN.md` at the repository root are the canonical, unmodified requirements. Their milestones run in order. The user's instruction permits continuing across milestones in this session, but does not waive the UI-before-backend gate.

1. M0: inspect, install a minimal PHP-only Symfony foundation, establish QA and document decisions.
2. M1.1: editorial tokens, accessible components, styleguide and mock data contract.
3. M1.2–M1.4: complete the public reading/discovery experience and error states.
4. M1.5–M1.7b: build the separate administrative workspace and every mock management screen.
5. M1.8: page/state/accessibility audit, crawler tests, **human UI review before M2**.
6. M2–M4: establish backend services, MySQL schema and verified identity/permissions.
7. M5–M8: integrate CMS, safe media, published content and reader engagement.
8. M9–M13: SEO, email, reporting, monetization extension points and settings.
9. M14–M16: harden, measure, consolidate coverage and document production deployment.

Each completed milestone is verified, recorded in `PROJECT_PROGRESS.md` and committed independently. Partial work stays In Progress; unavailable prerequisites are reported rather than replaced with misleading completion claims.
