'use strict';

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const VERSION = fs.readFileSync(path.join(ROOT, 'VERSION'), 'utf8').trim();

function e(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function attr(value) {
  return e(value);
}

function faDigits(s) {
  return String(s).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
}

function money(toman) {
  const n = Number(toman) || 0;
  return `${faDigits(n.toLocaleString('en-US'))} تومان`;
}

function slug(text) {
  const map = { آ: 'ا', أ: 'ا', إ: 'ا', ة: 'ه', ي: 'ی', ك: 'ک' };
  let t = String(text || '').trim();
  t = t.replace(/[آأإةيك]/g, (ch) => map[ch] || ch);
  t = t.replace(/[^\p{L}\p{N}]+/gu, '-').replace(/^-|-$/g, '').toLowerCase();
  return t || `item-${crypto.randomBytes(3).toString('hex')}`;
}

function uid(prefix = 'id') {
  return `${prefix}_${crypto.randomBytes(6).toString('hex')}`;
}

function phone(raw) {
  const map = {
    '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
    '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
    '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
    '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
  };
  let s = String(raw || '').replace(/[۰-۹٠-٩]/g, (ch) => map[ch] || ch).replace(/\D+/g, '');
  if (s.startsWith('0098')) s = s.slice(4);
  if (s.startsWith('98')) s = s.slice(2);
  if (s.startsWith('9') && s.length === 10) s = `0${s}`;
  return s;
}

function excerpt(text, len = 160) {
  const plain = String(text || '').replace(/[#>*_`\[\]()!]/g, ' ').replace(/\s+/g, ' ').trim();
  if (plain.length <= len) return plain;
  return `${plain.slice(0, len).trim()}…`;
}

function parseCookies(header) {
  const out = {};
  String(header || '').split(';').forEach((part) => {
    const i = part.indexOf('=');
    if (i === -1) return;
    out[part.slice(0, i).trim()] = decodeURIComponent(part.slice(i + 1).trim());
  });
  return out;
}

function hashPassword(password) {
  const salt = crypto.randomBytes(16).toString('hex');
  const hash = crypto.scryptSync(password, salt, 32).toString('hex');
  return `scrypt$${salt}$${hash}`;
}

function verifyPassword(password, stored) {
  const parts = String(stored || '').split('$');
  if (parts.length !== 3 || parts[0] !== 'scrypt') return false;
  const check = crypto.scryptSync(password, parts[1], 32).toString('hex');
  try {
    return crypto.timingSafeEqual(Buffer.from(parts[2], 'hex'), Buffer.from(check, 'hex'));
  } catch {
    return false;
  }
}

function sectionOf(type) {
  if (type === 'rumor') return 'rumors';
  if (type === 'guide') return 'guide';
  return 'news';
}

function sectionLabel(type) {
  return { news: 'اخبار', rumor: 'شایعه', guide: 'راهنما', page: 'برگه' }[type] || type;
}

function kindLabel(kind) {
  return {
    physical: 'نسخه فیزیکی',
    digital: 'نسخه دیجیتال',
    bundle: 'باندل کنسول',
    edition: 'نسخه ویژه',
    merch: 'کالکشن و مرچ',
    accessory: 'لوازم جانبی',
    addon: 'پک جانبی',
  }[kind] || kind;
}

function navActive(path, prefix) {
  if (prefix === '/') return path === '/' ? 'is-active' : '';
  return path === prefix || path.startsWith(`${prefix}/`) ? 'is-active' : '';
}

function asset(file) {
  const full = path.join(ROOT, 'public', file.replace(/^\//, ''));
  let v = VERSION;
  try { v = String(fs.statSync(full).mtimeMs); } catch { /* keep */ }
  return `/${file.replace(/^\//, '')}?v=${encodeURIComponent(v)}`;
}

module.exports = {
  ROOT,
  VERSION,
  e,
  attr,
  faDigits,
  money,
  slug,
  uid,
  phone,
  excerpt,
  parseCookies,
  hashPassword,
  verifyPassword,
  sectionOf,
  sectionLabel,
  kindLabel,
  navActive,
  asset,
};
