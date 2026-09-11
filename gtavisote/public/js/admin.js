(() => {
  const title = document.querySelector('input[name="title"]');
  const slug = document.querySelector('input[name="slug"]');
  if (!title || !slug || slug.value) return;
  title.addEventListener('blur', () => {
    if (slug.value) return;
    slug.value = title.value.trim().replace(/\s+/g, '-');
  });
})();
