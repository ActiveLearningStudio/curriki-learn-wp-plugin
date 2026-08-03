# Admin UI/UX for AI Features — Complete File Guide

This document maps all PHP, JavaScript, CSS, and documentation files that power the admin-side UI/UX for **AI Lesson Content Generation** and **AI Lesson Video Generation** in the TinyLxp WordPress plugin.

---

## PHP Files

### Core Admin Orchestration

| File | Purpose |
|------|---------|
| [admin/class-tiny-lxp-platform-admin.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/class-tiny-lxp-platform-admin.php) | **Main admin orchestrator** — enqueues JS/CSS on post edit pages (`post.php`, `post-new.php`); loads CKEditor 4.20.1; manages admin page registration (settings, tools, Edlink integration). Entry point for all admin-side asset loading. Hooks: `admin_enqueue_scripts`, `add_options_page`, `add_menu_page`. |

### Metabox & Modal Rendering

| File | Purpose |
|------|---------|
| [lms/class-learnpress-lesson-extension.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/lms/class-learnpress-lesson-extension.php) | **Metabox renderer & modal HTML generator** — adds metaboxes to LearnPress lesson CPT via `add_meta_box()`; renders: (1) AI Content Gen metabox with Full Lesson / Block Markers / Reset buttons, (2) AI Video metabox with Generate Video button, (3) complete 2-step video wizard modal HTML (fixed overlay, textarea inputs, polling status). ~400+ lines of inline HTML generation. Methods: `ai_content_metabox()`, `ai_content_metabox_html()`, displays block picker dropdown, handles video status display. |

---

## JavaScript Files

| File | Lines | Purpose |
|------|-------|---------|
| [admin/js/tiny-lxp-platform-post.js](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/js/tiny-lxp-platform-post.js) | ~1100 | **Primary frontend UI logic** — orchestrates all admin interactions: (1) **Full Lesson**: "Generate Full Lesson" button click → extracts editor content → calls `/lms/v1/lesson/ai-content` → returns HTML + template_id → displays status; (2) **Block Markers**: dropdown picker insertion, block type selection, "Generate Marked Blocks" button → calls `/lms/v1/lesson/ai-content-blocks` → renders each block separately; (3) **Reset**: "Restore Pre-AI Content" confirmation dialog → restores pre-generation backup; (4) **Video 2-step wizard**: modal open/close, Step 1 raw text + duration input → calls `/lms/v1/lesson/ai-video-script` → receives scene script, Step 2 shows script (editable) → calls `/lms/v1/lesson/ai-video` → polls `/lms/v1/lesson/ai-video` every ~5s for render status (pending/done/error), auto-closes modal on completion; (5) **Video actions**: Copy Link button, Insert Into Editor button (embeds video iframe); (6) **Button state management**: disables/enables all AI buttons during generation. Key functions: `tinyLxpHandleAiGenerate()`, `tinyLxpHandleAiBlocks()`, `lxpRenderVideoActionArea()`, `tinyLxpSetAiButtonsDisabled()`. |

---

## CSS Files

| File | Lines | Purpose |
|------|-------|---------|
| [admin/css/tiny-lxp-platform-post.css](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/css/tiny-lxp-platform-post.css) | 300+ | **Complete visual styling** for AI metabox + 2-step video modal: (1) AI action group cards (primary/blocks/video/reset sections) with gradient backgrounds & colored borders; (2) button states (active, disabled, hover); (3) block picker dropdown (position: absolute, z-index management, hover/focus states); (4) modal overlay + panel (fixed positioning, centered via transform, shadow); (5) textarea styling with focus ring; (6) video action buttons + status text colors (green for ok, red for error); (7) help icon hover effects; (8) responsive spacing, borders, typography. Uses CSS variables where applicable, inline-ready (no external font/icon dependencies beyond WordPress dashicons). |

---

## Admin Reference/Documentation Pages (PHP Partials)

### Block Marker DSL Guide

| File | Lines | Purpose |
|------|-------|---------|
| [admin/partials/block-reference-admin.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/partials/block-reference-admin.php) | 300+ | **Interactive Block Marker DSL Reference** — displays all ~20 block types as responsive grid cards; each card includes: (1) block type name + marker slug (e.g., `:::case-study`), (2) plain-English description, (3) best-use case (when to apply), (4) sample `:::block-type\n...\n:::` fence code block showing syntax, (5) live preview (scaled 62% to fit in card). Authors click the "Open Block Reference" help icon (from metabox) to consult before writing block markers in lesson content. Features: auto-grid layout, copy-to-clipboard buttons for markers, scrollable preview pane. |

### Video Scene Layout Catalog

| File | Lines | Purpose |
|------|-------|---------|
| [admin/partials/video-layouts-reference-admin.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/partials/video-layouts-reference-admin.php) | 400+ | **24-Layout Video Scene Catalog** — comprehensive guide to all 24 scene layouts available in AI Video generation: intro, problem, framework, process, contrast, evaluation, options, conclusion, card-list, branching-flow, before-after, quad-grid, three-step-flow, cycle-loop, checklist-reveal, deployment-circles, dial-gauge, staircase, matrix, numbered-list, split-screen, testimonial, network-graph, header-footer. Each layout documented as: (1) slug (machine name), (2) display name, (3) detailed description of visual/content structure, (4) best-use case (pedagogical guidance), (5) sample `:::layout-name\n[description]\n:::` fence syntax. Informational guide for Step 1 (video script writing). Helps authors understand layout constraints & choose appropriate scene types. |

---

## Documentation & Blueprint Files (Markdown)

| File | Purpose |
|------|---------|
| [docs/ai-video-context.md](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/docs/ai-video-context.md) | **As-Built Specification for AI Video Feature** — technical details: 24 layouts with item contracts, duration tiers & frame calculations, scene JSON schema (Scene/SceneItem/InputProps), accent color palettes, Remotion Lambda rendering, async polling. Reference for developers implementing or debugging video generation. Companion to `ai-course-creation-blueprint.md`. |
| [docs/ai-course-creation-blueprint.md](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/docs/ai-course-creation-blueprint.md) | **Reusable AI Blueprint for External Course-Creation Apps** — NEW document (created for external AI consumption); covers both AI Lesson Content (classify→fill strategy + block-marker DSL strategy) and AI Lesson Video (2-step generation wizard) pipelines in provider-agnostic, architecture-focused language; includes: prompt reference appendix (classifier, template fill, block render, video script, video JSON), duration model & normalization, 24-layout + 6-accent tables, scene JSON schema in JSONC, suggested agentic orchestration patterns, reconstruction checklist for a new app, caveats (auth hardening, JSON-mode brittleness, credential strategy). Designed for another AI system to ingest and build a standalone course-generation tool. |

---

## UI/UX Architecture Diagram

```
┌─────────────────────────────────────────────────────────┐
│  LearnPress Lesson Edit Page (post.php / post-new.php)  │
└──────────────────────┬──────────────────────────────────┘
                       │
        ┌──────────────▼───────────────┐
        │ Admin Orchestrator Loads:    │
        │                              │
        │ class-tiny-lxp-platform-admin.php
        │  ├─ enqueue_scripts()        │
        │  │  ├─ CSS:                  │
        │  │  │  └─ tiny-lxp-platform- │
        │  │  │      post.css          │
        │  │  ├─ JS:                   │
        │  │  │  └─ tiny-lxp-platform- │
        │  │  │      post.js           │
        │  │  └─ CKEditor 4.20.1       │
        │  └─ wp.media picker          │
        │     (for video bg-clip)      │
        └──────────────┬───────────────┘
                       │
        ┌──────────────▼───────────────────────────────────┐
        │ LearnPress Lesson Metaboxes Render:             │
        │                                                  │
        │ lms/class-learnpress-lesson-                    │
        │   extension.php                                 │
        │  ├─ ai_content_metabox()                        │
        │  │  ├─ "Generate Full Lesson" button ──────┐   │
        │  │  ├─ "Block Markers" section              │   │
        │  │  │  ├─ Block Picker Dropdown ─────────┐ │   │
        │  │  │  └─ "Generate Marked Blocks" btn    │ │   │
        │  │  └─ "Restore Pre-AI Content" btn       │ │   │
        │  │                                        │ │   │
        │  └─ ai_video_metabox()                    │ │   │
        │     ├─ "Generate Video" button ──────┐   │ │   │
        │     └─ Video modal HTML (2-step wizard)   │ │   │
        │        ├─ Step 1: raw text + duration     │ │   │
        │        └─ Step 2: script editor + poll    │ │   │
        │                                           │ │   │
        └───────────────────────────────────────────┼─┼───┘
                                                    │ │
            Reference Pages (Help Links)           │ │
            ┌───────────────────────────────┬──────┘ │
            │                               │        │
    ┌───────▼──────────────┐      ┌────────▼─────────┐
    │ block-reference-     │      │ video-layouts-   │
    │   admin.php          │      │   reference-     │
    │                      │      │   admin.php      │
    │ ~20 block types      │      │                  │
    │ ├─ name             │      │ 24 scene layouts  │
    │ ├─ marker (:::)     │      │ ├─ intro          │
    │ ├─ description      │      │ ├─ problem        │
    │ ├─ best-use-case    │      │ ├─ framework      │
    │ └─ sample code      │      │ ├─ process        │
    └────────────────────┘      │ ├─ ... (24 total) │
                                 └──────────────────┘
    
    User Interactions Flow:
    
    1. Author opens lesson edit page
       └─> admin/class-tiny-lxp-platform-admin.php enqueues all assets
    
    2. Metabox renders in sidebar
       └─> lms/class-learnpress-lesson-extension.php outputs buttons/modal
    
    3. Author clicks "Generate Full Lesson"
       └─> admin/js/tiny-lxp-platform-post.js extracts editor content
           └─> calls POST /lms/v1/lesson/ai-content REST endpoint
               └─> returns {content, template_id}
                   └─> displays status + inserts into editor
    
    4. Author clicks "Insert Block Marker"
       └─> JS opens dropdown (block-reference-admin.php styles/layout)
           └─> author selects block type
               └─> JS inserts :::block-type\n...\n::: fence
    
    5. Author clicks "Open Block Reference" help icon
       └─> opens admin/partials/block-reference-admin.php in new window
           └─> displays all 20 block types + samples
    
    6. Author clicks "Generate Video"
       └─> modal opens (HTML from lms/class-learnpress-lesson-extension.php)
           ├─ Step 1: paste raw text + duration (M:SS)
           │  └─> calls POST /lms/v1/lesson/ai-video-script
           │      └─> returns scene script (:::layout\n...\n:::)
           │
           └─ Step 2: optionally edit script
              └─> calls POST /lms/v1/lesson/ai-video
                  └─> returns JSON {title, accent, scenes[]}
                      └─> starts polling GET /lms/v1/lesson/ai-video every ~5s
                          └─> status='done' → "Play", "Copy Link", "Insert" appear
                              └─> JS inserts video iframe into editor
```

---

## Key UI Components & Their Files

### AI Content Metabox
- **Rendered by**: [lms/class-learnpress-lesson-extension.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/lms/class-learnpress-lesson-extension.php) (method `ai_content_metabox_html()`)
- **Styled by**: [admin/css/tiny-lxp-platform-post.css](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/css/tiny-lxp-platform-post.css) (classes: `.lxp-ai-action-group-primary`, `.lxp-ai-action-group-blocks`, `.lxp-ai-action-group-reset`)
- **Interactions by**: [admin/js/tiny-lxp-platform-post.js](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/js/tiny-lxp-platform-post.js) (handlers for button clicks, block picker, AJAX calls)

### AI Video Metabox & Modal
- **Rendered by**: [lms/class-learnpress-lesson-extension.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/lms/class-learnpress-lesson-extension.php) (method `ai_video_metabox_html()`, includes full modal HTML)
- **Styled by**: [admin/css/tiny-lxp-platform-post.css](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/css/tiny-lxp-platform-post.css) (classes: `.lxp-ai-action-group-video`, `.lxp-ai-video-modal-*`)
- **Interactions by**: [admin/js/tiny-lxp-platform-post.js](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/js/tiny-lxp-platform-post.js) (Step 1/2 inputs, polling, status updates)

### Block Picker Dropdown
- **Items rendered by**: [lms/class-learnpress-lesson-extension.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/lms/class-learnpress-lesson-extension.php) (method `get_ai_block_types()`, populates dropdown with block list)
- **Styled by**: [admin/css/tiny-lxp-platform-post.css](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/css/tiny-lxp-platform-post.css) (classes: `.lxp-ai-block-picker-list`, `.lxp-block-picker-item`)
- **Interactions by**: [admin/js/tiny-lxp-platform-post.js](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/js/tiny-lxp-platform-post.js) (open/close, selection, insertion)

### Block Reference Guide
- **Hosted at**: [admin/partials/block-reference-admin.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/partials/block-reference-admin.php)
- **Linked from**: [lms/class-learnpress-lesson-extension.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/lms/class-learnpress-lesson-extension.php) (help icon in metabox)
- **Fetches data from**: [lms/lms-rest-apis/ai-content.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/lms/lms-rest-apis/ai-content.php) (method `Rest_Lxp_AI_Content::get_block_catalog()`)

### Video Layouts Guide
- **Hosted at**: [admin/partials/video-layouts-reference-admin.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/partials/video-layouts-reference-admin.php)
- **Informational only** — helps authors understand scene layout options before writing video scripts

---

## File Dependencies & Load Order

### On Lesson Edit Page Load:
1. WordPress loads `post.php` / `post-new.php`
2. Hook `admin_enqueue_scripts` fires
3. [admin/class-tiny-lxp-platform-admin.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/class-tiny-lxp-platform-admin.php) enqueues:
   - CSS: [admin/css/tiny-lxp-platform-post.css](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/css/tiny-lxp-platform-post.css)
   - JS: [admin/js/tiny-lxp-platform-post.js](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/js/tiny-lxp-platform-post.js)
   - External: CKEditor 4.20.1 from CDN
   - WP Media (for video background clip picker)
4. Hook `add_meta_boxes` fires
5. [lms/class-learnpress-lesson-extension.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/lms/class-learnpress-lesson-extension.php) renders metaboxes (AI Content + AI Video)
6. JS in [admin/js/tiny-lxp-platform-post.js](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/js/tiny-lxp-platform-post.js) initializes event handlers on `document.ready`

### On Author Click "Generate Full Lesson":
1. [admin/js/tiny-lxp-platform-post.js](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/js/tiny-lxp-platform-post.js) extracts editor content
2. jQuery AJAX POST to `/lms/v1/lesson/ai-content`
3. REST endpoint calls [lms/lms-rest-apis/class-ai-content-standard-generator.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/lms/lms-rest-apis/class-ai-content-standard-generator.php)
4. Returns `{content, template_id}`
5. [admin/js/tiny-lxp-platform-post.js](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/js/tiny-lxp-platform-post.js) displays result + updates status message (styled by [admin/css/tiny-lxp-platform-post.css](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/css/tiny-lxp-platform-post.css))

### On Author Click "Generate Video":
1. [admin/js/tiny-lxp-platform-post.js](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/js/tiny-lxp-platform-post.js) opens modal (HTML from [lms/class-learnpress-lesson-extension.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/lms/class-learnpress-lesson-extension.php))
2. Modal styled by [admin/css/tiny-lxp-platform-post.css](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/css/tiny-lxp-platform-post.css)
3. Step 1: POST to `/lms/v1/lesson/ai-video-script`
4. Step 2: POST to `/lms/v1/lesson/ai-video`
5. Polling loop: GET to `/lms/v1/lesson/ai-video` every ~5s
6. All status updates displayed in modal (styled by CSS)

---

## Summary: File Responsibilities

| File | Responsibility |
|------|-----------------|
| [admin/class-tiny-lxp-platform-admin.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/class-tiny-lxp-platform-admin.php) | Asset loading orchestration |
| [lms/class-learnpress-lesson-extension.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/lms/class-learnpress-lesson-extension.php) | Metabox registration + HTML generation (buttons, modals, status areas) |
| [admin/js/tiny-lxp-platform-post.js](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/js/tiny-lxp-platform-post.js) | User interaction handling + AJAX calls + status display + modal state management |
| [admin/css/tiny-lxp-platform-post.css](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/css/tiny-lxp-platform-post.css) | Visual styling for all UI components |
| [admin/partials/block-reference-admin.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/partials/block-reference-admin.php) | Block marker DSL documentation page |
| [admin/partials/video-layouts-reference-admin.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/partials/video-layouts-reference-admin.php) | Video scene layout catalog page |
| [docs/ai-video-context.md](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/docs/ai-video-context.md) | As-built technical specification |
| [docs/ai-course-creation-blueprint.md](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/docs/ai-course-creation-blueprint.md) | Reusable blueprint for external AI systems |

---

## Quick Reference: What to Edit

- **To change button text/labels** → [lms/class-learnpress-lesson-extension.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/lms/class-learnpress-lesson-extension.php)
- **To change styling (colors, spacing, layout)** → [admin/css/tiny-lxp-platform-post.css](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/css/tiny-lxp-platform-post.css)
- **To change interaction behavior (button clicks, AJAX)** → [admin/js/tiny-lxp-platform-post.js](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/js/tiny-lxp-platform-post.js)
- **To update block marker guide** → [admin/partials/block-reference-admin.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/partials/block-reference-admin.php)
- **To update video layout guide** → [admin/partials/video-layouts-reference-admin.php](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/admin/partials/video-layouts-reference-admin.php)
- **To document feature for external AI apps** → [docs/ai-course-creation-blueprint.md](https://raw.githubusercontent.com/ActiveLearningStudio/curriki-learn-wp-plugin/refs/heads/main/docs/ai-course-creation-blueprint.md)
