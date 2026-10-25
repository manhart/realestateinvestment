# Release Notes

- Record relevant user-visible features, behavior changes and fixes in the
  `Unreleased` section of `CHANGELOG.md`, in concise German for app users.
- Keep headings in the supported format: `## [Unreleased]`,
  `## [x.y.z] - YYYY-MM-DD`, `### Neu`, `### Verbessert`, `### Korrigiert`.
  Write each list item on one line; do not use HTML or inline Markdown.
- `RealEstateInvestmentApp::VERSION` is the app version source of truth.
  Change it only for an explicitly requested version update. The latest
  numbered changelog entry must match it. Do not bump versions per commit.
- For an approved release, move the relevant Unreleased notes into its
  dated version entry. Use patch updates for fixes and minor updates for
  features; reserve major updates for breaking changes.
- Do not create commits, tags, GitHub releases or pushes without a separate
  explicit request. Keep private scenarios and internal plans out of notes.
