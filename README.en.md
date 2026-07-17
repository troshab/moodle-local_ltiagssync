<!-- Українською: [README.md](README.md) -->
# local_ltiagssync - LTI Advantage per-activity grade sync

Provider-side companion for Moodle's **Publish as LTI tool** (`enrol_lti`). When a whole course is
shared over LTI 1.3, stock `enrol_lti` reports only the **course total** as a single AGS line item, so
the consumer platform's gradebook shows one aggregate column. This plugin additionally reports **every
graded activity as its own decoupled AGS line item**, so the consumer renders a separate gradebook
column per activity (quiz, assignment, ...).

## Where to install

On the **provider** Moodle (the one that publishes the course). The consumer needs no plugin - it just
receives the extra columns through the standard AGS line item service.

## Requirements

- Moodle **4.3+** on the provider (needs the Packback-based `enrol_lti` AGS tool-side sync).
- `auth_lti` and `enrol_lti` enabled; the course shared as an LTI tool over **LTI 1.3** with grade sync on.
- On the **consumer**, the external-tool configuration must allow column management:
  *IMS LTI Assignment and Grade Services* = **"Grade synchronisation and column management"**
  (`ltiservice_gradesynchronization = 2`). Without it the consumer will not accept the additional columns.

## How it works

- Reuses `enrol_lti`'s LTI 1.3 registration lookup, its resource-link / user repositories and the bundled
  `Packback\Lti1p3` AGS client - the exact machinery `enrol_lti`'s own tool-side grade sync uses. No JWT,
  OAuth2 or HTTP code is re-implemented.
- For each **course-level** shared resource with grade sync enabled, it enumerates the course grade items
  and creates one **decoupled** line item per activity (stable `resourceId = ltiagssync_gi_<gradeitemid>`),
  then posts each user's score keyed by their LTI `sub`. Identity mapping is handled by the LTI platform.
- Two triggers: a **scheduled task every 5 minutes** (backfill) and an **observer on
  `\core\event\user_graded`** for near-real-time delivery (queued with de-duplication).
- A small state table records the last grade sent per user/activity, so unchanged grades are not re-sent.
- **Coexists** with stock `enrol_lti`: the course-total column stays; this plugin *adds* the per-activity
  breakdown.

## Settings

*Site administration -> Plugins -> Local plugins -> LTI Advantage per-activity grade sync*

- **Enable per-activity grade sync** (default: on).
- **Include manual grade items** - also sync manually created gradebook columns (default: off).

## Verify

```bash
php admin/cli/scheduled_task.php --execute='\local_ltiagssync\task\sync_grades'
```

On the consumer, the shared course's gradebook should now show one column per activity. In the consumer
DB, `mdl_ltiservice_gradebookservices` gains one row per activity line item for that course.

## Limitations

- Text / "none" grade items are skipped (AGS transmits numeric values only); scale items are sent as their
  numeric index.
- Changing an activity's maximum grade does not retro-update an existing line item's `scoreMaximum` (backlog).

## License

Proprietary. Copyright (c) 2026 Trokhym Babych. All rights reserved.
Not open source - use, copying, or distribution only with the copyright holder's written permission.
See [LICENSE](LICENSE).
