---
name: changelog
description: Adds changelog entries to CHANGELOG.md following keepachangelog format. Use when updating the Unreleased section or documenting changes for a release.
allowed-tools: Read, Edit
---

# Add Changelog Entry

Add entries to the `CHANGELOG.md` file.

## Workflow

1. Ask user for the change description (if not provided or known)
2. Determine category: Added, Changed, Fixed, Deprecated, Removed, Security

## Format

-   Start with `- ` (hyphen + 1 spaces)
-   Do NOT repeat the category verb — the heading already says Added/Changed/Fixed, so don't start entries with "Added...", "Fixed...", etc.
-   Don't link issues if not really needed: they usually can be backtracked by looking at the PR
-   End with the PR number and link between parenthesis (if it was part of a PR) and a period: `... ([#123](https://.../123)).`

## Writing Guidelines

Changelogs are for **humans, not machines**. Write for both technical and non-technical WordPress users.

**Write for the user:**

-   Explain what changed from the user's perspective, not what you did in the code
-   Name the outcome, not the mechanism: instead of "Optimized query" write "Improved performance on sites with large activity logs" — but keep it to that one clause, not a paragraph of scope
-   Replace jargon with clarity: avoid acronyms, internal class names, or hook names unless the audience is developers
-   Be specific: "Fixed timezone handling in email reports" not "Bug fixes"
-   Active voice: "Fixed X" not "X was fixed"

**Be honest and complete:**

-   Never hide breaking changes, deprecations, or security fixes
-   Be upfront about what changed and why — users trust changelogs that are transparent
-   Include all notable user-facing changes; selective entries undermine credibility
-   Mark experimental features with the `(Experimental) ` prefix (see "Experimental features" section below)

**Too long → tightened (real examples):**

These are all from the 5.29.0 changelog — each ran 2–3 sentences with "Previously…" clauses, enumerations, and scope caveats. Tightened to one line:

```
❌ Overview action links on user, plugin, post, and media events — "All users", "All plugins",
   "All posts" / "All pages" / "All `<custom-post-type>`", "All media". Also shown on delete
   events where the per-item link would dead-end. The "All users" link shows only on
   user-management events (profile updated, user created, user deleted), not on login, logout,
   failed-login, or session-destroy events.

✅ Overview action links ("All users", "All plugins", "All posts", "All media") on user,
   plugin, post, and media events.
```

```
❌ Simple History's activity log is now included in WordPress's personal-data export
   (Tools → Export Personal Data): the events a person performed are exported automatically.
   Previously the activity log was left out of export requests entirely.

✅ Activity log is now included in WordPress's personal-data export (Tools → Export Personal Data).
```

```
❌ New installs now create the history tables with `$wpdb->get_charset_collate()` (matching
   WordPress core's pattern since 4.2) instead of a hardcoded `CHARSET=utf8`. On modern hosts
   this means tables are created as `utf8mb4`, so they can store 4-byte UTF-8 characters
   like emoji in event context — previously a post title with an emoji could silently drop
   the entire context row, leaving log entries like `Updated ""` with no user attribution.

✅ New installs create history tables as `utf8mb4`, so emoji and other 4-byte characters in
   event context are preserved.
```

The pattern in every case: keep the user-visible change and a short location hint, cut the "Previously…" framing, the per-item enumerations, the edge-case scoping, and the mechanism. If a caveat genuinely matters to users (e.g. "existing installs unchanged"), it goes in the release post, not the bullet.

**Don't write:**

-   "Bug fixes" or "Various improvements" (too vague, tells users nothing)
-   "Updated code" or "Minor changes" (meaningless)
-   Raw commit messages or git log dumps
-   Internal hook/filter names in user-facing entries (put in developer docs instead)

## Categories

Use these standard categories from [Keep a Changelog](https://keepachangelog.com):

-   **Added** — New features and capabilities
-   **Changed** — Modifications to existing functionality
-   **Deprecated** — Features that will be removed in a future release
-   **Removed** — Features that have been eliminated
-   **Fixed** — Bug fixes
-   **Security** — Vulnerability patches (always include these, never hide them)

## Experimental features

Features gated behind the experimental features setting use a consistent prefix that signals the gating without drawing extra attention.

**Format:**

```
- (Experimental) Description of the feature, written like any other entry.
```

**Rules:**

-   Lead with `(Experimental) ` (no emoji, no bold).
-   Don't add "Requires experimental features to be enabled" or trailing "(experimental)" — the prefix already says it.
-   Place experimental entries at the **bottom** of their subsection (Added/Changed/Fixed/Security). Stable items first, experimental opt-ins after.
-   If a feature also has a developer-facing filter or hook to toggle it, mention that in the body of the entry, not as boilerplate.
-   Don't repeat the marker on continuation entries — every experimental bullet stands alone.

**Why this format:**

-   The plain `Experimental` prefix labels the entry without making it stand out — experimental opt-ins shouldn't compete with stable shipping changes for attention.
-   Leading the line (rather than trailing) keeps it consistent and easy to spot when you're looking for it.
-   Placing experimental items last in each section means readers focused on stable changes hit them only after the entries that apply to everyone.

**Examples:**

```
✅ (Experimental) Failed application password authentication on REST API and XML-RPC requests is now logged as a warning…
✅ (Experimental) "History" column on post and page list tables showing recent activity at a glance.
❌ "History" column on post and page list tables… (experimental)            (trailing tag — old format)
❌ "History" column on post and page list tables… Requires experimental features to be enabled.   (boilerplate phrase — superseded by the prefix)
❌ History column…                                                          (missing Experimental label)
```

## Short names on the main Added entries

Start the one to three most important **Added** entries of a release with a short bold name for the feature, a few words at most:

```
-   **Table view** for the event log. Sort and filter your events, save views and export them.
-   **Splunk forwarding**: send events to the Splunk HTTP Event Collector.
```

The "What's new" card shown to lapsed Premium customers (issue 15) uses these names as its bullet labels, and only entries with a bold name count as highlights. Leave small additions and `Experimental —` entries without one. The bold renders fine on wordpress.org.

## Unreleased Section

Always maintain an `## [Unreleased]` section at the top of the changelog. This lets users see what's coming and makes it easy to promote entries into a versioned release.

When releasing, move Unreleased entries into a new versioned section with the release date.

## Examples

```
✅ Post creation via Gutenberg autosave not being logged, causing email reports to show 0 posts created.
✅ Developer mode badge to improve debugging workflow.
✅ Performance on sites with large activity logs improved by optimizing database queries.
✅ `simple_history_log()` function — use `SimpleHistory\log()` instead. Will be removed in 6.0.
❌ Added developer mode badge (redundant — heading already says "Added")
❌ Fixed post creation (redundant — heading already says "Fixed")
❌ Bug fixes
❌ Updated code
❌ Refactored SimpleHistoryLogQuery class
❌ Various improvements and optimizations
```

## References

-   WordPress Developer Blog: [The Importance of a Good Changelog](https://developer.wordpress.org/news/2025/11/the-importance-of-a-good-changelog/)
-   Keep a Changelog: [https://keepachangelog.com](https://keepachangelog.com)

## Location

-   File: `CHANGELOG.md` (project root)
-   Section: `# Changelog` → `## [Unreleased]`
-   If Unreleased doesn't exist, create it after the prologue, following the [Keep a Changelog](https://keepachangelog.com) convention.
