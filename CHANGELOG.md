# Changelog

## 1.9.0 - 2026-09-06

- The chat bubble now returns to the main site while a robot icon and Agent name return to the current Agent home.
- Removed escaped archive-title markup from visible headings.
- Replaced front-end implementation language with natural Agent activity and growth states.

## 1.8.0 - 2026-09-06

- Unanswered useful questions enter the research backlog.
- Each frequency-controlled cycle researches the highest-priority backlog question first and tests its relevance to the Agent boundary.
- When no suitable backlog remains, the Agent selects an independent learning angle.
- Added autonomous public-web discovery and bounded source retrieval before local two-source synthesis.

## 1.7.0 - 2026-09-06

- Start local AI automatically for signed-in members whenever an Agent page is visible.
- Added model download and preparation progress.
- Reconnect after navigation, connectivity changes, page restoration and recoverable inference failures.
- Let multiple active devices claim independent, rotated learning work so they increase aggregate learning throughput.
- Added per-device cooldowns, locks and title-based update reuse to reduce duplicate learning output.

## 1.6.2 - 2026-09-06

- Added automatic phone-compatible CPU/WASM inference when WebGPU cannot provide a compatible adapter.
- Kept chat and learning private and browser-local on the fallback path.

## 1.6.1 - 2026-09-06

- Clear legacy server-runtime failures after upgrading to browser-local AI.
- Show Growth and Waiting for local AI instead of a stale Error and Needs attention state.

## 1.6.0 - 2026-09-06

- Added explicit per-device consent for private browser-local AI.
- Added cached WebGPU inference for grounded Agent chat without an AI-provider account.
- Added leased browser learning jobs that synthesise at least two stored evidence records and commit only server-validated citations.
- Kept durable Agent memory, FAQs, backlog, revisions, timelines and loop telemetry in WordPress.
- Replaced misleading runtime states with Waiting for local AI, Learning now and Queued for local learning.
- Disabled obsolete server-runtime cron dispatch while retaining its code for upgrade compatibility.

## 1.5.0 - 2026-09-06

- Added dark theme-colour metadata for supported mobile browsers and installed web-app surfaces.
- Added a persistent Agent header signal for Learning now, next scheduled learning, Paused, Ready to learn and Needs attention states.
- Derived active learning from the existing atomic loop lease without changing scheduler behavior.

## 1.4.0 - 2026-09-06

- Changed the network home mark to a solid chat bubble.
- Removed Manage Agents and Log out from the front-end navigation.
- Kept management and account controls in the native WordPress administration surfaces.

## 1.3.0 - 2026-09-06

- Replaced the letter mark with a distinct Expert chat bubble.
- Made the brand control return to the main network homepage from every Agent site.
- Removed the redundant Agent directory navigation item.
- Renamed Open questions to Backlog.

## 1.2.0 - 2026-09-06

- Made the animated core homepage and limited Agent catalogue public.
- Kept Agent pages, alternate views, archives, feeds, menus, comments, REST routes and all interactions behind network membership.
- Replaced public Agent links with a login route and added a clear header login action.
- Allowed public indexing only for the core homepage while retaining noindex protection elsewhere.

## 1.1.0 - 2026-09-06

- Updated Expert's complete front-end visual system while retaining its existing behavior and distinct identity.
- Added the atomic network animation, with one orbit and one particle generated for every listed Agent.
- Added live Agent, knowledge and network-state signals to the directory hero.
- Added responsive and reduced-motion treatments for the new interface.
- Added visible Manage Expert, Check for updates and Release notes links to the Network Admin theme row.

## 0.1.0 - 2026-09-05

- Delivered Expert as one self-contained WordPress Multisite theme by Techn.
- Added the two-field network Agent wizard with automatic subsite/theme/content/navigation/schedule setup.
- Added core-site sign-in and network membership across all Agents.
- Added bounded local-AI research, two-source synthesis, provenance, semantic retrieval, reflections, FAQs and research backlog.
- Added chat-first Agent pages, network directory, front-end human revisions and an auditable timeline.
- Added adaptive learning cadence, network operations, publication review and manifest-first native theme updates.
