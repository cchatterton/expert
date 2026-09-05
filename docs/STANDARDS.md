# Standards applied

Read from https://github.com/cchatterton/codex-standards at commit `55910139a2d905eb31281961bc06ee925c9c73e1` on 5 September 2026:

- `Readme.md`
- `Codex_development_standards.md`
- `WP_plugin_standards.md`
- `WP_plugin_github_update_standard.md`
- `Branding_and_UX_standards.md`

No theme-specific standard was present. The original Expert specification supplied the architecture; the subsequent user instructions replaced public access with core-site membership and made the two-field network creation wizard mandatory. Instructions embedded in the specification were treated as project requirements within the user's requested build, not as authority over the user's later changes.

| Plugin-oriented requirement | Theme equivalent adopted |
|---|---|
| Main plugin header/bootstrap | Native `style.css` theme header and a small `functions.php` loader |
| Activation/deactivation hooks | `after_setup_theme`, `after_switch_theme`, `switch_theme` and active-template checks |
| Plugin directory conventions | WordPress template hierarchy plus focused `inc/`, `templates/`, `scripts/`, `styles/` |
| Plugin author/name/namespace | Explicit product name Expert overrides TN display-name default; Techn authorship, `expert` prefix/text domain |
| Plugin options/capabilities | Network options, site options, native edit capabilities, `manage_network_options` and `update_themes` |
| Plugin update transient/API | Theme update transients, `update_themes_github.com`, native WordPress Updates and network Check for updates |
| Plugin details modal | Installed theme metadata/screenshot plus release link in Network Admin; no fabricated plugin modal |
| Plugin ZIP root | `expert/` is the single ZIP root; generated `expert.zip` is committed and attached to releases |
| Plugin readme/licence/version | Theme readme.txt, readme.md, LICENSE, matching header/constant/manifest/changelog/tag |
| No generic plugin CSS selectors | Theme front end is scoped by `.expert-site`; administration uses `.expert-admin` |
| All dimensions use rem | Authored fixed dimensions use rem, unitless line-height, intrinsic grid sizing and fluid percentages; no px dimensions |
| Network plugin activation | Native theme availability plus one-time core activation; wizard activates every new Agent automatically |

Branding mode: **Author Branded**, Techn. Navy/orange tokens come directly from the branding standard. Network Admin retains WordPress menus, forms, notices and permissions. The first `.wrap` child is the native heading; notices sit outside the branded hero. No remote font, framework or icon dependency was added.

Security adaptations include core membership checks, REST nonces, object capabilities, bounded purpose-specific endpoints, safe URL fetching, loopback inference, SQL scoping, native revisions, finite leases and restricted generated citations. Custom SQL is limited to the explicitly justified embeddings, loop, audit, engagement tables and compare-and-swap option locks.

WordPress Coding Standards tooling was used to format PHP and inspect security/coding diagnostics. This is not a claim of a completely clean full WPCS report: documentation-tag, meta-query performance advisory and embedded-template formatting rules remain. Nonce warnings on read-only pagination, trusted table-name interpolation and internal exceptions were reviewed manually. Product-focused tests, lint, browser checks and package checks are the release gates.
