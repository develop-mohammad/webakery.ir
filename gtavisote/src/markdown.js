'use strict';

function toHtml(src) {
  const text = String(src || '').replace(/\r\n?/g, '\n');
  const escaped = text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
  const blocks = escaped.split(/\n{2,}/);
  return blocks.map((block) => {
    const b = block.trim();
    if (!b) return '';
    if (/^### /.test(b)) return `<h3>${inline(b.slice(4))}</h3>`;
    if (/^## /.test(b)) return `<h2>${inline(b.slice(3))}</h2>`;
    if (/^# /.test(b)) return `<h2>${inline(b.slice(2))}</h2>`;
    if (/^> /.test(b)) return `<blockquote>${inline(b.replace(/^> /gm, ''))}</blockquote>`;
    if (/^---+$/.test(b)) return '<hr>';
    const lines = b.split('\n');
    if (/^[-*] /.test(lines[0])) {
      const lis = lines.map((line) => `<li>${inline(line.replace(/^[-*] /, ''))}</li>`).join('');
      return `<ul>${lis}</ul>`;
    }
    return `<p>${inline(b.replace(/\n/g, '<br>'))}</p>`;
  }).join('\n');
}

function toText(src) {
  return String(src || '')
    .replace(/!\[[^\]]*\]\([^)]+\)/g, '')
    .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
    .replace(/[#>*_`]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function inline(s) {
  return s
    .replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" loading="lazy">')
    .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/`([^`]+)`/g, '<code>$1</code>');
}

module.exports = { toHtml, toText };
