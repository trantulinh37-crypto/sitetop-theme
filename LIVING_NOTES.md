# Living Implementation Notes — Operating Convention

This file defines how you (Claude Code) maintain implementation notes during all coding work in this project. Read it fully before any non-trivial task. The convention is non-optional for qualifying work.

---

## 1. When this applies

Activate the Living Notes workflow for any of:
- Implementing a feature from a spec, ticket, or design doc
- Refactoring that touches more than one function
- Bug fixes requiring investigation or changes across multiple files
- Any task expected to exceed ~50 lines of code change
- Any task where you make architectural or library choices

Skip for: typo fixes, formatting-only changes, dependency version bumps, trivial single-line edits.

When in doubt, activate it. A short notes entry costs nothing; a missing one costs reviewer time.

---

## 2. The notes file

**Location**: `implementation-notes.md` at the project root.
For monorepos, use the closest package root (e.g. `apps/api/implementation-notes.md`).

**Format**: Markdown. Append-only. Never overwrite or rewrite prior content.

**Git**: Commit the notes file alongside the code change in the same commit. The notes ARE part of the deliverable.

---

## 3. Session start protocol

Before writing any code in a qualifying task:

1. Check if `implementation-notes.md` exists at the appropriate location.
2. If yes, read the last 2-3 session blocks to recover context. The notes file is your source of truth across `/clear`, `/compact`, and session boundaries — treat it as more authoritative than chat memory.
3. Open a new session block with this header:

```
## Session <ISO-8601 UTC timestamp> — <one-line task summary>
**Spec source:** <path, URL, or inline summary>
**Branch:** <git branch name>
```

Get the timestamp via:
```bash
date -u +"%Y-%m-%dT%H:%M:%SZ"
```

---

## 4. The four things to record

Update these AS YOU CODE. Not at the end. If you batch-write at the end of the task, you have failed the workflow.

### 4.1 Decisions
Choices you made that the spec did not specify.

Format: bullet list, 1-3 sentences each, with file:line references when relevant.

Example:
```
### Decisions
- Chose `ioredis` over `node-redis` for the rate limiter
  (`apps/api/src/rate-limit.ts:12`). Cluster support is required in prod and
  `ioredis` has first-class cluster docs.
- Used a single Redis key per user (`rl:{userId}`) instead of per-endpoint.
  Spec doesn't differentiate endpoints; can be split later if needed.
```

### 4.2 Deviations from spec
Where the implementation differs from what was asked. Always include the reason.

Example:
```
### Deviations from spec
- Spec: "block requests over limit". Implemented: return HTTP 429 with
  `Retry-After` header. Reason: silent drops violate HTTP semantics and
  break client retry logic.
```

### 4.3 Tradeoffs
Alternatives you weighed. Use a Markdown table when comparing 2+ options.

Example:
```
### Tradeoffs
Algorithm choice:

| Option                  | Pros                       | Cons                          | Chosen |
|-------------------------|----------------------------|-------------------------------|--------|
| Sliding window log      | Accurate                   | O(N) memory per user          | No     |
| Sliding window counter  | O(1) memory, ~95% accurate | Slight edge-of-window error   | Yes    |
| Token bucket            | Smooth                     | More complex state            | No     |

Counter chosen because expected accuracy is acceptable for our SLA and
memory cost matters at our user count.
```

### 4.4 Reviewer notes
Anything non-obvious the reviewer or future maintainer should know.

Include:
- **Assumptions** — e.g. "Assumes Redis is single-region; multi-region writes will cause drift."
- **Tech debt deliberately introduced** — e.g. "Redis client duplicated in 2 modules. TODO: extract."
- **Cut corners** — e.g. "No unit tests for the cleanup loop; manually tested only."
- **Surprising behavior** — e.g. "Returns 200 with empty body when user is allowlisted. Intentional. Do not change without checking issue #234."
- **Things you're unsure about** — e.g. "Not sure if `Retry-After` should be seconds or HTTP date; used seconds. Please verify."

---

## 5. Workflow rules

1. **Notes-before-code for non-trivial decisions.** When you are about to make a meaningful choice, write the note FIRST, then implement. This forces conscious decision-making.

2. **No batching.** Each unit of work → immediate notes update. Frequency over completeness.

3. **Honesty over polish.** If you cut a corner, say so. If you're uncertain, say so. Notes that hide problems are worse than no notes.

4. **Terse.** 1-3 sentences per entry. Tables for comparisons. No prose essays.

5. **Self-contained.** A reviewer should understand each entry without reading the code first. Include enough context.

6. **Cross-reference.** Use `file.ts:42` or ``` `functionName()` ``` so the reviewer can jump.

---

## 6. Session-end summary

Before considering a task complete, append a summary section:

```
## Summary
**Files changed:**
- `path/to/file1.ts` — <what changed in one phrase>
- `path/to/file2.ts` — <what changed in one phrase>

**Top items for reviewer to scrutinize:**
1. <thing most likely to be wrong or controversial>
2. <thing that touches existing behavior>
3. <thing you're least sure about>

**Open questions:**
- <anything you could not resolve>

**Test coverage:**
- <what is tested, what is not, why>
```

Generate the file list with:
```bash
git status --short
```

---

## 7. Multi-session continuity

When a fresh Claude Code session opens this project:
1. Read this convention file (loaded via CLAUDE.md).
2. Read the last 2-3 session blocks of `implementation-notes.md`.
3. If the user references prior work without context (e.g. "continue the rate limiter"), the notes file is your ground truth — do not guess.

This is the primary mechanism for context recovery across session boundaries. The notes file survives `/clear`, `/compact`, and process restarts because it lives on disk.

---

## 8. Anti-patterns — do NOT do these

- ❌ Writing all notes at the end as a single block. Defeats the purpose.
- ❌ Vague entries like "made some decisions about the data model." Worthless.
- ❌ Overwriting or rewriting prior session blocks. Always append.
- ❌ Polished prose that hides tradeoffs or uncertainty. Be blunt.
- ❌ Skipping notes because "the code is self-explanatory." It never is to a reviewer two weeks from now.
- ❌ Notes that describe WHAT changed but not WHY. The WHY is the entire point.
- ❌ Hiding hacks. If you hardcoded a value or added a TODO, document it under Reviewer Notes.

---

## 9. Quick example of a well-formed entry

```markdown
## Session 2026-05-19T13:42:11Z — Add per-user rate limiting to /api/v1
**Spec source:** docs/specs/rate-limit.md
**Branch:** feature/rate-limit

### Decisions
- Used `ioredis` (`apps/api/src/rate-limit.ts:8`). Cluster-ready.
- Key format: `rl:{userId}:{windowStart}`. windowStart bucketed to 10s.

### Deviations from spec
- Spec says "block". Returning 429 + `Retry-After` instead. HTTP-compliant.

### Tradeoffs
| Algorithm        | Memory | Accuracy | Chosen |
|------------------|--------|----------|--------|
| Sliding log      | O(N)   | Exact    | No     |
| Sliding counter  | O(1)   | ~95%     | Yes    |

### Reviewer notes
- Assumes single-region Redis. Multi-region needs CRDT or sticky routing.
- No cleanup job for expired keys yet — relying on Redis TTL. Verify TTL is set in all paths.
- `Retry-After` returns seconds, not HTTP date. Both are valid per RFC 7231.

## Summary
**Files changed:**
- `apps/api/src/rate-limit.ts` — new module
- `apps/api/src/middleware/index.ts` — wire in middleware
- `apps/api/tests/rate-limit.test.ts` — unit tests

**Top items for reviewer:**
1. Verify Redis TTL is set in every write path (rate-limit.ts:34, :51).
2. Confirm 429 response shape matches what mobile clients expect.
3. Decide if per-endpoint limits are needed before merging.

**Open questions:**
- Should allowlist be in code or config? Punted to env var for now.

**Test coverage:**
- Unit tests for the limiter logic. No integration test against real Redis yet.
```

---

## 10. Optional: HTML rendering for PR review

If reviewers prefer HTML (tables render better, navigable TOC):

```bash
pandoc implementation-notes.md -o implementation-notes.html --standalone --toc
```

Attach the rendered HTML to the PR description.

---

**End of convention. Apply to all qualifying work in this project from now on. If a task qualifies and you proceed without a notes session block, you are violating the convention — stop and create the block first.**
