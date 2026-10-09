/*
 * Visual editor for a study type definition (admin → Study types). It edits the same JSON the server
 * stores: general texts, sections and their fields, and the rules. Keys it does not know (computed
 * values, tables, extract…) are kept as they are. The JSON tab shows / edits the whole definition.
 */
(() => {
  const root = document.getElementById('te');
  if (!root) return;
  const jsonBox = document.getElementById('json');
  const form = document.getElementById('teForm');
  const OPS = { gte: '≥ at least', lte: '≤ at most', gt: '> more than', lt: '< less than', eq: '= equals', neq: '≠ not equal', in: 'is one of', notIn: 'is not one of', between: 'between' };
  const TYPES = { text: 'Text', textarea: 'Long text', number: 'Number', select: 'List of choices', bool: 'Yes / No' };
  const CALCS = JSON.parse(root.dataset.calcs || '[]');
  let m;
  try { m = JSON.parse(jsonBox.value); } catch (e) { m = { sections: [], rules: [] }; }

  // ---- tiny DOM helpers
  const h = (tag, attrs = {}, ...kids) => {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (k === 'class') el.className = v; else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
      else if (k === 'checked' || k === 'value' || k === 'disabled') el[k] = v; else if (v !== null && v !== undefined && v !== false) el.setAttribute(k, v);
    }
    kids.flat().forEach(c => c !== null && c !== undefined && el.append(c.nodeType ? c : document.createTextNode(String(c))));
    return el;
  };
  const tr = (o, k) => (o && typeof o === 'object' ? o : { en: o || '', ar: '' });
  const input = (get, set, attrs = {}) => h('input', { class: 'inp', value: get() ?? '', oninput: e => set(e.target.value), ...attrs });
  const num = (get, set, attrs = {}) => input(() => get() ?? '', v => set(v === '' ? undefined : (isNaN(+v) ? v : +v)), { inputmode: 'decimal', style: 'max-width:110px', ...attrs });
  const bi = (obj, key, ph = '') => { obj[key] = tr(obj[key]); return h('div', { class: 'te-bi' },
    input(() => obj[key].en, v => obj[key].en = v, { placeholder: (ph ? ph + ' ' : '') + '(English)' }),
    input(() => obj[key].ar, v => obj[key].ar = v, { placeholder: (ph ? ph + ' ' : '') + '(عربي)', dir: 'rtl' })); };
  const select = (opts, get, set, attrs = {}) => h('select', { class: 'inp', onchange: e => set(e.target.value), ...attrs },
    Object.entries(opts).map(([v, l]) => h('option', { value: v, selected: String(get() ?? '') === v ? 'selected' : null }, l)));
  const btn = (label, fn, cls = 'btn') => h('button', { type: 'button', class: cls, onclick: fn }, label);
  const move = (list, i, d) => { const j = i + d; if (j < 0 || j >= list.length) return; [list[i], list[j]] = [list[j], list[i]]; render(); };
  const field = (label, ...kids) => h('label', { class: 'f' }, h('span', {}, label), ...kids);
  const keyOk = k => /^[a-z][a-z0-9_]*$/.test(k || '');

  const fieldsOf = key => {
    const sec = (m.sections || []).find(s => s.key === key);
    const list = sec ? sec.fields.map(f => [f.key, `${f.key} — ${tr(f.label).en}`]) : [];
    ((m.computed || {})[key] || []).forEach(c => list.push([c.key, `${c.key} — ${tr(c.label).en} (calculated)`]));
    return Object.fromEntries(list);
  };
  const allRefs = () => {
    const o = {};
    (m.sections || []).forEach(s => Object.entries(fieldsOf(s.key)).forEach(([k, l]) => { o[`${s.key}.${k}`] = `${s.key}.${l}`; }));
    return o;
  };

  // ---- general
  const general = () => {
    m.file = m.file || { required: true };
    return h('div', { class: 'card pad te-card' },
      h('h2', {}, 'General'),
      field('Name', bi(m, 'name')),
      field('Short description (on the type card)', bi(m, 'summary')),
      field('Specification reference', bi(m, 'spec')),
      h('div', { class: 'row2' },
        field('Discipline', select({ Electrical: 'Electrical', Mechanical: 'Mechanical', Plumbing: 'Plumbing', Fire: 'Fire', Other: 'Other' }, () => m.discipline, v => m.discipline = v)),
        field('Icon', select({ bolt: 'Bolt', fan: 'Fan', drop: 'Drop', chart: 'Chart', clipboard: 'Clipboard', helmet: 'Helmet', building: 'Building', shield: 'Shield' }, () => m.icon, v => m.icon = v))),
      h('div', { class: 'row2' },
        field('Calculation', select(Object.fromEntries([['', 'None'], ...CALCS.map(c => [c, c])]), () => m.calc || '', v => { if (v) m.calc = v; else delete m.calc; })),
        h('label', { class: 'check', style: 'align-self:end;padding-bottom:10px' }, h('input', { type: 'checkbox', checked: m.file.required !== false, onchange: e => m.file.required = e.target.checked }), ' Supporting file required')),
      field('Supporting file label', bi(m.file, 'label')),
      field('Supporting file hint', bi(m.file, 'hint')));
  };

  // ---- one field row
  const fieldEditor = (sec, f, i) => {
    f.label = tr(f.label);
    const opts = () => (f.options || []).map(o => [o.value, tr(o.label).en, tr(o.label).ar].join(' | ')).join('\n');
    const parseOpts = txt => txt.split('\n').map(l => l.trim()).filter(Boolean).map(l => {
      const [v, en, ar] = l.split('|').map(x => (x || '').trim());
      return { value: v, label: { en: en || v, ar: ar || en || v } };
    });
    const extra = h('div', { class: 'te-extra' },
      f.type === 'number' ? h('div', { class: 'te-inline' }, field('Min', num(() => f.min, v => f.min = v)), field('Max', num(() => f.max, v => f.max = v)), field('Step', num(() => f.step, v => f.step = v)), field('Default', num(() => f.default, v => f.default = v))) : null,
      f.type === 'select' ? field('Choices — one per line: value | English | عربي', h('textarea', { class: 'inp mono', style: 'min-height:90px', dir: 'ltr', oninput: e => f.options = parseOpts(e.target.value) }, opts())) : null,
      field('Help under the field', bi(f, 'help')));
    return h('div', { class: 'te-field' + (keyOk(f.key) ? '' : ' bad') },
      h('div', { class: 'te-row' },
        input(() => f.key, v => { f.key = v.trim(); }, { class: 'inp mono', placeholder: 'key', style: 'max-width:150px', title: 'Stored name: lower-case letters, digits, _' }),
        select(TYPES, () => f.type, v => { f.type = v; if (v === 'select' && !f.options) f.options = [{ value: 'a', label: { en: 'A', ar: 'A' } }]; render(); }, { style: 'max-width:150px' }),
        input(() => f.label.en, v => f.label.en = v, { placeholder: 'Label (English)' }),
        input(() => f.label.ar, v => f.label.ar = v, { placeholder: 'العنوان (عربي)', dir: 'rtl' }),
        input(() => f.unit, v => { if (v) f.unit = v; else delete f.unit; }, { placeholder: 'unit', style: 'max-width:80px' }),
        h('label', { class: 'check', title: 'Required' }, h('input', { type: 'checkbox', checked: !!f.required, onchange: e => f.required = e.target.checked }), ' req.'),
        btn('↑', () => move(sec.fields, i, -1), 'btn te-mini'), btn('↓', () => move(sec.fields, i, 1), 'btn te-mini'),
        btn('✕', () => { if (confirm(`Delete the field "${f.key}"? Rules using it must be removed too.`)) { sec.fields.splice(i, 1); render(); } }, 'btn danger te-mini')),
      h('details', {}, h('summary', {}, 'More: limits, choices, help'), extra));
  };

  const sections = () => h('div', { class: 'card pad te-card' },
    h('h2', {}, 'Sections and fields'),
    h('p', { class: 'mute', style: 'margin:-6px 0 6px;font-size:13px' }, 'A normal section is one set of values (e.g. design data). A table section repeats its fields for each row (panels, circuits, units). Changing a key breaks studies already saved with the old one.'),
    ...(m.sections || []).map((sec, si) => {
      sec.title = tr(sec.title);
      sec.fields = sec.fields || [];
      const isTable = !!sec.repeat;
      return h('div', { class: 'te-sec' + (keyOk(sec.key) ? '' : ' bad') },
        h('div', { class: 'te-row' },
          input(() => sec.key, v => sec.key = v.trim(), { class: 'inp mono', placeholder: 'section key', style: 'max-width:150px' }),
          input(() => sec.title.en, v => sec.title.en = v, { placeholder: 'Title (English)' }),
          input(() => sec.title.ar, v => sec.title.ar = v, { placeholder: 'العنوان (عربي)', dir: 'rtl' }),
          h('label', { class: 'check' }, h('input', { type: 'checkbox', checked: isTable, onchange: e => { if (e.target.checked) sec.repeat = { min: 1, max: 50, title: sec.fields[0]?.key || '', add: { en: 'Add row', ar: 'إضافة صف' } }; else delete sec.repeat; render(); } }), ' table'),
          btn('↑', () => move(m.sections, si, -1), 'btn te-mini'), btn('↓', () => move(m.sections, si, 1), 'btn te-mini'),
          btn('✕', () => { if (confirm(`Delete the section "${sec.key}" and its fields?`)) { m.sections.splice(si, 1); render(); } }, 'btn danger te-mini')),
        isTable ? h('div', { class: 'te-inline', style: 'margin:8px 0' },
          field('Min rows', num(() => sec.repeat.min, v => sec.repeat.min = v)), field('Max rows', num(() => sec.repeat.max, v => sec.repeat.max = v)),
          field('Row name field', select(Object.fromEntries(sec.fields.map(f => [f.key, f.key])), () => sec.repeat.title, v => sec.repeat.title = v)),
          field('"Add" button', bi(sec.repeat, 'add'))) : null,
        h('div', { class: 'te-fields' }, sec.fields.map((f, i) => fieldEditor(sec, f, i))),
        btn('+ Field', () => { sec.fields.push({ key: 'field_' + (sec.fields.length + 1), type: 'number', required: true, label: { en: 'New field', ar: 'حقل جديد' } }); render(); }, 'btn line'));
    }),
    btn('+ Section', () => { m.sections.push({ key: 'section_' + (m.sections.length + 1), title: { en: 'New section', ar: 'قسم جديد' }, fields: [] }); render(); }, 'btn line'));

  // ---- one rule
  const valueEditor = r => {
    const mode = r.value && typeof r.value === 'object' && !Array.isArray(r.value) ? 'ref' : (Array.isArray(r.value) ? 'list' : 'fixed');
    const modeSel = select({ fixed: 'a fixed value', list: 'a list / range', ref: 'another field' }, () => mode, v => {
      r.value = v === 'ref' ? { ref: Object.keys(allRefs())[0] || '' } : v === 'list' ? [] : 0; render();
    }, { style: 'max-width:150px' });
    let box;
    if (mode === 'ref') {
      const refs = { ...Object.fromEntries(Object.entries(fieldsOf(r.section)).map(([k, l]) => [k, 'same row: ' + l])), ...allRefs() };
      box = select(refs, () => r.value.ref, v => r.value.ref = v);
    } else if (mode === 'list') {
      box = input(() => (r.value || []).join(', '), v => r.value = v.split(',').map(x => x.trim()).filter(x => x !== '').map(x => isNaN(+x) ? x : +x), { placeholder: r.op === 'between' ? 'min, max' : 'a, b, c' });
    } else {
      box = input(() => typeof r.value === 'boolean' ? (r.value ? 'true' : 'false') : r.value, v => r.value = v === 'true' ? true : v === 'false' ? false : (v !== '' && !isNaN(+v) ? +v : v), { placeholder: 'value (number, text, true / false)' });
    }
    return h('div', { class: 'te-inline' }, modeSel, box);
  };
  const ruleEditor = (r, i) => {
    r.label = tr(r.label); r.comment = tr(r.comment);
    const whenKey = Object.keys(r.when || {})[0] || '';
    const fieldOk = Object.keys(fieldsOf(r.section)).includes(r.field);
    return h('div', { class: 'te-rule' + (fieldOk ? '' : ' bad') },
      h('div', { class: 'te-row' },
        input(() => r.id, v => r.id = v.trim(), { class: 'inp mono', style: 'max-width:80px', placeholder: 'id' }),
        input(() => r.label.en, v => r.label.en = v, { placeholder: 'Check name (English)' }),
        input(() => r.label.ar, v => r.label.ar = v, { placeholder: 'اسم الفحص (عربي)', dir: 'rtl' }),
        select({ fail: 'Non-compliant', warn: 'Clarify' }, () => r.severity || 'fail', v => r.severity = v, { style: 'max-width:150px' }),
        btn('⧉', () => { m.rules.splice(i + 1, 0, JSON.parse(JSON.stringify({ ...r, id: r.id + 'b' }))); render(); }, 'btn te-mini'),
        btn('✕', () => { if (confirm(`Delete the rule ${r.id}?`)) { m.rules.splice(i, 1); render(); } }, 'btn danger te-mini')),
      h('div', { class: 'te-inline' },
        field('Section', select(Object.fromEntries((m.sections || []).map(s => [s.key, s.key])), () => r.section, v => { r.section = v; r.field = Object.keys(fieldsOf(v))[0] || ''; render(); })),
        field('Field', select(fieldsOf(r.section), () => r.field, v => r.field = v)),
        field('must be', select(OPS, () => r.op, v => { r.op = v; if ((v === 'in' || v === 'notIn' || v === 'between') && !Array.isArray(r.value)) r.value = []; render(); })),
        field('compared with', valueEditor(r))),
      h('div', { class: 'te-inline' },
        field('Only for rows where', select({ '': '— every row —', ...fieldsOf(r.section) }, () => whenKey, v => { if (v) r.when = { [v]: (r.when || {})[whenKey] || [] }; else delete r.when; render(); })),
        whenKey ? field('is one of (comma separated)', input(() => (r.when[whenKey] || []).join(', '), v => r.when[whenKey] = v.split(',').map(x => x.trim()).filter(Boolean))) : null,
        field('Specification clause', input(() => r.clause, v => r.clause = v, { placeholder: '262300 §2.6.B' }))),
      field('Comment to the client — {actual} {expected} {rows} {unit} {clause} are replaced', bi(r, 'comment')));
  };
  const rules = () => {
    m.rules = m.rules || [];
    return h('div', { class: 'card pad te-card' },
      h('h2', {}, 'Rules'),
      h('p', { class: 'mute', style: 'margin:-6px 0 6px;font-size:13px' }, 'Each rule checks one field (in every row of a table) against a fixed value, a list, or another field. A rule outlined in red points at a field that does not exist.'),
      ...m.rules.map(ruleEditor),
      btn('+ Rule', () => { const s = (m.sections || []).find(x => x.repeat) || (m.sections || [])[0] || { key: '' }; m.rules.push({ id: 'R' + (m.rules.length + 1), section: s.key, field: Object.keys(fieldsOf(s.key))[0] || '', op: 'gte', value: 0, severity: 'fail', label: { en: '', ar: '' }, clause: '', comment: { en: '{actual} does not meet {expected}. Rows: {rows}.', ar: '{actual} لا يحقق {expected}. البنود: {rows}.' } }); render(); }, 'btn line'));
  };

  const render = () => { const y = window.scrollY; root.replaceChildren(general(), sections(), rules()); window.scrollTo(0, y); };

  // ---- tabs: the JSON tab is the same definition as text
  const tabs = document.querySelectorAll('[data-tab]');
  const show = name => {
    if (name === 'json') jsonBox.value = JSON.stringify(m, null, 2);
    else { try { m = JSON.parse(jsonBox.value); render(); } catch (e) { alert('The JSON is not valid: ' + e.message); return; } }
    tabs.forEach(t => t.classList.toggle('on', t.dataset.tab === name));
    root.hidden = name !== 'visual'; document.getElementById('jsonPane').hidden = name !== 'json';
  };
  tabs.forEach(t => t.addEventListener('click', () => show(t.dataset.tab)));
  form.addEventListener('submit', () => { if (!root.hidden) jsonBox.value = JSON.stringify(m, null, 2); });
  render();
})();
