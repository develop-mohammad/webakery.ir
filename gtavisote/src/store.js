'use strict';

const fs = require('fs');
const path = require('path');
const { ROOT } = require('./util');

const DIR = path.join(ROOT, 'data');
const mem = new Map();

function fileOf(type) {
  const safe = String(type).replace(/[^a-z0-9_-]/gi, '') || 'data';
  return path.join(DIR, `${safe}.json`);
}

function ensureDir() {
  fs.mkdirSync(DIR, { recursive: true });
}

function load(type) {
  if (mem.has(type)) return mem.get(type);
  ensureDir();
  const file = fileOf(type);
  let items = [];
  if (fs.existsSync(file)) {
    try {
      const json = JSON.parse(fs.readFileSync(file, 'utf8'));
      items = Array.isArray(json.items) ? json.items : [];
    } catch {
      items = [];
    }
  }
  const data = { items };
  mem.set(type, data);
  return data;
}

function write(type, data) {
  ensureDir();
  mem.set(type, data);
  const file = fileOf(type);
  const tmp = `${file}.tmp`;
  fs.writeFileSync(tmp, JSON.stringify(data, null, 2), 'utf8');
  fs.renameSync(tmp, file);
}

const store = {
  all(type) {
    return load(type).items.slice();
  },
  find(type, id) {
    return store.all(type).find((row) => String(row.id) === String(id)) || null;
  },
  findBy(type, field, value) {
    return store.all(type).find((row) => String(row[field]) === String(value)) || null;
  },
  where(type, fn) {
    return store.all(type).filter(fn);
  },
  save(type, item) {
    const now = new Date().toISOString();
    if (!item.id) item.id = `${type}_${Date.now().toString(36)}`;
    if (!item.created_at) item.created_at = now;
    item.updated_at = now;
    const data = load(type);
    const i = data.items.findIndex((row) => String(row.id) === String(item.id));
    if (i >= 0) data.items[i] = { ...data.items[i], ...item };
    else data.items.push(item);
    write(type, data);
    return i >= 0 ? data.items[i] : item;
  },
  delete(type, id) {
    const data = load(type);
    const before = data.items.length;
    data.items = data.items.filter((row) => String(row.id) !== String(id));
    write(type, data);
    return data.items.length < before;
  },
  replaceAll(type, items) {
    write(type, { items: items.slice() });
  },
  resetMem() {
    mem.clear();
  },
};

module.exports = store;
