# Development validation — 2026-09-10

## Passed

- 80 automated assertions on the FPP device's PHP 8.4 runtime, using an isolated
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

The development installation uses Small Matrix. Its metadata disables GitHub
updates because these changes are not published yet. Test uninstall operations
used fixtures only. Legacy standalone scripts and their obsolete playlists and
schedule were removed after local recovery copies were saved. Plugin commands
now call the runtime directly.

## Still required before registry submission

- Rendered UI validation on desktop/mobile and light/dark themes. No browser
  was connected during this session, so markup/HTTP checks do not establish
  visual layout quality.
- Physical matrix validation of font fit, seconds updates, completion and stop.
- Physical comparison of native single-color and separate-color text layout.
- Fresh installation, upgrade and uninstall acceptance tests on both the latest
  release and a current nightly; isolated tests do not replace these checks.
- Publish the tested version to GitHub, restore normal update metadata on the
  development installation, then submit the registry request.
