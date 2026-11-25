@AGENTS.md

## Git

- Never include `Claude-Session` trailer in commits;
- Always use Conventional Commit style
- Always follow the 50/72 rule for commit titles/messages length;
- Keep commit messages short: they should be for humans and should describe briefly what was the change, not give the full context;
- Add `[skip ci]` to the title of commits that only change documentation, unless they touch `docs/user-documentation/` (shipped in the app);
- Never EVER force-push on the main branch, not even if the user explicitly asks for it; you may force-push ONLY on other branches!

## UI changes

- Whenever applicable and whenever not explicitly asked by the user show before/after side-by-side comparison screenshots at the end;
- Never put screenshots meant only for an issue or a PR in a temporary branch or anywhere else public (other branches,
  gists, image hosts) unless explicitly asked: send them to the user instead;

## CI/CD

- **PR previews** (`deploy-preview.yml`): the `PREVIEW_MODE` repository variable sets which PRs get a preview
  (`opt-out`, the default: every PR unless it has the `preview: off` label; `opt-in`: only PRs with `preview: on`;
  `off`: none). The label names come from the `PREVIEW_OPT_IN_LABEL` / `PREVIEW_OPT_OUT_LABEL` variables, and
  `preview: off` wins over `preview: on`. To turn a PR's preview off or on, change its labels: the `decide` job deploys
  or removes the preview right away, and other labels or a push to a PR without a preview do nothing.
- The `destroy` job runs the scripts and actions of `main` (so that merged or deleted branches can be cleaned up):
  changes to it, or to `.github/actions/preview-comment`, only show on removals once merged. Deployments use the PR's.
- The concurrency group is on the `deploy` and `destroy` jobs, not on the workflow, so that runs with nothing to do
  never cancel them. cron-job.org rate limits its API (HTTP 429): deployments and single removals only warn, and the
  weekly cleanup removes the cron jobs left behind.
- Previews of different PRs deploy at the same time: `deploy.sh` installs the files they share on the space (the
  deployer, the router) under a temporary name, then renames them into place. Keep it that way rather than adding a
  space-wide concurrency group.
- While a PR's head commit has `[skip ci]`, GitHub runs no `pull_request` workflow for it, label changes included: to
  deploy or remove its preview then, run `deploy-preview.yml` manually (`action` deploy/destroy, `pr` number).
- Before creating a PR, or updating one that has neither preview label, always ask the user whether its preview
  should be enabled or disabled, then set the matching label (`preview: on` / `preview: off`) on the PR.
