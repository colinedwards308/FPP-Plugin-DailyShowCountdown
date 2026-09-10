<?php
require_once __DIR__ . '/lib/countdown.php';
?>
<div id="daily-countdown" class="container-fluid py-3">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div><h2>Daily Show Countdown</h2><p class="text-body-secondary mb-0">Show the time remaining until your show or event on an FPP display.</p></div>
    <span id="dc-status" class="badge text-bg-secondary text-wrap text-break" role="status">Loading status…</span>
  </div>
  <div id="dc-notice" class="alert alert-info" role="status">Loading settings…</div>
  <form id="dc-form">
      <div class="row g-4">
        <fieldset id="dc-fields" class="col-12 col-lg-7" disabled>
          <section class="border rounded p-3 mb-3">
            <h3 class="h5">When does your show or event begin?</h3>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="dc-enabled" name="enabled"><label class="form-check-label" for="dc-enabled">Enable countdown</label><div class="form-text">Allow manual and scheduled starts. To stop a countdown already running, use Stop countdown.</div></div>
            <div class="row g-3">
              <div class="col-12 col-sm-6"><label class="form-label" for="dc-mode">Count down to</label><select class="form-select" name="mode" id="dc-mode"><option value="daily">A daily time</option><option value="event">A specific date and time</option></select></div>
              <div class="col-12 col-sm-6"><label class="form-label" for="dc-target">Show / event start time</label><input class="form-control" type="time" step="1" name="targetTime" id="dc-target" required></div>
              <div class="col-12" id="dc-event-fields" hidden><label class="form-label" for="dc-date">Event date</label><input class="form-control" type="date" name="eventDate" id="dc-date"></div>
              <div class="col-12" id="dc-window-fields">
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="useWindow" id="dc-window"><label class="form-check-label" for="dc-window">Limit scheduled starts to a time window</label></div>
                <label class="form-label" for="dc-start">Earliest allowed countdown start</label><input class="form-control" type="time" step="1" name="startTime" id="dc-start" required>
                <div class="form-text">The window ends when your show begins. It does not start the countdown automatically; set up an FPP schedule below. Start now ignores this restriction. For a window across midnight, choose an earlier show time, such as 11 PM to 1 AM.</div>
              </div>
            </div>
            <p class="form-text mb-0" id="dc-clock">Times use the FPP device’s timezone.</p>
          </section>
          <section class="border rounded p-3 mb-3">
            <h3 class="h5">Display</h3>
            <div class="row g-3">
              <div class="col-12"><label class="form-label" for="dc-model">Display model</label><select class="form-select" name="model" id="dc-model"><option value="">Select a model…</option></select><div class="form-text">Choose the pixel overlay model configured in FPP for your matrix or display.</div></div>
              <div class="col-12"><label class="form-label" for="dc-heading">Heading</label><input class="form-control" name="heading" id="dc-heading" maxlength="128"><div class="form-text">Leave blank for the timer alone.</div></div>
              <div class="col-12 col-sm-6"><label class="form-label" for="dc-heading-color">Heading color</label><input class="form-control form-control-color" type="color" name="headingColor" id="dc-heading-color"><div class="form-text">Color of “Show begins in:” or your custom heading.</div></div>
              <div class="col-12 col-sm-6"><label class="form-label" for="dc-timer-color">Timer color</label><input class="form-control form-control-color" type="color" name="timerColor" id="dc-timer-color"><div class="form-text">Also used for the completion message.</div></div>
              <div class="col-12 col-sm-8"><label class="form-label" for="dc-font">Font</label><select class="form-select" name="font" id="dc-font"></select><div class="form-text">Only fonts available in FPP are listed.</div></div>
              <div class="col-12 col-sm-4"><label class="form-label" for="dc-size">Font size</label><input class="form-control" type="number" name="fontSize" id="dc-size" min="4" max="200" required><div class="form-text">Used for both heading and timer. Reduce it if text is cut off.</div></div>
              <div class="col-12"><p class="form-label">Show time units</p><div class="d-flex flex-wrap gap-4">
                <div class="form-check"><input class="form-check-input" type="checkbox" name="showHours" id="dc-hours"><label class="form-check-label" for="dc-hours">Hours</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="showMinutes" id="dc-minutes"><label class="form-check-label" for="dc-minutes">Minutes</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="showSeconds" id="dc-seconds"><label class="form-check-label" for="dc-seconds">Seconds</label></div>
              </div><div class="form-text">Select at least one. Hours + minutes + seconds displays a timer such as 01:30:00.</div><details class="small mt-2"><summary>How other time formats work</summary><p class="mt-2 mb-0">Minutes + seconds shows total minutes, such as 90:00. Seconds alone shows 5400s. Hours + seconds uses labels, such as 01h 1800s, to avoid confusion. Hidden smaller units are rounded up so the timer does not reach zero early.</p></details></div>
            </div>
          </section>
          <section class="border rounded p-3">
            <h3 class="h5">When the countdown finishes</h3>
            <label class="form-label" for="dc-completion">Completion message</label><input class="form-control mb-3" name="completionMessage" id="dc-completion" maxlength="128" placeholder="Leave blank to show zero">
            <label class="form-label" for="dc-hold">Show the final message or zero for (seconds)</label><input class="form-control" type="number" name="holdSeconds" id="dc-hold" min="0" max="60" required>
            <div class="form-text">After this delay, the countdown disappears. Use 0 to clear immediately when the countdown ends. This plugin does not start your show playlist.</div>
          </section>
        </fieldset>
        <div class="col-12 col-lg-5">
          <section class="border rounded p-3 mb-3">
            <h3 class="h5">Text preview</h3>
            <pre id="dc-preview" class="bg-dark text-light rounded p-3 text-center fs-4" style="white-space: pre-wrap; overflow-wrap: anywhere" aria-live="polite">Select your settings</pre>
            <p id="dc-preview-note" class="small text-body-secondary">Preview does not change the matrix. It shows the text, not an exact simulation of font fit.</p>
            <button type="button" class="btn btn-outline-secondary" id="dc-preview-button" disabled>Refresh preview</button>
          </section>
          <section class="border rounded p-3 mb-3">
            <h3 class="h5">Controls</h3>
            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-primary" type="submit" id="dc-save" disabled>Save settings</button>
              <button class="btn btn-outline-primary" type="button" id="dc-run" disabled>Start now</button>
              <button class="btn btn-danger" type="button" id="dc-stop">Stop countdown</button>
            </div>
            <p class="small mt-3">To begin: check Enable countdown, save your settings, then choose Start now. Saving alone does not start the display.</p>
            <p id="dc-start-help" class="small text-body-secondary">For a daily time, Start now counts down to today’s show time, or tomorrow’s if it has already passed.</p>
            <p class="small text-body-secondary mb-0">Changes take effect the next time you start. To apply changes to a running countdown, save, stop, then start again. Stop countdown clears the display without saving your edits.</p>
          </section>
          <section class="border rounded p-3">
            <h3 class="h5">Schedule automatic starts</h3>
            <ol class="ps-3"><li class="mb-2">Enable the countdown and save your settings here.</li><li class="mb-2">In FPP’s scheduler, add a command entry using <strong>Daily Show Countdown Start</strong>.</li><li class="mb-2">Choose the days and time to begin displaying the countdown. If you use a time window, choose a time inside it.</li><li>To stop early, schedule <strong>Daily Show Countdown Stop</strong> or use the Stop button here.</li></ol>
            <p class="small">For a one-time event, schedule the command for a time before the event.</p>
            <p class="small">The countdown runs independently after it starts. Stopping a playlist does not stop it.</p>
            <p class="small mb-0">Stop any other effect using the same display model before starting this countdown.</p>
          </section>
        </div>
      </div>
  </form>
</div>
<script>
(() => {
  const root = document.getElementById('daily-countdown');
  const form = document.getElementById('dc-form');
  const base = '/api/plugin/FPP-Countdown-Advanced/countdown/';
  let saved = null;
  let previewGeneration = 0;
  const notice = (text, kind = 'info') => {
    const node = root.querySelector('#dc-notice');
    node.className = 'alert alert-' + kind;
    node.textContent = text;
  };
  async function request(url, body) {
    const response = await fetch(url, body === undefined ? {} : {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)});
    const text = await response.text();
    const contentType = response.headers.get('content-type') || '';
    let data = text;
    if (contentType.includes('application/json')) {
      try { data = JSON.parse(text); }
      catch (e) { throw Error('FPP returned malformed JSON.'); }
    }
    if (!response.ok) throw Error(typeof data === 'string' && data.trim() ? data.trim() : data.error || 'FPP request failed.');
    if (typeof data === 'object' && data !== null && data.ok === false) throw Error(data.error || 'FPP request failed.');
    return data.data === undefined ? data : data.data;
  }
  function values() {
    const c = {};
    for (const key of Object.keys(saved)) {
      const el = form.elements.namedItem(key);
      c[key] = el.type === 'checkbox' ? el.checked : el.type === 'number' ? Number(el.value) : el.type === 'color' ? el.value.toUpperCase() : el.value;
    }
    return c;
  }
  function visibility() {
    const event = form.elements.mode.value === 'event';
    root.querySelector('#dc-event-fields').hidden = !event;
    root.querySelector('#dc-window-fields').hidden = event;
    form.elements.eventDate.required = event;
    form.elements.startTime.disabled = event || !form.elements.useWindow.checked;
    root.querySelector('#dc-start-help').textContent = event
      ? 'Start now counts down to the event date and time you selected. Choose a future date and time.'
      : 'For a daily time, Start now counts down to today’s show time, or tomorrow’s if it has already passed. The scheduled-start window does not restrict this button.';
  }
  function displayTime(value, timezone) {
    return new Intl.DateTimeFormat(undefined, {timeZone: timezone, year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', second: '2-digit'}).format(new Date(value));
  }
  async function preview() {
    const generation = ++previewGeneration;
    try {
      const config = values();
      const p = await request(base + 'preview', config);
      if (generation !== previewGeneration) return;
      const previewNode = root.querySelector('#dc-preview');
      previewNode.replaceChildren();
      const lines = p.text.split('\n');
      lines.forEach((line, index) => {
        if (index) previewNode.append(document.createTextNode('\n'));
        const span = document.createElement('span');
        span.textContent = line;
        span.style.color = lines.length > 1 && index === 0 ? config.headingColor : config.timerColor;
        previewNode.append(span);
      });
      root.querySelector('#dc-preview-note').textContent = 'Counting down to ' + displayTime(p.target, p.timezone) + ' (' + p.timezone + '). ' + (p.inWindow ? '' : 'Outside your scheduled-start window; you can still use Start now. ') + 'Preview only: your display is not changed. Check the actual display to confirm the text fits.';
    } catch (e) {
      if (generation === previewGeneration) root.querySelector('#dc-preview-note').textContent = e.message;
    }
  }
  async function refreshStatus() {
    try {
      const s = await request(base + 'status');
      const badge = root.querySelector('#dc-status');
      badge.className = 'badge text-wrap text-break ' + (s.running ? 'text-bg-success' : s.state.error ? 'text-bg-danger' : 'text-bg-secondary');
      badge.textContent = s.running ? 'Running · ends ' + displayTime(s.state.target, s.timezone) : s.state.error ? 'Stopped · ' + s.state.error : 'Stopped';
      root.querySelector('#dc-clock').textContent = 'FPP clock: ' + displayTime(s.deviceTime, s.timezone) + ' (' + s.timezone + '). All times on this page use this timezone.';
    } catch (e) { root.querySelector('#dc-status').textContent = 'Status unavailable: ' + e.message; }
  }
  async function command(name) {
    await request('/api/command', {command: name, args: []});
    notice(name.endsWith('Stop') ? 'Stop requested. Clearing the display may take a few seconds.' : 'Start requested. The status at the top of the page will confirm whether it started.', 'info');
    setTimeout(refreshStatus, 500);
  }
  form.addEventListener('submit', async event => {
    event.preventDefault();
    try {
      saved = await request(base + 'config', values());
      notice('Settings saved. They will be used on the next start.', 'success');
      if (window.$ && $.jGrowl) $.jGrowl('Countdown settings saved', {themeState: 'success'});
      preview();
    } catch (e) { notice(e.message, 'danger'); }
  });
  form.addEventListener('change', () => { visibility(); preview(); });
  root.querySelector('#dc-preview-button').addEventListener('click', preview);
  root.querySelector('#dc-run').addEventListener('click', async () => {
    try {
      const current = values();
      // Time inputs may omit :00 seconds; compare their normalized values.
      for (const key of ['startTime', 'targetTime']) if (current[key].length === 5) current[key] += ':00';
      if (JSON.stringify(current) !== JSON.stringify(saved)) throw Error('Save your changed settings before starting.');
      if (!saved.enabled) throw Error('Check Enable countdown and save your settings first.');
      await command('Daily Show Countdown Start Now');
    } catch (e) { notice(e.message, 'danger'); }
  });
  root.querySelector('#dc-stop').addEventListener('click', () => command('Daily Show Countdown Stop').catch(e => notice(e.message, 'danger')));
  function options(select, items, selected, preserveMissing = true) {
    select.replaceChildren(new Option('Select…', ''));
    for (const value of items) select.add(new Option(value, value));
    if (preserveMissing && selected && !items.includes(selected)) select.add(new Option(selected + ' (unavailable)', selected));
    select.value = items.includes(selected) || preserveMissing ? selected : '';
  }
  (async () => {
    try {
      const result = await request(base + 'config');
      saved = result.config;
      for (const [key, value] of Object.entries(saved)) {
        const el = form.elements.namedItem(key);
        if (el.type === 'checkbox') el.checked = value; else el.value = value;
      }
      const discovery = await Promise.allSettled([request('/api/models'), request('/api/overlays/fonts')]);
      const models = discovery[0].status === 'fulfilled' ? discovery[0].value : [];
      const fonts = discovery[1].status === 'fulfilled' ? discovery[1].value : [];
      options(form.elements.model, (Array.isArray(models) ? models : models.models || []).map(m => typeof m === 'string' ? m : m.Name), saved.model);
      options(form.elements.font, Array.isArray(fonts) ? fonts : [], saved.font, false);
      root.querySelector('#dc-fields').disabled = false;
      for (const id of ['dc-save', 'dc-run', 'dc-preview-button']) root.querySelector('#' + id).disabled = false;
      visibility(); preview(); refreshStatus();
      const fontsLoaded = discovery[1].status === 'fulfilled' && Array.isArray(fonts);
      if (!fontsLoaded) {
        form.elements.font.disabled = true;
        root.querySelector('#dc-save').disabled = true;
        root.querySelector('#dc-run').disabled = true;
        notice('Could not load the available fonts. Check that FPP is running, then reload this page to save or start.', 'danger');
      } else if (!fonts.includes(saved.font)) {
        notice('The previously saved font is not installed. Choose an available FPP font before saving.', 'warning');
      } else {
        const modelsLoaded = discovery[0].status === 'fulfilled';
        notice(modelsLoaded ? 'Choose your settings, save, then select Start now. Use the preview to check your text and colors.' : 'Could not load the display models. Check that FPP is running, then reload this page.', modelsLoaded ? 'info' : 'danger');
      }
      setInterval(() => { if (!document.hidden) refreshStatus(); }, 3000);
    } catch (e) { notice(e.message, 'danger'); }
  })();
})();
</script>
