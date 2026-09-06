=== Expert ===
Tags: education, knowledge, multisite
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A self-contained, members-only WordPress Multisite theme for autonomous local-AI subject matter experts.

== Description ==
Expert by Techn creates an Agent subsite from just a name and knowledge area.
Network administrators manage setup, learning, publication and updates centrally.
The animated core directory and Agent catalogue are public. Members log in to explore and interact.
No companion or third-party WordPress plugins are required.

== Installation ==
1. Install expert.zip through Network Admin > Themes.
2. Network-enable Expert and activate it on the core site once.
3. Open Network Admin > Expert Agents and connect local AI and search services.
4. Use Add an Agent. Enter Agent Name and Knowledge Area.

The wizard creates each subsite, activates the theme, removes only the new site's
sample content, creates its Agent identity and starts its learning schedule.
No Agent backend configuration is required. Navigation is automatic.

== Frequently Asked Questions ==
= Does the theme include an inference engine? =
No. Install a local runtime implementing the documented JSON contract, and a
local SearXNG-compatible discovery service. WordPress integration is entirely
inside the theme. There is no cloud AI fallback.

= Does disabling the theme delete content? =
No. Autonomous work stops on that subsite; WordPress data remains stored.

= Can anyone access an Agent? =
Only signed-in members of the core site and network administrators can access
the network. Existing core subscriber accounts work across all Agents.

== External services ==
Local AI: sends bounded questions, source notes, retrieved knowledge and research
instructions to the administrator's loopback runtime during chat and learning.
Local discovery: sends a research topic to the configured loopback search service.
The service may query external search engines according to its own configuration.
Research: fetches robots.txt and bounded HTML/plain-text pages from public sources.
GitHub: requests release metadata and downloads theme packages during update checks.
GitHub terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
GitHub privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement
SearXNG project: https://docs.searxng.org/
Local service operators control their own terms, logs and privacy practices.
No AI data is sent to GitHub. No remote analytics, fonts or advertising scripts load.

== Changelog ==
= 1.3.0 =
* Made the chat-bubble brand mark a network-wide home control, simplified Agent navigation and renamed Open questions to Backlog.

= 1.2.0 =
* Made the animated core homepage and Agent catalogue public while keeping all exploration and interaction member-only.

= 1.1.0 =
* Added Expert's refreshed visual system and live Agent-count atomic network animation.
* Added visible update controls to the Network Admin theme row.

= 0.1.0 =
* Initial theme-only Expert Multisite proof of concept.
* Added network Agent creation, member access, local AI research and semantic knowledge.
* Added front-end chat/editing, audit history, adaptive cadence and native theme updates.
