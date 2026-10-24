# Agent release authority

Effective 2026-10-17, by Dalton's explicit instruction. This is the shared merge
policy for the seven active Lunara website repositories and the three secondary
Lunara repositories listed in [repository alignment](REPOSITORY-ALIGNMENT.md).
It replaces earlier instructions reserving merges to Dalton or requiring
another confirmation for an already authorized task.

## Dalton's confirmation — effective 2026-10-23

Dalton's confirmation of a website implementation task (for example "yes",
"go ahead", "do it" or "begin/create it") authorizes the requested work through
task-branch commits, PRs, passing checks, agent merge, manual deployment and
live verification. Do not ask for a second deployment confirmation or require
a new approval naming the final version. Reuse the confirmation in the current
conversation; instructions to hold, prepare only, leave a draft, or not deploy
take precedence. Ask only about a real missing decision, access, changed
destination or work beyond that confirmed scope.

Keep branch protections, required CI and exact-head PR merges. Manual
deployment means the agent uses the existing host action; it does not mean
Dalton must click it. Auto-deploy stays off unless he specifically changes it.
A confirmation never makes a merge evidence of deployment: verify and report
both states separately.

## Standing authorization

Claude, Codex, Gemini, Grok and other agents acting on Dalton's approved task
may commit, push a task branch, open or update its PR, resolve routine
integration issues and merge the completed, verified result. This includes
work requested as "get it ready for release." Finish the merge and report the
result; do not hand Dalton a routine merge-click task. Respect an explicit
instruction to leave a particular PR unmerged.

Dalton's task confirmation covers the requested implementation through release,
including deployment, without a second version-specific permission request.
It covers only that scope. Editorial publication, database application,
credential/access changes and unrelated production settings must be included
in his actual instruction; approval to implement a proposal is not permission
to perform unrelated actions. Respect explicit draft-only or no-deploy limits.

## Required merge procedure

1. **Establish the candidate.** Fetch current remote state. Use a task branch
   from the intended base; normal release PRs target `main`. Record the exact
   repository, PR number, base and remote head SHA. When continuing an existing
   PR, preserve its useful work and integrate current main as needed. Do not
   merge an old branch merely because GitHub says it is ahead.
2. **Review the final changes.** Read the complete diff against the current
   base. Confirm it contains the requested work, preserves unrelated unfinished
   work and does not introduce an accidental rollback. Resolve relevant review
   threads and inspect any conflict resolution. Mark a draft ready only when
   its work is actually complete.
3. **Verify that candidate.** Run appropriate local checks and all applicable
   repository CI on the final candidate. Required checks must pass; applicable
   checks must not remain pending or failed. If the head or base changes,
   reassess the diff and repeat affected verification plus required CI. Do not
   rerun unrelated accepted checks without a new change or failure. Repositories
   with no CI must explicitly record that fact and the relevant local checks;
   never invent a green CI result. Documentation-only edits need a diff/link
   review and existing repository CI, not new placeholder tests. Theme's
   [documentation-only route](CI.md) is an explicit exception to runtime
   retesting; the required `ci` result must still pass. Other repositories
   retain their existing checks.
4. **Merge through the PR.** Recheck the intended base, current head SHA and
   mergeability immediately before the merge. Use a supported merge method and
   an exact-head guard, for example `gh pr merge <number> --repo <owner/repo>
   --merge --match-head-commit <verified-sha>`. Use `--squash` instead when that
   repository requires it. Do not use `--admin`, disable protections, relax
   checks, force push or commit directly to main to get past a gate. If the
   candidate changes or GitHub rejects it, inspect the reason before proceeding.
5. **Confirm the result.** Read back the merged PR and merge commit, verify its
   relationship to remote main, and check any resulting main CI. Record the
   exact commit, checks and any remaining deployment step in the shared Theme
   session log. Fast-forward a clean primary checkout when appropriate; never
   reset or overwrite somebody else's local work.
6. **Keep cleanup safe.** Remove a task branch only after proving its useful
   work is merged and no unfinished work depends on it. Preserve unrelated
   drafts, branches and recovery material. A branch count is not a release gate.

An actual access failure, unresolved product decision or conflict requiring a
choice may need Dalton. Routine PR creation, green-CI merging and branch
coordination do not. Each agent environment must use its own authorized
GitHub connection; this policy does not distribute credentials.

## Deploy within Dalton's confirmed task

Automatic deployment remains off for the active WordPress Theme and plugins.
After the verified merge, use Dalton's confirmation of the requested
implementation to deploy it; do not require a second instruction naming the
final candidate. An explicit hold, prepare-only or no-deploy instruction wins.
Verify the existing host connection, intended branch and destination.
Deploy changed runtime plugins before the Theme, then verify deployment
completion, exact live identity and affected behavior. A merge or green CI
alone is never evidence that the site has changed.

Documentation/test-only policy changes require no runtime version bump or
standalone deployment. Secondary SQL, Engine and World repositories do not
become production release sources by adopting this policy; confirm their
separate runtime/hosting responsibility before any deployment or data apply.

## Authority and maintenance

The active-website `AGENTS.md` files summarize this policy. Secondary
repositories link here from their scoped `AGENTS.md`. Keep current contributor
and release instructions consistent. Preserve dated logs and evidence as
history; label obsolete operational instructions instead of rewriting receipts.
On an old checkout, fetch the current default-branch agreement before acting.

This policy does not weaken GitHub protections or promise permissions an agent
connection does not have. Verify access when needed; never treat an old
human-only instruction as a reason to stop an otherwise authorized merge.
