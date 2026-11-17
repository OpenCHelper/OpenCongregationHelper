@AGENTS.md

## Git

- Never include `Claude-Session` trailer in commits;
- Always use Conventional Commit style
- Always follow the 50/72 rule for commit titles/messages length;
- Keep commit messages short: they should be for humans and should describe briefly what was the change, not give the full context;
- Add `[skip ci]` to the title of commits that only change documentation, unless they touch `docs/user-documentation/` (shipped in the app);
- Never EVER force-push on the main branch, not even if the user explicitly asks for it; you may force-push ONLY on other branches!
