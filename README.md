# tool_sebprep — SEB Preparation Setup for Module Courses

A Moodle admin tool that automates preparation of module courses for Safe Exam Browser (SEB) exams. It copies a standardised SEB preparation section from a template course into any number of target courses in two steps.

## Requirements

- Moodle 5.0.6+ (requires 2026021600)
- Plugin [mod_subcourse](https://moodle.org/plugins/mod_subcourse)
- Plugin [block_completion_progress](https://moodle.org/plugins/block_completion_progress)
- `moodle/site:config` capability (site administrator)

## Installation

1. Copy the plugin folder into `admin/tool/sebprep/`.
2. Run the Moodle upgrade (`/admin/index.php`).
3. The tool appears under **Site administration → Tools → SEB Preparation Setup**.

## Usage

### Common fields

| Field | Description |
|---|---|
| **Course URLs** | One Moodle course URL per line (`?id=…`) |
| **Semester tag** | e.g. `FS26` or `HS26` — appended to the section title and used in the idnumber (`SEB-PREP-FS26`) |
| **Language** | Deutsch copies section 1 from the template; English copies section 2 |
| **Mock exam deadline** | Replaces `{{FRIST_PROBE}}` in the subcourse description |
| **Replacement device deadline** | Replaces `{{FRIST_ERSATZ}}` in the subcourse description |

### Step 1 — Insert course section

Copies section 1 (DE) or section 2 (EN) of the template course into every target course at position 1. For each target course the step:

- Enables completion tracking.
- Inserts a new section with the template's name + semester tag.
- Copies all activities (resource, subcourse, assign) from the template section.
- Sets a unique `idnumber` (`SEB-PREP-<tag>`) on the subcourse module.
- Skips courses where the section or a subcourse with that semester tag already exists.
- Records a warning if an older semester's subcourse is still present in the course.

**Additional field required:** Template course ID.

### Step 2 — Add progress bar

Finds the subcourse added in Step 1 (by its `idnumber`) and inserts a `completion_progress` block titled *SEB Vorbereitung \<tag\>* into the right sidebar of each target course. Skips courses where the block already exists.

### Preview & Execute

Both steps use a preview screen that shows which courses will be processed before any changes are made. After reviewing, click **Execute now** to apply.

### Warning CSV download

After execution, any courses that triggered warnings (e.g. an old semester's subcourse still present) are listed in a warning table. The table can be exported as a UTF-8 CSV file (Excel-compatible) via the **Download warnings** button.

## Template course structure

```
Template course
├── Section 1  (DE content)
│   ├── subcourse  — link to SEB preparation course ({{FRIST_PROBE}}, {{FRIST_ERSATZ}})
│   └── assign     — device registration task
└── Section 2  (EN content)
    ├── resource
    ├── subcourse
    └── assign
```

## idnumber scheme

| Language | idnumber |
|---|---|
| Deutsch | `SEB-PREP-<tag>` e.g. `SEB-PREP-FS26` |
| English | `SEB-PREP-EN-<tag>` e.g. `SEB-PREP-EN-FS26` |

## Skip conditions

The tool never overwrites existing content. A course is skipped (with a status of *Skipped*) when:

- A section with the same name + semester tag already exists, **or**
- A subcourse with the same semester tag in its name already exists (Step 1).
- A `completion_progress` block with the same title already exists (Step 2).

## Files

| File | Purpose |
|---|---|
| `version.php` | Plugin metadata |
| `settings.php` | Registers the admin menu entry |
| `lib.php` | All business logic (parsing, validation, section/module copy, block insertion) |
| `index.php` | Admin UI — form, preview, execute, result table |
| `download_warnings.php` | Streams the warning list as a CSV file |
| `lang/de/tool_sebprep.php` | German strings |
| `lang/en/tool_sebprep.php` | English strings |
