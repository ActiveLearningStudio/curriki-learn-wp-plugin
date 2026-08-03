# AI Course-Creation Blueprint

> **What this document is.** A reusable, platform-agnostic blueprint of the two AI authoring
> pipelines in the TinyLxp plugin — **AI Lesson Content** and **AI Lesson Video**. It is written
> to be ingested by *another* AI that is building a standalone **Agentic AI Course-Creation
> application**. It describes the transferable AI logic — prompt architecture, generation
> strategies, schema/JSON contracts, and pipeline stages — not the WordPress plumbing. WordPress
> details appear only where they encode a reusable idea.
>
> **What this is NOT.** It is not an as-built API reference. For the exact WordPress REST routes,
> post-meta keys, and file wiring of the video feature, see the companion as-built spec
> [ai-video-context.md](ai-video-context.md). Two auxiliary AI pipelines that exist in the plugin
> (student "capstone" answer evaluation, policy-document PDF assembly) are intentionally out of
> scope here.

---

## 1. Purpose & how to use this blueprint

The goal is to let an agent **assemble a full course** — a hierarchy of lessons, each with rich
HTML content and optionally an animated explainer video — by orchestrating a small number of
LLM calls against **fixed structural contracts**.

Read it in this order:

1. **§2 Shared AI foundation** — the one LLM primitive both pipelines use, and the single design
   principle that makes the whole approach robust.
2. **§3 Pipeline A (Content)** and **§4 Pipeline B (Video)** — each is self-contained: inputs →
   prompt strategy → output contract → deterministic post-processing.
3. **§5 Orchestration** — how an agent strings these into a course.
4. **§6 Prompt appendix** — the actual prompt text, verbatim enough to reproduce behavior.
5. **§7 Reconstruction checklist & caveats** — what to re-verify and harden before shipping.

> **Verify before relying:** model IDs, inference-profile names, and AWS regions in this document
> reflect the source at the time of writing. Re-check every model ID / region string against the
> live provider catalog — they drift.

---

## 2. Shared AI foundation

### 2.1 One LLM primitive

Every AI call in both pipelines goes through a single text-in / text-out function. Reference
implementation: `TL_AWS_Bedrock_Client::invoke_bedrock()` in
[../includes/class-aws-bedrock-client.php](../includes/class-aws-bedrock-client.php).

```
invoke(user_message: string, system_prompt: string = "", max_tokens: int = 4096) -> string | Error
```

Concrete instance (swap freely for any equivalent Claude endpoint):

| Aspect | Value in the reference implementation |
|---|---|
| Transport | AWS Bedrock **Converse API** (`messages` + optional `system`, single user turn) |
| Model | `global.anthropic.claude-sonnet-4-6` (primary), `us.anthropic.claude-sonnet-4-6` (fallback) |
| Sampling | `temperature: 0.3` (low, for structural consistency) |
| Region | `us-east-1` (hardcoded) |
| Auth | **No API keys.** Credentials resolved from the host's cloud IAM role (EC2 IMDSv2). |
| Model-ID resilience | On an "invalid model identifier / on-demand throughput / inference profile" error, it auto-retries with the cross-region profile and persists whichever ID worked. |

The important portability facts: it is **stateless**, **single-turn**, **system + user**, and
**low-temperature**. No conversation memory, no tools, no streaming are required by either
pipeline. Any provider giving you `system + user -> text` at low temperature will do.

### 2.2 Token budgets differ by task — on purpose

| Task | max_tokens | Why |
|---|---|---|
| Template **classification** (return a 2-digit number) | 4096 (only a few used) | trivial output |
| Single **content block** render | 2048 | one small HTML section |
| **Standard whole-lesson** fill | 4096 | one full lesson page |
| **Synthesis template** (large numbered grids) | 8192 | many repeated cards |
| Video **scene script** (block markers) | 2048 | short outline |
| Video **scene JSON** | 8192 | full JSON scene array |

Rule of thumb an agent can reuse: **budget to the size of the structured output, not the input.**
Classification is cheap; JSON-mode and multi-card fills need headroom.

### 2.3 The one principle behind everything: constrain generation with a fixed contract

Neither pipeline asks the model to invent layout. Instead:

- **Content** gives the model an **HTML template with `[PLACEHOLDER]` tokens** and says "fill the
  tokens, preserve everything else."
- **Video** gives the model a **strict JSON schema with a per-layout `items[]` contract** and says
  "emit exactly this shape."

The model supplies *content*; the *structure* is deterministic. This is what makes the output
renderable, style-consistent, and safe to post-process. Every design decision below follows from
this principle.

---

## 3. Pipeline A — AI Lesson Content Generation

**Input:** a lesson title + raw lesson text (author notes, prose, an outline).
**Output:** a self-contained block of styled HTML (inline CSS only), ready to be a lesson body.

There are **two strategies**. An agent picks one per lesson.

### 3.1 Strategy 1 — Two-pass *classify → fill*

Use when the author has freeform prose and wants the system to choose a good structure.

```
        title + raw text
              │
     ┌────────▼─────────┐   Pass 1: CLASSIFY
     │ pick 1 of N        │  LLM returns only "NN" (e.g. "07")
     │ structural         │  from a topic-agnostic manifest of
     │ templates          │  structural patterns
     └────────┬─────────┘
              │  template_id
     ┌────────▼─────────┐
     │ load template HTML │  a heredoc full of [PLACEHOLDER] tokens
     └────────┬─────────┘
              │
     ┌────────▼─────────┐   Pass 2: FILL
     │ replace tokens     │  LLM returns the completed HTML,
     │ preserve styles    │  preserving all inline CSS + sentinels
     └────────┬─────────┘
              │
        finished lesson HTML
```

**Pass 1 — classification.** The classifier is given the title, the content, and a **manifest of
structural patterns** (not topics). In the reference implementation there are 15 templates; the
manifest describes each by *shape*, e.g.:

- `01` — Stats/evidence grid + numbered consequence cards + myth-vs-reality comparison. *Use when
  data establishes urgency.*
- `07` — Failure-reason grid + alert block + alternatives row. *Use when a common default approach
  is flawed and needs replacing.*
- `15` — Numbered component grid + guidelines block + living-document cycle. *Use as a
  capstone/synthesis template consolidating prior learning into a draft document.*

The classifier's entire job is to return **only the two-digit id** (`"01"`–`"15"`). The caller
validates with a regex and **falls back to `01`** on any non-conforming answer. This is a cheap,
deterministic routing step — the model chooses a *shape*, code does the rest.

> **Reusable idea:** describe your templates by their *structural intent*, topic-agnostically, so
> one library serves any subject. Make the classifier output a single token you can validate.

**Pass 2 — fill.** The chosen template is a full HTML document fragment wrapped in
`<div class="lp-ai-lesson-template">`, built from reusable chunks (hero header, a Learning
Outcomes + Opening Hook block, several topic sections, a Capstone activity). The fill prompt's
guardrails (all reusable):

- Output **only raw HTML** — no markdown, no code fences, no prose. Must start exactly with
  `<div class="lp-ai-lesson-template">` and end with `</div>`.
- **Replace every `[PLACEHOLDER]`** with content drawn from the lesson; fill `[OUTCOME_1..4]` with
  actionable outcomes and `[OPENING_HOOK_STATEMENT]` with a framing statement.
- **Preserve every inline style exactly.** Keep CSS-variable references verbatim
  (`var(--lp-primary-color,#ffb606)` / `var(--lp-secondary-color,#442e66)`).
- **Preserve protected sentinels verbatim** (see §3.3) — do not treat them as tokens to fill.
- **Reading-time cap:** total lesson must read in ≤ 15 minutes; calibrate depth to topic
  complexity (simpler topics → shorter output).
- **Forbidden:** adding any "Check for Understanding" / quiz section, Tailwind classes, Font
  Awesome, or any external CSS/JS.

**Dynamic counts.** One template (the synthesis one, `15`) needs *N* numbered component cards. A
small deterministic scanner (`detect_component_count`) reads the source for an explicit count
("7 principles", numbered lists) and the prompt instructs the model to emit **exactly N cards**,
badged `01..NN`, "do not stop early." The template gets an explicit *exception* permitting the
model to replicate a card `<div>` — the only place structure duplication is allowed.

### 3.2 Strategy 2 — Block-marker DSL (author-directed, per-block calls)

Use when the author wants to control the exact sequence of sections. The author writes fenced
blocks in the source:

```
:::hero
This lesson guides school teams through writing clear, usable policy documents.
:::

:::learning-outcomes
- Identify the core sections of a policy document
- Understand why structure shapes interpretation
- Review whether each section fits its audience
:::

Some ordinary prose here becomes a plain "prose" block with no AI call.

:::contrast-panel
Good policy writing names the audience and assigns revision ownership.
Weak policy writing hides how decisions are made and skips review.
:::

:::capstone
Learners draft a revised policy section naming the audience, the required action,
and how the document will be updated over time.
:::
```

**Parsing.** A parser splits the content into an **ordered list of segments** `{type, content}`.
It tolerates rich-text-editor noise (fences wrapped in `<p>` tags, `&nbsp;`, entities). Any text
outside a fence becomes a **`prose` segment**.

**Rendering.** Each segment is rendered independently:

- A **`prose`** segment is wrapped in a styled `<section>` locally — **no AI call**.
- Every **typed block** triggers **one LLM call** that fills *that block's* HTML template.
- All rendered blocks are concatenated inside one `<div class="lp-ai-lesson-template">` wrapper.

There are ~20 block types (hero, learning-outcomes, opening-hook, capstone, stats-grid,
cards-grid, tier-cards, numbered-grid, two-col-table, three-col-table, contrast-panel, callout,
dark-block, definition-block, role-split, option-cards, checklist, cycle, myth-reality,
blockquote).

**Coherence across independent calls.** Because each block is a separate call, they could drift.
Two mechanisms prevent it:

1. A compact **lesson-context string** (a one-line summary of every non-prose block, in order) is
   injected into *each* block's system prompt: "This block is one part of a multi-section lesson.
   LESSON OVERVIEW: … Use this to stay tonally aligned and avoid repeating content."
2. **Per-block fidelity policies** (below) control how much the model may rewrite.

**Three fidelity policies** (assigned per block type — a reusable pattern for "how literal should
the model be"):

| Policy | Applied to | The model may… |
|---|---|---|
| `shell` | hero, learning-outcomes, opening-hook, capstone, blockquote | enhance & rephrase freely, staying faithful to intent & key points |
| `preserve-close` | cycle, myth-reality, contrast-panel, role-split, definition-block, callout, dark-block, checklist | make only minimal edits; **keep explicit comparisons/pairings intact** |
| `structured` | stats-grid, cards-grid, tier-cards, numbered-grid, tables, option-cards | reorganize & tighten to fit cards/tables, but **never invent new claims, facts, examples, or data** |

**Dynamic item counts.** For list-style blocks, a small scanner counts intended items (bullets →
labeled sections like "Option A:" / "Step Two:" → sentence count) and the prompt emits an
**item-count hint**: "produce exactly N items; replicate the inner element's HTML/CSS for extras;
remove excess; leave no unfilled `[PLACEHOLDER]`."

### 3.3 Output & schema contract (both strategies)

- **Wrapper:** always `<div class="lp-ai-lesson-template" style="max-width:980px;margin:0 auto;…">
  … </div>`.
- **Styling:** **inline CSS only**, two CSS variables for theming
  (`--lp-primary-color`, `--lp-secondary-color`). No external stylesheets, no JS, no web fonts.
  This is what lets the HTML drop into any rendering surface safely.
- **`[PLACEHOLDER]` tokens:** ALL-CAPS bracketed names (`[LESSON_TITLE]`, `[OUTCOME_1]`,
  `[STAT_1_VALUE]`, …). The model replaces them; everything else is preserved byte-for-byte.
- **Protected sentinels** — plain-text strings the model must emit **verbatim**, never fill:
  - `[Capstone Box]` inside `<div class="lxp-capstone-box">` — a placeholder for an interactive
    response box swapped in at runtime by frontend JS.
  - `[Text Box]` — same idea for workbook input fields.
  - **Why they exist:** HTML sanitizers strip real `<textarea>`/form elements from stored content,
    so interactive controls **cannot** be stored in the lesson body. The sentinel is stored;
    frontend JS converts it to a live control at view time. **Never store form elements in
    generated content — store a sentinel and hydrate it client-side.**

### 3.4 Reusable takeaways

- **Classify-then-fill beats one-shot.** Splitting "what shape?" from "what words?" gives you
  consistent structure, cheap routing, and a validatable intermediate (the template id).
- **Per-block calls beat one giant call** when the author wants a precise section order and higher
  fidelity per section — at the cost of more calls. Use a shared context string to keep them
  coherent, and fidelity policies to tune literalness.
- **Agent decision rule:** freeform prose, "just make it look good" → *classify→fill*. Author has
  an explicit section-by-section outline, or wants to preserve exact pairings/tables → *blocks*.

---

## 4. Pipeline B — AI Lesson Video Generation

**Input:** lesson title + raw lesson text + a target duration (`M:SS`, clamped 0:30–5:00).
**Output:** an MP4, produced by feeding a **JSON scene array** to a programmatic video renderer.

The AI's job is **not** to render video — it is to emit a strict JSON description of scenes. A
separate rendering engine turns that JSON into frames. Keep that boundary in mind: the reusable
contract is **"lesson text → JSON scene array → renderer."** The reference renderer is Remotion on
AWS Lambda, but any renderer that consumes the schema in §4.3 works.

### 4.1 Two-step generation

```
 raw text + duration
        │
   ┌────▼──────────────┐  STEP 1 — SCRIPT
   │ LLM → block-marker  │  outputs :::layout-name\n<desc>\n::: blocks
   │ scene *outline*     │  (human-editable; count driven by duration tier)
   └────┬──────────────┘
        │  author may edit / reorder / insert layouts
   ┌────▼──────────────┐  STEP 2 — JSON
   │ LLM → strict JSON   │  { title, accent, scenes[] } per the schema
   │ scene array         │  (block outline is passed back in as an exact spec)
   └────┬──────────────┘
        │  deterministic post-processing (durations, overlay)
   ┌────▼──────────────┐
   │ renderer (Remotion) │  JSON as InputProps → MP4; async render + poll
   └───────────────────┘
```

**Why two steps.** Step 1 produces a cheap, human-readable **outline** (a sequence of layout
choices with a one-line intent each) that an author can edit before committing to an expensive
render. Step 2 turns the approved outline into fully-specified scenes.

**Step 1 — scene script.** The model is a "lesson video scene architect." It outputs **only**
block markers, nothing else:

```
:::intro
Open on the core question this lesson answers.
:::
:::process
Walk through the three drafting stages in order.
:::
:::conclusion
Recap the living-document cycle and the call to action.
:::
```

Rules the model must obey: first block is `intro`; last block is `conclusion` or `cycle-loop`;
choose layouts only from the fixed vocabulary (§4.4); match layout to content type; produce a
block count inside the duration tier's range (§4.2); no-repeat rule (at most one repeat for long
scripts).

**Step 2 — scene JSON.** The model becomes a "professional instructional video designer and motion
graphics director" and must output **one valid JSON object, no markdown fences, no prose**. When
the Step-1 outline is passed back, it's converted into an **explicit spec** ("Generate exactly N
scenes in this order; Scene 1 — layout: intro, Content: …") and a **BLOCK MODE** clause forces the
model to honor the declared count/order/layout exactly rather than re-planning.

### 4.2 Duration model — approximate, then pin deterministically

The author's chosen length maps to a **tier** that sets the scene-count range and per-scene frame
budget (30 fps throughout):

| target ≤ | target_frames | scenes (min–max) | frames/scene |
|---|---|---|---|
| 30 s | 900 | 4–6 | 120–190 |
| 60 s | 1800 | 6–10 | 150–240 |
| 90 s | 2700 | 9–13 | 180–250 |
| 120 s | 3600 | 12–17 | 190–260 |
| 180 s | 5400 | 16–22 | 200–270 |
| > 180 s (≤300) | sec × 30 | `max(18, n_ideal−3)` … `n_ideal+3`, where `n_ideal = round(frames/215)` | 210–280 |

The model only **approximates** the per-scene `duration_frames`. After generation, a deterministic
step rescales **every** scene proportionally so the totals sum to **exactly** `target_seconds × 30`
frames; the **last scene absorbs the rounding remainder**. Scene count and relative pacing are
preserved; only the absolute total is pinned.

> **Reusable idea — "LLM approximates, code pins."** Let the model handle *relative* pacing (it's
> good at that) and let deterministic code enforce the *exact* invariant (it must be exact). This
> pattern generalizes to any "the total must be exactly X" constraint.

### 4.3 Scene schema (the JSON contract)

```jsonc
// InputProps — the whole video
{
  "title": "string — lesson title",
  "accent": "string — one of the 6 palettes (see §4.5)",
  "scenes": [ /* Scene objects */ ],
  "background_clip": "string? — optional URL; injected AFTER the LLM (see §4.6)"
}

// Scene
{
  "layout": "string — one of the layout vocabulary (§4.4)",
  "title": "string — max ~6 words, accent-colored heading",
  "on_screen_text": "string — max ~10 words, white supporting phrase",
  "narration": "string — 1-2 spoken sentences (reserved for TTS/subtitles; NOT drawn on screen)",
  "items": [ /* SceneItem objects — count & contract depend on layout */ ],
  "duration_frames": 180,          // integer; normalized to exact total before render
  "callout": "string? — one ≤2-line key insight, rendered as a highlighted box",
  "overlay_anchor": "'bottom'|'left'|'right'?  — only meaningful in overlay mode (§4.6)"
}

// SceneItem
{
  "text": "string — required display label / heading",
  "sub_label": "string? — secondary detail",
  "featured": true,                 // boolean? — hero / recommended item
  "role": "'input'|'output'|'bad'|'good'?",   // semantic role for flow/contrast layouts
  "status": "'pass'|'gap'|'warn'?",           // color coding for evaluation/checklist
  "icon": "string? — a NAMED glyph (shield, lock, globe, building, mic, calendar, fuel, target, gauge, document, network, checkmark) OR a single emoji fallback",
  "badge": "string? — short ALL-CAPS pill: KEY CONCEPT · TIP · EXAMPLE · WARNING · BEST PRACTICE · NOTE · STEP · TOOL · RULE · INSIGHT · MYTH · FACT",
  "description": "string | string[]? — layout-dependent (array of short phrases for 'framework'; one short sentence elsewhere; required in 'editorial')"
}
```

**Text conventions the model uses inside any text field:**

- **`*emphasis*`** — wrap one or two key words in asterisks to render them in the accent color.
  Use sparingly.
- **Named icons** render as crisp accent-colored SVG glyphs; unknown values fall back to being
  drawn as emoji — so both a curated vocabulary and arbitrary emoji "just work."

### 4.4 Layout vocabulary (24 layouts) + `items[]` contract

Each layout is a distinct animated component. The model must respect each layout's `items[]`
contract (count + which `role`/`status`/`featured` flags matter).

| Layout | Purpose | `items[]` contract |
|---|---|---|
| `intro` | Title hero with orbiting concept chips | 3–6 concept names |
| `problem` | Stacked pain-point cards | 3–5; `featured:true` on the key one |
| `framework` | Numbered blueprint blocks | 3; each `description` = string[] of 2–4 short phrases |
| `process` | Left-to-right pipeline | 3–5 ordered stage names |
| `contrast` | Overloaded → focused | 2–3; `role:'bad'` on before, `role:'good'` on after |
| `evaluation` | Checklist with a gap reveal | 3–5; `status:'gap'` on the weakness |
| `options` | Option circles, one highlighted | 3–4; `featured:true` on recommended |
| `conclusion` | Call-to-action cycle | 3–5 stage names |
| `card_list` | Plain card list (alias of `problem`) | 3–6; `featured:true` on standout |
| `branching_flow` | 1 input → several outputs | `role:'input'` on 1, `role:'output'` on 2–4 |
| `before_after` | State transition | exactly 2: `role:'bad'` then `role:'good'` |
| `quad_grid` | 2×2 with animated checkmarks | exactly 4; use `sub_label` |
| `three_step_flow` | 3 boxes + arrows | exactly 3; use `sub_label` |
| `cycle_loop` | 4 nodes in a diamond loop | exactly 4; `text` must be 1–3 words |
| `split_blueprint` | Inputs (left) vs outputs (right) | 4–8; `role:'input'`/`'output'`; short text |
| `fuel_engine` | Ingredients → engine → 1 result | 3–5; `role:'input'`, 1 `role:'output'`; single-line |
| `checklist_reveal` | Sequentially revealed checklist | 3–6; `status:'gap'`/`'warn'`; ~6–8 words each |
| `deployment_circles` | Concentric rings, innermost first | exactly 4; `text` 1–2 words |
| `editorial` | Rich concept blocks | 1–3; each MUST have `description`; `callout` recommended |
| `comparison` | X vs Y (optional merged result) | exactly 2 (+ optional 3rd merged); `featured` on preferred |
| `gate` | "Ask before acting" checkpoint | 2–4 question/confirm items |
| `routing` | Sort items to destinations | 3–5; `text`=item, `sub_label`=bucket |
| `stat_highlight` | One striking metric / before→after | 1–2; before→after via `role:'bad'`/`'good'` |
| `transform_text` | Rewrite weak → sharp | exactly 2: `role:'bad'` then `role:'good'` |

**Ordering & rhythm rules the prompt enforces:** first scene `intro`, last `conclusion` or
`cycle_loop`; alternate analytical layouts with high-impact ones; no layout repeats in a 6–8 scene
video (≤1 repeat for 9–10); progressive density (visual open → conceptual middle → synthesis
close).

### 4.5 Accent palettes (domain-driven theming)

The model picks one `accent` value by the lesson's primary domain:

| `accent` | Domain |
|---|---|
| `gold` (default) | General / business / finance / history |
| `cyan_orange` | Technology / STEM / programming / engineering |
| `emerald` | Health / science / environment / growth / biology |
| `violet` | Creativity / AI / design / innovation / arts |
| `rose` | Leadership / management / communication / social sciences |
| `teal` | Data / systems / analytics / digital / information |

The prompt pushes for **specificity** ("a machine-learning lesson is `violet`, not generic
`gold`"). All palettes render over a shared navy background.

### 4.6 Rendering stage & overlay mode

**Render + poll.** The JSON is handed to the renderer as its input props; rendering is asynchronous
(cloud render). The client submits, receives a render id + `processing` status, then **polls a
status endpoint every ~5 s** until `done` (returns a video URL) or `error`. Reusable pattern:
**submit → persist render id/status → poll → cache terminal result.**

**Overlay mode (optional background video).** An author may attach one external clip
(`.mp4/.webm/.mov`, validated for scheme + extension). Key design points, all reusable:

- The clip URL is **injected into the JSON *after* the LLM returns** — the model never sees it.
- Presence of a clip flips a rendering flag that makes every scene's background transparent so the
  footage shows through (a split-frame band in the reference renderer).
- When a clip is present, the Step-2 prompt gets an **OVERLAY MODE** addendum nudging the model
  toward text-forward layouts (`editorial`, `intro`, `conclusion`, `card_list`,
  `checklist_reveal`, `process`), away from center-owning ones (`quad_grid`, `cycle_loop`,
  `deployment_circles`), keeping `items[]` sparse (2–4) and setting `overlay_anchor` per scene.
- The clip is trimmed to the composition length (no looping; holds last frame if shorter). The
  renderer must be able to fetch the URL over the public internet.

### 4.7 Robustness notes (JSON mode)

- The model is told to output **only** the JSON object; the caller still **strips markdown fences**
  defensively before parsing.
- After parsing, require a **non-empty `scenes[]`** or fail loudly — never render an empty array.
- All post-generation mutation (duration normalization, overlay-clip injection) happens on the
  parsed object, deterministically, before the renderer sees it.

---

## 5. Orchestration model for an agentic course builder

### 5.1 The reusable object model

```
Course
  └── Lesson (the unit an agent iterates over)
        ├── HTML content   (Pipeline A: classify→fill  OR  block DSL)
        └── Video (opt.)   (Pipeline B: script → JSON → render)
```

A lesson needs an **identity (id + title) to exist first** — the video pipeline reads the lesson
title for context and writes its artifacts back against that identity. So create the lesson record
before generating its media.

### 5.2 Suggested agent loop

```
1. Create or locate the Course.
2. Decide the lesson list (titles + source text) — from a syllabus, an outline, or the author.
3. For each Lesson:
   a. Create/locate the Lesson record (id + title) so media has something to attach to.
   b. Gather the lesson's source text.
   c. Choose a content strategy:
        - freeform prose, "make it look good"      -> classify→fill (§3.1)
        - explicit section outline / exact tables  -> block DSL     (§3.2)
   d. Generate the lesson HTML.  **Persist it yourself** (see §5.3).
   e. (Optional) Generate a video from the same source text:
        - Step 1: text + duration -> scene script (let author edit, or auto-approve)
        - Step 2: script -> scene JSON -> submit render -> poll to completion
        - Persist the returned video URL / status.
4. Assemble lessons into the course structure.
```

### 5.3 Persistence split — a lesson learned

The two pipelines behave differently, and an agent must account for it:

- **Content generation returns HTML but does NOT save it.** The caller owns the save step. (In the
  reference plugin the generated HTML comes back in the response and a human clicks "Update" to
  persist it; before returning, the endpoint backs up the *previous* content so a "restore" is
  possible.) **Your agent must explicitly write the returned HTML to the lesson.**
- **Video generation persists its own state** (render id, status, final URL) server-side and is
  polled. The agent mainly waits and records the resulting URL.

Design your own agent so the **save is an explicit, owned step** for content, and an **await + record**
step for video.

---

## 6. Prompt reference appendix

These are the load-bearing prompts, close to verbatim, lightly annotated. Reproduce their
*structure* and *constraints*; adapt wording to your provider.

### 6.1 Content — template classifier

*System:*
```
You are a lesson template classifier. Given a lesson title and content, return ONLY the
two-digit number (01-15) of the best-matching structural template. Output nothing else - no
explanation, no period, no whitespace.
```
*User:*
```
LESSON TITLE: {title}

LESSON CONTENT:
{content}

TEMPLATE MANIFEST:
01: {structural description}
… (through 15)

Which template number (01-15) best matches the structural needs of this lesson? Reply with only
the two-digit number.
```
*Caller:* validates output is `01`–`15` (regex, with a fallback extraction from a longer string);
defaults to `01` on any failure.

### 6.2 Content — template fill

*System (key clauses):*
```
You are an expert Instructional Designer. [Use "{title}" as the primary heading in the Hero
Header [LESSON_TITLE] token.] You will receive lesson content and an HTML template. Transform the
lesson content into the template.
CRITICAL: Output ONLY the raw HTML code - no markdown, no code fences, no explanation.
The HTML must start exactly with <div class="lp-ai-lesson-template"> and end with </div>.
Replace every [PLACEHOLDER] token with content tightly relevant to the original lesson topic.
Preserve ALL inline styles exactly as written - do not add, remove or alter any style attributes.
Keep CSS variable references exactly as written: var(--lp-primary-color,#ffb606) and
var(--lp-secondary-color,#442e66).
REQUIRED: Replace [OUTCOME_1]..[OUTCOME_4] with 3-4 specific, actionable outcomes.
REQUIRED: Replace [OPENING_HOOK_STATEMENT] with a compelling, context-setting statement.
CRITICAL: Preserve the [Capstone Box] sentinel exactly as written inside its <div>.
FORBIDDEN: Do NOT add any "Check for Understanding", quiz, or multiple-choice section.
Do NOT add Tailwind classes, Font Awesome, or any external CSS/JS references.
READING TIME CONSTRAINT: Total reading time MUST NOT exceed 15 minutes. Calibrate depth to the
complexity of the original content.
```
*User:* `LESSON TITLE`, `ORIGINAL LESSON CONTENT`, and `TEMPLATE TO FILL IN` (the raw template
HTML). For the synthesis template, an extra clause: "Generate exactly N card <div> blocks …
number each badge 01..NN … do not stop early."

### 6.3 Content — single block render

*System (assembled from parts):*
```
You are an expert instructional designer formatting one lesson section at a time.
[Hero only: Use "{title}" exactly for the [LESSON_TITLE] placeholder.]
[This block is one part of a multi-section lesson. LESSON OVERVIEW (all sections in order): {ctx}
 Use this overview to stay tonally aligned, avoid repeating content, and reinforce the central
 message.]
{policy clause — one of:}
  shell:           "You may enhance and rephrase the source for clarity and engagement, but keep
                    the meaning, intent, and key points faithful to the author input."
  preserve-close:  "Preserve the author intent and any explicit comparisons, contrasts, or
                    pairings as closely as possible, making only minimal edits to fit the template."
  structured:      "You may reorganize and lightly rewrite the source to fit the card or table
                    structure … but never invent new claims, facts, examples, scenarios, or data."
[capstone only: Fill [CAPSTONE_PROMPT] from the author notes. CRITICAL: [Capstone Box] is a
 protected runtime sentinel — output it verbatim; never replace or modify it.]
The source content is the author's own notes … Output ONLY the raw HTML for the provided section …
Replace every [PLACEHOLDER] … Preserve every inline style … no scripts/classes/external assets …
reading time under 15 minutes.
```
*User:* `LESSON TITLE`, `SOURCE BLOCK CONTENT`, `SECTION TEMPLATE`, and for list blocks an
`ITEM COUNT HINT: … produce exactly N items … replicate the item element's HTML/CSS … leave no
unfilled [PLACEHOLDER]`.

### 6.4 Video — Step 1 scene script

*System (essentials):*
```
You are a lesson video scene architect. Convert raw lesson content into a structured video scene
script using block markers. Output ONLY the scene blocks — no JSON, no explanations.

Each block:
:::layout-name
[1-2 sentences describing what this scene should show, derived from the lesson content]
:::

RULES:
- Produce exactly {n_min} to {n_max} scene blocks (matching the target duration).
- First block MUST be 'intro'. Last block MUST be 'conclusion' or 'cycle-loop'.
- Choose layouts only from: intro, problem, framework, process, contrast, evaluation, options,
  conclusion, card-list, branching-flow, before-after, quad-grid, three-step-flow, cycle-loop,
  split-blueprint, fuel-engine, checklist-reveal, deployment-circles, editorial, comparison, gate,
  routing, stat-highlight, transform-text
- Match layout to content type [editorial=definitions, process=steps, framework=components,
  contrast=before/after, comparison=X vs Y, gate=confirm checkpoint, routing=sort to buckets,
  stat-highlight=one metric, transform-text=rewrite weak→sharp, …].
- {no-repeat rule}. Descriptions must be specific to the topic. Any text outside the blocks breaks
  the parser.
```
*User:* `Lesson title: {title}` + `Raw lesson content:` + the text + "Convert this lesson into a
structured video scene script using the block marker format."

### 6.5 Video — Step 2 scene JSON

*System (essentials — this is the big one):*
```
You are a professional instructional video designer and motion graphics director …
Your output MUST be a single valid JSON object — no markdown fences, no explanations.

Schema: { "title": "...", "accent": "...", "scenes": [ 6..10 scene objects ] }
Scene: { layout, title (≤6 words), on_screen_text (≤10 words), narration (1-2 sentences),
         items[], duration_frames (int) }
SceneItem: { text (required), sub_label?, featured?, role?, status?, icon?, badge?, description? }
Scene also accepts: { callout? }

ACCENT SELECTION table [gold/cyan_orange/emerald/violet/rose/teal by domain; prefer specificity].
AVAILABLE LAYOUTS and their items[] contract [the 24-row table from §4.4].
SCENE ORDERING RULES [first intro, last conclusion/cycle_loop; total {n_min}-{n_max} scenes;
  durations sum ≈ {target_frames}; each scene {frames_min}-{frames_max}].
BLOCK MODE RULES [when the user lists scenes: match count exactly, use the declared layout, honor
  the declared order; intro/conclusion position rules do NOT apply].
CONTENT RULES [topic-specific text; *asterisk* emphasis sparingly; confident narration; output
  ONLY the JSON].
DESIGN PRINCIPLES [layout variety, scene rhythm, icon economy, badge discipline, description
  usage, callout placement, editorial usage, progressive density, color specificity].
```
*User (block mode — when a Step-1 script is supplied):*
```
Lesson title: {title}

Generate exactly {N} scenes in the exact order listed below.
For each scene, use ONLY the specified layout — do not substitute a different layout.

Scene 1 — layout: intro
Content: {block 1 description}

Scene 2 — layout: process
Content: {block 2 description}
…
Generate the video script JSON now.
```
*Overlay addendum (appended only when a background clip is attached):* prefer text-forward
layouts, avoid center-owning layouts, keep 2–4 items per scene, add `overlay_anchor` to each scene.

---

## 7. Reconstruction checklist & caveats

Before shipping an agentic builder based on this blueprint:

- [ ] **Re-verify model IDs & regions.** `global.anthropic.claude-sonnet-4-6` / cross-region
      fallback, Bedrock region `us-east-1`, renderer region `us-east-2` — confirm all against the
      live provider/renderer catalog; they change.
- [ ] **Harden auth.** In the reference plugin every AI endpoint is effectively open (permission
      callbacks return true; capability checks are commented out). A real app **must** gate these
      behind proper authorization — generation calls cost money and write content.
- [ ] **Own the content save.** Content generation returns HTML; nothing persists it for you. Wire
      an explicit save (and ideally a "backup previous content" step, as the reference does).
- [ ] **Guard JSON mode.** Strip markdown fences before parsing; require non-empty `scenes[]`;
      do all mutation (duration normalization, overlay injection) deterministically post-parse.
- [ ] **Keep generated HTML inert.** Inline CSS only; no external CSS/JS/fonts; store interactive
      controls as sentinels and hydrate them client-side — do not let the model emit form elements
      into stored content.
- [ ] **Two regions, credential-free.** The reference uses cloud IAM roles (no keys in code) and
      two different regions for the LLM vs the renderer. Decide your own credential + region
      strategy explicitly.
- [ ] **Renderer must reach asset URLs publicly.** Any background clip (or other external asset)
      must be fetchable by the render service over the public internet — localhost/dev URLs fail.
- [ ] **Budget tokens to output size** (§2.2), and expect the render step to be **async** — build
      the submit → poll → record loop.

---

*Sources (this repository): [../includes/class-aws-bedrock-client.php](../includes/class-aws-bedrock-client.php),
[../lms/lms-rest-apis/class-ai-content-standard-generator.php](../lms/lms-rest-apis/class-ai-content-standard-generator.php),
[../lms/lms-rest-apis/class-ai-content-block-generator.php](../lms/lms-rest-apis/class-ai-content-block-generator.php),
[../lms/lms-rest-apis/class-ai-content-template-library.php](../lms/lms-rest-apis/class-ai-content-template-library.php),
[../lms/lms-rest-apis/ai-video.php](../lms/lms-rest-apis/ai-video.php), and the Remotion service under
`../remotion-video-service/`. Companion as-built video spec: [ai-video-context.md](ai-video-context.md).*
