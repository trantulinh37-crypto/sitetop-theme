---
name: implement
description: Implement a spec while maintaining living implementation notes per the project convention (decisions, deviations, tradeoffs, reviewer notes — recorded as you code, not at the end).
---

# implement

You have been invoked to implement a spec while maintaining living implementation notes per the project convention.

**Spec to implement:** $ARGUMENTS

## Instructions

1. **Read the convention strictly:** Follow @LIVING_NOTES.md in full. It defines when notes are required, the four things to record (Decisions, Deviations from spec, Tradeoffs, Reviewer notes), the session-start protocol, the session-end summary format, and anti-patterns to avoid.

2. **Session-start protocol** (before writing any code):
   - Check if `implementation-notes.md` exists at the project root. If yes, read the last 2-3 session blocks for context recovery.
   - Open a new session block with the header format specified in section 3 of LIVING_NOTES.md, using `date -u +"%Y-%m-%dT%H:%M:%SZ"` for the timestamp.

3. **Notes-before-code for non-trivial decisions.** When about to make a meaningful choice, write the note FIRST, then implement. No batching at the end — update notes AS YOU CODE.

4. **Terse and honest.** 1-3 sentences per entry. Tables for comparisons. If you cut a corner, say so. If you're unsure, say so under Reviewer notes.

5. **Session-end summary.** Before considering the task complete, append the Summary section (files changed, top items for reviewer, open questions, test coverage) per section 6 of LIVING_NOTES.md.

6. **Commit notes alongside code.** The notes file is part of the deliverable — include `implementation-notes.md` in the same commit as the code change.

If the task does not qualify (typo fix, formatting only, trivial single-line edit per section 1 of LIVING_NOTES.md), say so explicitly and proceed without opening a session block.
