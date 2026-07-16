document.addEventListener("DOMContentLoaded", async () => {
  const saved = await chrome.storage.sync.get({
    psiApiKey: "",
    strategy: "mobile",
    wpUrl: "",
  });

  document.getElementById("psiApiKey").value = saved.psiApiKey;
  document.getElementById("strategy").value = saved.strategy;
  document.getElementById("wpUrl").value = saved.wpUrl;

  document.getElementById("save").addEventListener("click", async () => {
    await chrome.storage.sync.set({
      psiApiKey: document.getElementById("psiApiKey").value.trim(),
      strategy: document.getElementById("strategy").value,
      wpUrl: document.getElementById("wpUrl").value.trim(),
    });

    const msg = document.getElementById("saved");
    msg.hidden = false;
    setTimeout(() => {
      msg.hidden = true;
    }, 2000);
  });
});
