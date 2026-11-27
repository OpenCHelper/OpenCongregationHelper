# Contributing

## Version numbers

The version headings of the [changelog](CHANGELOG.md) leave out the `v` (e.g. `## [1.0.0] - 2026-12-01`), as
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) writes them and as all the past headings are written: for
[Semantic Versioning](https://semver.org/spec/v2.0.0.html), `v1.0.0` is not itself a version number, the `v` is only a
common prefix for tags, and tools that read changelogs usually expect the bare number. The `v` is kept where it already
is: the git tags (e.g. `v1.0.0`), the titles of the GitHub releases and the `version` of `composer.json`. The release
workflow (`.github/workflows/create-release.yml`) strips the `v` from the tag to find the changelog section of the
release.
