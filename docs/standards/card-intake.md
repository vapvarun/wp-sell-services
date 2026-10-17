# Card intake template

Every Bugs card carries these before it is built. A card missing one goes back to Possible Bugs.

- **Replicated:** version/commit, role, URL, exact steps, what is shown vs stored.
- **Cause:** `file:line` and how it was established (browser, `wp eval`, code reading).
- **Reach:** every reader and writer across Free, Pro, REST, AJAX, admin, frontend, emails.
- **Presentation:** what the customer sees today, and the one clear state after the fix. No dead control, silent failure or misleading message; docs match.
- **Owner policy:** any money, access or data-loss choice is answered by the owner and recorded.
- **Acceptance bar:** the literal check, plus the negative case that fails on the old code.
- **Close-out ticks:** `audit/escapes.json` row, journey check line (`card:<id>`), manifest delta, "now dead / now duplicate" removed.
