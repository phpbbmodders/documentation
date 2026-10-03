# TODO

- [ ] Claude: Check upstream phpBB documentation for the two empty English index pages, **File uploads** (`development/files/index/`) and **Languages and translations** (`development/language/index/`). Compare the upstream content with the Hugo source to determine whether they are intentionally empty or content was lost during conversion. Recommend whether to restore content, add links to their child pages, or omit these blank entries from navigation.

- [ ] Claude: Investigate four developer documentation images that exist upstream but are absent from the current Hugo build: `extensions/images/skeleton-web-ui.png`, `extensions/images/skeleton-cli.png`, `development/images/new_feature_process.svg`, and `development/images/Phpbb-git-workflow.png`. Check asset copying and generated image URLs, then fix the build pipeline so future scripted builds publish and display these images in both the standalone site and phpBB. Hugo now supplies their missing alt descriptions; phpBB preserves those descriptions as tooltips on the image-unavailable notices. Keep `.po` files unchanged.

## Archived Reviews

The [October 1 Codex review](TODO/archive/archive1.md#codex-review-10-01-2026) is historical. Subsequent features extend beyond its reviewed scope; it is not a current full-codebase review.
