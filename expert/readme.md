# Expert

Author: Techn · Version: 1.5.0 · Status: proof of concept · GPL v2 or later

Expert turns a WordPress Multisite network into a directory of autonomous subject matter Agents. Each begins with a name and a knowledge area. The theme owns the complete WordPress application: no companion plugin, AI plugin, vector plugin or scheduling plugin is required.

## Start here

1. On an existing WordPress Multisite installation, upload `expert.zip` in Network Admin → Themes. Network-enable it and activate Expert on the core site **once**. WordPress does not execute an inactive theme, so network enablement alone cannot start its code.
2. Go to Network Admin → Expert Agents → Network setup and settings. Set the loopback AI URL, text and embedding model identifiers, and local discovery URL. Use the health/models check. These are network-wide infrastructure settings, not per-Agent setup.
3. Select **Add an Agent**, enter **Agent Name** and **Knowledge Area**, then **Create Agent**. Everything else is automatic: unique site address, subsite creation, theme activation, Agent account, subject description, native content types, navigation, permalinks and first learning schedule.
4. Add people as subscribers to the **core site** using WordPress's existing Users screen. They can use every Agent without being separately added to each subsite.

No one needs to visit an Agent backend. Administrators can review drafts, pause/resume and trigger learning from Network Admin. Content corrections happen on the front end. Source notes and publisher/author/date fields are editable there too. The network dashboard displays each Agent's cadence, engagement, knowledge count and recent errors.

## Membership and access

Anonymous visitors can view the core site's animated network overview and limited Agent catalogue. Agent names are not linked publicly, and every Agent page, archive, alternate view, REST route, feed, comment and interactive control remains behind the core membership boundary. Anonymous visits to an Agent redirect to the public directory; login returns members there. Subscriber membership grants use, not editing or network administration. Edit operations use current-blog WordPress capabilities and nonces.

Use a normal same-network domain arrangement; arbitrary domain-mapped Agent sites need a separate single-sign-on design and are not supported in this proof of concept. WordPress serves uploaded media directly through the web server: if you later upload confidential files, enforce membership at the web-server/storage layer as well. The theme does not create private media downloads. Do not put authenticated HTML behind a public full-page cache; responses send private/no-store headers.

## Runtime contract

The local runtime is an operating-system service, not a WordPress plugin. Bind it to loopback. Default base URL is `http://127.0.0.1:8765`. Only `127.0.0.1`, `localhost` and `::1` are accepted; credentials, query strings, fragments and base paths are rejected. Requests never follow redirects. `localhost` is pinned to the literal IPv4 loopback address.

Required endpoints:

| Method/path | Request | Response |
|---|---|---|
| GET `/health` | none | `{"ok":true}` |
| GET `/models` | none | JSON object describing available model identifiers/capabilities |
| POST `/generate` | JSON: `model`, `system`, `task`, `input`, `schema`, `max_tokens` | `{"output":{...}}` or `{"output":"JSON object"}` |
| POST `/embed` | `{"model":"your-model","input":"text"}` | `{"model":"your-model","embedding":[0.1,0.2,...]}` |

`schema` is a descriptive field contract for the selected task, not a claim that the runtime accepts formal JSON Schema. The adapter must produce the requested JSON fields. Task names include `research_focus`, `source_assessment`, `two_source_synthesis`, `taxonomy_equivalence`, `reflection`, `grounded_answer` and `faq_review`. Embeddings must contain 2–4096 finite numbers, have non-zero norm and identify the configured model. Changed model identifiers trigger a batched reindex of published knowledge.

Ollama, llama.cpp and other runtimes are **not assumed** to implement these routes directly. An operator-supplied local adapter may be required. No engine or model weights are bundled, no fake responses exist in runtime code, and there is no cloud fallback. The theme cannot install or start operating-system services from WordPress.

## Discovery and sources

Configure a local SearXNG-compatible endpoint, for example `http://127.0.0.1:8888`. Expert requests `GET /search?q=...&format=json` and expects `{"results":[{"url":"https://..."}]}`. JSON search must be enabled by its operator. No fixed source list, categories or topics are needed.

A loop plans one in-area research topic using backlog demand, recent thinking, existing taxonomy, popular content and the current subject description. It considers at most six discovered URLs and reads at most six source candidates, with one robots lookup per uncached origin. Both pages must be credible, relevant and materially independent before an opinion is generated. Same-host, same-publisher and same-original-report pairs fail the independence check; the model also assesses syndication and circular references. This conservative policy can defer legitimate same-institution sources.

Requests reject private/reserved addresses, non-HTTP schemes, credentials and unusual ports, use WordPress's safe HTTP APIs, disable redirects and cap bytes/time. Robots rules are respected conservatively; robots retrieval errors defer a fetch. Only HTML/plain text is supported. Robots permission is not a copyright licence or a guarantee of source terms compliance. Operators remain responsible for appropriate discovery engines and source use. Network egress controls are recommended as defence in depth against DNS rebinding and proxy misconfiguration.

Sources retain original/canonical URL, title, publisher, author/date when present, retrieved date, type, credibility/primary classification, original-report identity, content hash, topic, claims, source notes, taxonomy and loop ID. Raw article bodies are discarded after analysis. Stored summaries are bounded to 120 words. URL/hash, title and semantic checks avoid duplicates; unchanged known URLs are not repeatedly fetched.

## Knowledge lifecycle

Native posts hold considered perspectives. `expert_source`, `expert_faq` and `expert_research` hold source records, durable answers and open research questions. `expert_subject` provides a revision-capable current understanding. Native categories/tags emerge through exact and semantic reuse. Reflections are native comments, restricted to semantically relevant earlier posts and material changes.

Chat retrieves only the current Agent's published, non-password-protected knowledge, including useful comments and subject description. The model receives evidence as untrusted data and has no tools, settings access or arbitrary fetch authority. Citation links are resolved from native objects, not generated by the model. Weak answers admit the gap and useful questions enter the backlog. Repeated equivalent questions increase demand. Grounded reusable answers may become FAQs; equivalent FAQs are reused. Prompt separation and validation reduce injection risk but cannot prove a probabilistic model's factual correctness or injection immunity. Review consequential content.

Publication defaults to **Draft for review**. Enable **Auto publish** network-wide or per Agent to demonstrate full autonomy. Draft opinions are reviewed centrally. In draft mode automatic opinion publication and dependent reflections/subject changes are held back. Source notes and sufficiently grounded FAQs are still published to members. Human corrections retain revisions, editor attribution and timeline events. AI never silently overwrites a human-edited record; it can add justified related reflections. A human-edited subject description therefore stays under human control.

## Scheduling and engagement

Knowledge pieces centrally count published Agent-authored Posts, Sources and FAQs. Below 100 pieces, Growth runs every 600 seconds. At the threshold, Steady defaults to 10,800 seconds. Sustained engagement can move to 3,600, 1,800 or 600 seconds. Thresholds and intervals are configurable. Two consecutive qualifying observations are required to change the band; acceleration also requires new engagement between observations. Quiet Agents return to baseline. Three repeated failures cause exponential backoff capped at one day. Pause overrides all autonomous loops.

Only intentional first-party interactions are counted. Weights: view 1, source 2, search/FAQ 3, chat/comment 4, edit 5. Views are deduplicated for 30 minutes per signed-in user and object. There are no IP logs, third-party analytics or browser fingerprints. Hourly aggregates retain 30 days. Engagement/hour uses six recent hourly buckets; engagement/loop counts new weighted events since the prior snapshot (bounded to those six buckets). Loop averages retain six observations. Full loop telemetry is capped at 200 rows per Agent; the append-oriented knowledge timeline is retained.

Use a real cron trigger every minute for the core site's `wp-cron.php`, and ensure WordPress can spawn each subsite's cron endpoint. The dispatcher scans 20 sites at a time and dispatches at most three due Agents per pass, retaining a cursor fairly. Work is asynchronous and protected by per-Agent atomic leases. PHP/hosting execution allowances should accommodate a 240-second ceiling. Default limits: 180 seconds, 24 AI requests, one discovery search, six source candidates/fetches, 262,144 bytes per response, two Sources, one Post, two reflections, four new terms, two FAQs and three backlog changes. Failed work does not delete existing knowledge.

## Storage and scale

Network tables use the installation's actual base prefix and explicit `blog_id`:

- `expert_embeddings`: native object/chunk, text hash, model, JSON vector and update time.
- `expert_loops`: timing, actual additions, cadence, engagement, result and error code; no full prompts or source bodies.
- `expert_timeline`: timestamp, actor/type, action, object, concise description, related sources and loop ID.
- `expert_engagement`: per-site/hour/object aggregate scores and counts.

Options store Agent identity/state, settings, locks and reindex progress. Content/meta/revisions use native WordPress APIs. SQL knowledge queries are scoped to the current blog. Root aggregation is a limited directory projection, not shared intelligence.

The POC scans at most 2,000 recent vectors per retrieval, stores up to four 1,400-character chunks per object and limits taxonomy comparison to 100 existing terms. Older content outside this scan window may not appear. There is no approximate-nearest-neighbour engine, PDF ingestion, broad crawler or cross-Agent intelligence. Routine comment updates are indexed asynchronously; model changes rebuild posts, while old comments are refreshed when edited/reapproved. A larger deployment requires performance testing and index evolution.

## Theme lifecycle and updates

Expert must be active on the core and managed Agent sites. WordPress does not load inactive theme PHP, and `switch_to_blog()` does not load another site's theme. Switching an Agent to another theme cancels Expert jobs for that site and disables its autonomy without deleting content. CPT records reappear when Expert is reactivated. Switching away from Expert on the core removes the network control UI; no plugin is installed to circumvent that theme-only limitation.

Updates use the WordPress theme updater and the public `cchatterton/expert` repository. Lookup order: `update.json`, public latest-release redirect, then GitHub API. Successful checks are cached; failures use separate short backoff. A network administrator can select **Check for updates** and install through WordPress Updates. Package URLs are constructed from the trusted repository and validated version.

## Validation and remaining deployment work

See repository `docs/VALIDATION.md` for the exact checks performed. Tested on a fresh WordPress 7.1 subdirectory Multisite with no active plugins and PHP 8.5.7. PHP 8.1 is the minimum code target, not a separately tested environment. Real model quality, real search-discovery reliability, production cron, subdomain cookies and sustained load require environment-specific validation.

Questions promoted to FAQs/backlog are visible to other network members; do not submit private data. Full WordPress revisions and the timeline are deliberately retained. WordPress exports/backups contain that history. Deleting the theme does not erase it.
