(() => {
  const root = document.querySelector('[data-launch]');
  if (!root) return;
  const end = new Date(root.getAttribute('data-launch')).getTime();
  const ids = ['d', 'h', 'm', 's'];
  const tick = () => {
    const diff = Math.max(0, end - Date.now());
    const parts = [
      Math.floor(diff / 86400000),
      Math.floor(diff / 3600000) % 24,
      Math.floor(diff / 60000) % 60,
      Math.floor(diff / 1000) % 60,
    ];
    const fa = '۰۱۲۳۴۵۶۷۸۹';
    parts.forEach((n, i) => {
      const el = document.getElementById(ids[i]);
      if (el) el.textContent = String(n).replace(/\d/g, (d) => fa[d]);
    });
  };
  tick();
  setInterval(tick, 1000);
})();
