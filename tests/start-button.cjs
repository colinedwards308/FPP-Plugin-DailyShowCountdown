// Exercise the actual UI save handler without a browser or physical display.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../plugin_setup.php'), 'utf8');
const state = source.slice(source.indexOf('  let saved = null;'), source.indexOf('  let previewGeneration'));
const handler = source.slice(source.indexOf("  form.addEventListener('submit'"), source.indexOf("  root.querySelector('#dc-preview-button').addEventListener"));
const listeners = {};
const classes = new Set(['btn-outline-primary']);
const context = vm.createContext({
  root: {querySelector: () => ({classList: {toggle: (name, on) => on ? classes.add(name) : classes.delete(name)}})},
  form: {elements: {enabled: {checked: true}}, addEventListener: (name, fn) => { listeners[name] = fn; }},
  base: '/test/', window: {}, values: () => ({}), notice() {}, preview() {}, visibility() {},
  request: async () => ({enabled: true})
});
vm.runInContext(state + handler, context);
const submit = () => listeners.submit({preventDefault() {}});
const green = () => classes.has('btn-success') && classes.has('text-white') && !classes.has('btn-outline-primary');
(async () => {
  assert(!green(), 'not green before save');
  await submit();
  assert(green(), 'successful enabled save is green with white text');
  listeners.input();
  assert(!green(), 'editing removes green');
  await submit();
  context.form.elements.enabled.checked = false;
  listeners.change();
  assert(!green(), 'unchecking Enable removes green');
  context.request = async () => ({enabled: false});
  await submit();
  assert(!green(), 'disabled save stays neutral');
  context.form.elements.enabled.checked = true;
  context.request = async () => ({enabled: true});
  await submit();
  context.request = async () => { throw Error('Validation failed'); };
  await submit();
  assert(!green(), 'failed save removes green');
  let resolve;
  context.request = () => new Promise(r => { resolve = r; });
  const pending = submit();
  listeners.input();
  resolve({enabled: true});
  await pending;
  assert(!green(), 'edits during save cannot turn button green');
  console.log('PASS: Start Now button save, disabled, failure and edit states');
})().catch(error => { console.error(error); process.exitCode = 1; });
