# Theme CI scope

CI runs on every pull request and every push to `main`. The required check
remains **`ci`**, including for documentation-only changes. Branch protection,
the full behavior suites, six browser shards and `tests/ci-skip.txt` are
unchanged by this routing policy.

## Documentation-only route

The complete Git comparison must contain at least one changed path, and every
path must be one of:

- `AGENTS.md`, `ARCHITECTURE.md`, `CLAUDE.md`, `DESIGN.md` or `README.md` at the
  repository root.
- A Markdown (`.md`) file inside `docs/`, including its subdirectories.

The `CI scope` job checks routing behavior and classifies the changes without
installing npm dependencies, PHP or browsers. For a documentation-only result,
the PHP/JS/CSS checks and browser matrix are intentionally skipped. The final
`ci` job requires successful scope validation and those exact skipped results.
Review the documentation diff and links as part of the normal PR review.

## Full route

Any other path runs the existing checks and all six browser shards. This
includes runtime code, styles, templates, assets, configuration, dependencies,
lockfiles, test fixtures (even Markdown fixtures), CI scripts and workflows.
Adding documentation to a code change does not reduce its checks.

Missing/invalid event data, unknown events, a new branch without a previous
commit, unavailable Git history and an empty comparison default to the full
route. A failed scope job blocks the required check. On the full route,
failed, cancelled, missing or unexpectedly skipped jobs cannot pass `ci`.

PRs compare their merge base with the head of the branch; main pushes compare
the previous head with the final head, covering the entire push. Full Git
history avoids truncated API file lists. Rename detection is disabled so both
the old and new paths are considered: moving runtime code into `docs/` still
requires full CI. Deleting or renaming documentation within the allowed scope
remains documentation-only.

## Main push after a tested pull request

A pull request runs full CI on GitHub's merge of the branch with current
`main`. When it passes, the final `ci` job records that exact Git tree as a
small `ci-passed-tree-<tree>` artifact (kept 14 days). A later push to `main`
with the identical tree skips the PHP/JS/CSS checks and browser shards, so the
merge does not run the same suite twice. `CI scope` still runs, and `ci` still
must pass.

`.github/scripts/ci-reuse.cjs` accepts only a completed, successful
`pull_request` run of this same workflow from this repository, never a fork.
Any other tree runs the full route: for example, `main` moved and the branch
was not updated, a pull request failed or is unfinished, the artifact expired,
or the GitHub API was unavailable. The `CI scope` log names the reused run. The
six plugin repositories use the same script in their own workflows.

## Browser shard balance

The full route splits browser test files across the six shards by measured
run time, not by name order. `.github/scripts/browser-shards.cjs` gives the
slowest files out first, each to the shard with the least planned time. Every
shard computes the same plan, so each discovered file runs exactly once. New
tests are included automatically at the median measured time; deleted tests
are ignored. Each shard log prints its planned seconds.

Stale times only make the shards uneven; they never skip a test. When one shard
becomes much slower than the others, refresh the table from a recent full run:

```sh
gh run view <run-id> --repo Lunara-Film/lunara-theme-blocks --log \
  | node .github/scripts/browser-shards.cjs timings > .github/scripts/browser-timings.json
```

## Changing or verifying the policy

Run the routing, shard and reuse tests locally with no dependency installation:

```sh
node --test .github/scripts/ci-policy.test.cjs .github/scripts/browser-shards.test.cjs .github/scripts/ci-reuse.test.cjs
```

These tests use temporary Git repositories for PR/push ranges, mixed changes,
renames, deletions, large diffs and unavailable comparisons. They also exercise
the Actions output, the reuse rules against a local fake GitHub API and the
final required-check policy. Changing the policy or its tests always takes the
full CI route. Inspect `CI scope` and the final `ci` job summary/log in Actions
to see which route was selected.

The documentation-only route belongs to the Theme repository; plugins keep
their own full workflows on pull requests. Neither route changes the separately
dispatched public design acceptance workflow. CI/docs-only changes need no
runtime version bump or site deployment.
