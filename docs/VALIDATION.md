# Validation — Expert 0.1.0

Environment: fresh WordPress 7.1 subdirectory Multisite, PHP 8.5.7, local MySQL, no active plugins. The theme was installed and network-enabled using native WordPress APIs. Tests used disposable content and accounts. AI generation, embeddings and discovered pages were deterministic HTTP fixtures in `tests/` only; production PHP contains no mock responses.

## Automated integration checks

`tests/integration.php` passed 78 assertions and covers:

- Fresh Multisite and theme-only operation, no active plugin dependencies.
- Two-field wizard creates independent subsites, activates Expert, prepares identity/subject/navigation and schedules Growth at 600 seconds.
- Dedicated Agent account and blocked interactive authentication.
- Healthy runtime, offline runtime, repeated-error backoff.
- Two credible sources, provenance, hashes, original notes, synthesis and publication.
- Duplicate source avoidance, weak second-source/independence deferral, retained useful sources.
- Existing category reuse and relevant native reflection comments.
- Grounded chat, real object citations, FAQ creation/reuse, backlog creation/demand increase.
- Semantic retrieval isolation across Agents for sources, embeddings and backlog.
- Core subscriber access across Agents, subscriber edit denial, cross-Agent administrator edit denial, nonce rejection.
- Front-end edit callback, native revisions, editor attribution, optimistic conflict detection, human-content protection and no-op post-edit reflection.
- Growth threshold, three-hour Steady mode, acceleration, hysteresis and return to baseline.
- Timeline events, per-Agent locking, paused work, network context restoration and theme-switch preservation of content.
- Loopback URL validation, public-source SSRF rejection, robots rules, removed script markup, cosine dimension safety.
- Network dashboard/wizard/telemetry rendering.

`tests/updates.php` covers nine release cases: manifest-first discovery, repository-controlled package URL, success caching, public-release redirect fallback, separate rate-limit backoff, suppressed retries, malformed responses, newer-version update injection and clearing stale equal-version update data.

The suite verifies prompt separation and bounded actions, not universal prompt-injection immunity or factual quality. It invokes learning loops directly and verifies schedules; it does not wait ten minutes between every test or prove production cron delivery.

`tests/scheduler.php` additionally checks that an unvisited Agent’s job is immediately due when its cron is spawned, and that pending jobs are retried.

## Browser and accessibility checks

Headless installed Chrome via Playwright:

- Anonymous Agent request redirects to core; only the login landing is offered.
- Native core login returns to the directory.
- Directory → Agent navigation, chat form and actual unavailable-runtime error.
- Network Admin renders the creation wizard; submitting two fields creates a real subsite without visiting its backend.
- Screenshots reviewed at 1440×1100 and 390×844; no horizontal document overflow at the mobile size.
- Network pause/resume controls changed the selected Agent state correctly.
- No JavaScript page exceptions in the tested flows.
- Axe-core WCAG 2 A/AA and 2.1 A/AA automated checks passed for the directory and Agent main content.
- Semantic forms, native details/summary, visible focus styling and live status regions inspected. This is not a full manual assistive-technology certification.

Screenshots are generated from the disposable test installation. The theme screenshot is a genuine browser capture of that demonstration, not a production-data claim.

## Static/package checks

- PHP lint across all theme PHP files.
- JavaScript syntax check.
- Actual ZIP installation through the WordPress theme installer succeeded.
- Native theme ZIP has one `expert/` root, required templates/header/licence/readmes/screenshot, matching versions and no tests, dependencies, nested ZIPs or environment files.
- No project-authored CSS pixel dimensions; WordPress's own generated CSS is outside that requirement.
- WordPress Coding Standards inspection and automatic formatting. Full WPCS compliance is not claimed; reviewed advisory/style exceptions are described in `STANDARDS.md`.
- No theme-generated PHP warnings/notices in the exercised paths. The installed WP-CLI dependency emits PHP 8.5 deprecation notices; those are tooling notices outside this theme.

## Environmental validation still required

- Real inference adapter and installed models; semantic and generation quality.
- Real SearXNG-compatible discovery, source robots/terms and independently assessed evidence quality.
- Production cron, PHP execution limits, DNS/network egress policy and sustained concurrency/load.
- Subdomain deployments and any custom cookie/domain settings. Arbitrary domain mapping/SSO is outside this version.
- Other PHP versions, additional browsers, broader accessibility review and large knowledge indexes.

This is a functioning proof of concept, not a claim of production readiness across untested infrastructure.
