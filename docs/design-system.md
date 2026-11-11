# Fieldnotes visual system

- Forest primary / terracotta secondary; warm paper backgrounds and restrained borders. Runtime palette values use CSS RGB variables, already compatible with future appearance settings.
- Newsreader variable font for editorial headings, DM Sans for interface text. Both self-hosted from Google Fonts under the SIL Open Font License; copies are in `assets/fonts/*-OFL.txt`.
- Mobile-first standard Tailwind breakpoints (640 / 768 / 1024 / 1280 / 1536), 4px spacing scale, 8px control and 16px card radius, low-contrast layered shadows.
- Minimum 44px default control targets; visible offset focus outlines, labeled forms, semantic tables, native dialog focus containment, keyboard dropdowns and tabs, reduced-motion support.
- Native import maps are the supported browser baseline. All runtime fonts, CSS, JavaScript, icons and illustrations are local. `assets/icons/sprite.svg` contains original simple line icons.
- `/_styleguide` is available only in dev/test. It shows every reusable primitive, feedback state and light/dark treatment. Forms and confirmations are deliberately preview-only.
