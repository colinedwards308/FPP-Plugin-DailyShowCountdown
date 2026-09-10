# Submission audit — 2026-09-10

Reviewed the current [plugin guidelines](https://github.com/FalconChristmas/fpp-plugin-Template/blob/master/PLUGIN_GUIDELINES.md),
[metadata format](https://github.com/FalconChristmas/fpp-plugin-Template/blob/master/PLUGININFO_FORMAT.md),
and [submission rules](https://github.com/FalconChristmas/fpp-data/blob/master/PLUGINS.md).
This is a local audit, not a passing result from FPP's automated submission reviewer.

## Passed

- 86 automated assertions on the FPP device's PHP 8.4 runtime, using an isolated
  temporary directory and mocked overlay requests.
- Time formats, rounding, daily windows, overnight windows, DST day rollover,
  past event rejection, settings validation and encoding.
- Real HTTP requests to the isolated settings/preview API: successful save,
  invalid-save preservation, checkbox persistence, and preview without output.
- Duplicate starts, background/foreground operation, stop, SIGTERM, FPP's
  `--type lifecycle shutdown` callback, render-error cleanup, active-overlay
  refusal, completion, and repeated uninstall.
- PHP, shell, JavaScript, and JSON syntax; whitespace checks.
- Page structure: one Stop button inside Controls, outside the disabled settings
  fieldset so it remains available during loading or a settings-load failure.
- Development installation on FPP at 192.168.1.71. The real FPP page serves the
  form and menu entry. Configuration save/load and preview work through the real
  API. FPP reports all three commands registered after its plugin load API.
- Live API integration exposed a reserved `/settings` route collision; plugin
  routes now live under `/api/plugin/FPP-Plugin-DailyShowCountdown/countdown/`.
- Live model inspection confirmed `isActive`, `effectRunning`, and `isLocked`
  fields; the runtime checks these before taking over a model.
- Metadata validated against the current official Draft 2020-12 JSON Schema,
  including URI format checks. Repository name, URLs and `main` branch agree.
- Declared apt dependencies (`python3-pil`, `fontconfig`) are restricted to
  Debian-family platforms; minimum FPP version is 10.0, which supports them.
- Start Now button tests cover enabled/disabled saves, failed saves, edits,
  and editing while a save is pending (`node tests/start-button.cjs`).
- Menu now declares one documented `output` entry and escapes generated links.
- Status writes reject a missing/unwritable plugin data directory before
  `tempnam` can fall back to a system temporary directory.
- Runtime logging uses one append-only FPP-managed log; no custom rotation.
  Test-only mock request logs are confined to disposable test fixtures.
- Stop hooks request cooperative shutdown without polling. Uninstall waits
  up to 12 seconds for cleanup and is tested twice against fixtures.
- Source audit found no telemetry, advertising, payment links, tunneling,
  direct FPP restarts, remote shell execution, or service/config reconfiguration.
- Root icon is 256 × 256 PNG. MIT license, README and issue URL are present.

The repository is public and published as `FPP-Plugin-DailyShowCountdown`.
Repository metadata allows normal updates. The device API reports FPP 10.0
on branch `v10.0`; GitHub's latest-release endpoint also reports 10.0 (released
2026-08-21). This confirms the test runtime is the latest release, not nightly.
The audit did not reinstall/uninstall the live plugin, change saved settings,
start physical output, or upgrade FPP.

## Open code/design review items

### Submission-check follow-up

- Committed executable permissions for install/uninstall/preStop hooks and
  the callback/command shell entry points (Git mode 100755).
- The flagged `unlink($tmp)` is internal cleanup of the temporary file created
  by that same `writeState()` invocation. Added an explicit CLI-only guard;
  web execution is rejected before file creation or deletion. Isolated HTTP
  tests verify both GET and POST are rejected and leave status unchanged.
- These changes still need the submission reviewer's `/recheck`; passing local
  tests does not establish that the automated review has accepted the fix.

- Guideline 8.1 recommends `PrintSetting*`/toggle helpers. The current form uses
  Bootstrap controls and an explicit Save action with atomic JSON validation;
  persistence uses FPP's `ReadSettingFromFile`/`WriteSettingToFile` helpers.
  This is not a claim of literal compliance with the UI-helper requirement.
  Assess helper integration without losing validation/save behavior, or raise
  the design with maintainers before submission.
- Preview colors intentionally represent the user's matrix colors, not UI
  theme colors. All surrounding UI uses semantic Bootstrap classes. Verify
  preview readability in both themes; custom content colors are not proof of
  theme compliance.
- Separate-color rendering has a 1,048,576-pixel cap and process timeout, but
  low-end hardware CPU/RAM performance has not been measured. Do not infer
  tested hardware coverage from the platform compatibility list or invent
  resource hints without measurements.

## Still required before registry submission

- Rendered UI validation on desktop/mobile and light/dark themes. No browser
  was connected during this session, so markup/HTTP checks do not establish
  visual layout quality.
- Physical matrix validation of font fit, seconds updates, completion and stop.
- Physical comparison of native single-color and separate-color text layout.
- Fresh installation, upgrade and uninstall acceptance tests on both the latest
  release and a current nightly; isolated tests do not replace these checks.
- Exercise repeated installation and upgrade through Plugin Manager on a test
  controller; source review of idempotency is not an acceptance test.
- Resolve the UI-helper review item and measure representative low-end use.
- Publish these audit fixes after review, then submit the registry request.

## Acceptance test record to complete

### Completed: live command registration lifecycle, FPP 10.0

On 2026-09-10, tested through the same Plugin Manager endpoints used by the UI:
`DELETE /api/plugin/FPP-Plugin-DailyShowCountdown?stream=true`, followed by
`POST /api/plugin?stream=true` using the public repository metadata and `main`.
Reinstalled commit `7258c56`.

- Backed up the installed plugin, runtime files and saved configuration before
  testing. Countdown was already stopped; issued Stop and verified stopped state.
- Before uninstall: all three Daily Show Countdown commands present in
  `GET /api/commands`.
- After uninstall: zero countdown commands, and the plugin directory, own
  configuration and runtime directory were removed. Declared package dependencies
  were removed by FPP's package ownership workflow.
- After reinstall: all three commands returned automatically; dependencies were
  installed and the installer completed with exit code 0. Fresh defaults were
  disabled before restoring configuration.
- FPP's MainPID stayed `1069`, with ExecMainStartTimestampMonotonic `9960504`
  before uninstall, after uninstall and after reinstall. No FPP restart or
  direct manual load/unload calls were used.
- Restored configuration through the validated settings API and compared every
  setting with the backup. Countdown was left stopped, matching its initial state.

This verifies live command registration/withdrawal on this FPP 10.0 build; it
does not establish nightly, physical-display, or repeated-upgrade coverage.

For both latest release and nightly, record the exact FPP version, platform,
model dimensions, and results for: fresh install; repeated install; upgrade
with saved settings preserved; enabled/disabled saves; missing-font rejection;
daily/event and overnight scheduling; all time-unit combinations; both color
paths; completion; manual/scheduled Stop; FPP shutdown; and uninstall cleanup.
Record screenshots at approximately 320px and desktop widths in both themes.
Use spare hardware or a separate test image for nightly testing, not an
unapproved upgrade of the active show controller.
