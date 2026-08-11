(() => {
  const cfg = window.VMS_SCAN || {};
  const statusEl = document.getElementById('scan-status');
  const resultEl = document.getElementById('scan-result');
  const startBtn = document.getElementById('start-scan');
  const stopBtn = document.getElementById('stop-scan');
  const fileInput = document.getElementById('qr-file');
  const apiUrl = cfg.apiUrl;
  const csrf = cfg.csrf;

  if (!statusEl || !resultEl || !startBtn) return;

  let scanner = null;
  let locked = false;

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function extractToken(raw) {
    const text = String(raw || '').trim();
    if (!text) return '';
    try {
      const parsed = JSON.parse(text);
      if (parsed && typeof parsed === 'object' && parsed.token) return String(parsed.token).trim();
      if (typeof parsed === 'string') return extractToken(parsed);
    } catch (_) {}
    const tokenParam = text.match(/[?&]token=([a-zA-Z0-9_]+)/);
    if (tokenParam) return tokenParam[1];
    const signed = text.match(/^(tok_[a-f0-9]+)\.\d+\.[a-f0-9]{16}$/i);
    if (signed) return text; // send full signed payload to API
    const tok = text.match(/\b(tok_[a-f0-9]+)\b/i);
    if (tok) return tok[1];
    return text;
  }

  async function processPayload(raw) {
    if (locked) return;
    locked = true;
    statusEl.textContent = 'Processing…';
    const token = extractToken(raw);
    if (!token) {
      resultEl.innerHTML = `<p style="color:var(--danger)">Empty QR content.</p>`;
      locked = false;
      return;
    }
    try {
      const res = await fetch(apiUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ token, _csrf: csrf })
      });
      let data;
      try { data = await res.json(); } catch (_) { throw new Error('Bad API response (' + res.status + ')'); }
      if (!data.ok) {
        resultEl.innerHTML = `<p style="color:var(--danger)">${escapeHtml(data.message || 'Scan failed')}</p>`;
      } else {
        const v = data.visitor;
        const pill = v.status === 'Checked In' ? 'pill-success' : 'pill-muted';
        const photo = v.photo_path
          ? `<img class="avatar-md" src="${escapeHtml(v.photo_path)}" alt="" style="margin-bottom:0.6rem;">`
          : '';
        resultEl.innerHTML = `
          ${photo}
          <p><strong>${escapeHtml(v.full_name)}</strong></p>
          <p class="muted">${escapeHtml(v.host_name)} · ${escapeHtml(v.visit_date)}</p>
          <p><span class="pill ${pill}">${escapeHtml(v.status)}</span> — ${escapeHtml(data.message)}</p>
          <p style="margin-top:0.75rem"><a class="btn btn-sm" href="visitor_view.php?id=${encodeURIComponent(v.id)}">View</a></p>
        `;
        statusEl.textContent = data.message;
      }
    } catch (err) {
      resultEl.innerHTML = `<p style="color:var(--danger)">${escapeHtml(err.message || 'Network error')}</p>`;
      statusEl.textContent = 'Scan failed.';
    } finally {
      setTimeout(() => { locked = false; }, 1200);
    }
  }

  async function stopScanner() {
    if (!scanner) return;
    try { await scanner.stop(); } catch (_) {}
    try { scanner.clear(); } catch (_) {}
    scanner = null;
    startBtn.hidden = false;
    if (stopBtn) stopBtn.hidden = true;
    statusEl.textContent = 'Camera idle.';
  }

  async function startScanner() {
    if (!window.Html5Qrcode) {
      statusEl.textContent = 'Scanner library failed to load.';
      return;
    }
    await stopScanner();
    scanner = new Html5Qrcode('reader');
    const config = {
      fps: 10,
      qrbox: (w, h) => {
        const edge = Math.floor(Math.min(w, h) * 0.75);
        return { width: edge, height: edge };
      },
      aspectRatio: 1.0
    };
    const onSuccess = (decoded) => processPayload(decoded);
    const attempts = [{ facingMode: 'environment' }, { facingMode: 'user' }];
    let lastError = null;
    for (const cameraConfig of attempts) {
      try {
        await scanner.start(cameraConfig, config, onSuccess, () => {});
        statusEl.textContent = 'Camera active — point at badge QR.';
        startBtn.hidden = true;
        if (stopBtn) stopBtn.hidden = false;
        return;
      } catch (err) { lastError = err; }
    }
    try {
      const cameras = await Html5Qrcode.getCameras();
      if (!cameras || !cameras.length) throw lastError || new Error('No camera found');
      await scanner.start(cameras[0].id, config, onSuccess, () => {});
      statusEl.textContent = 'Camera active — point at badge QR.';
      startBtn.hidden = true;
      if (stopBtn) stopBtn.hidden = false;
    } catch (err) {
      statusEl.textContent = 'Camera error: ' + (err && err.message ? err.message : 'unavailable');
      resultEl.innerHTML = `<p class="muted">Use <strong>Upload QR</strong> or enter a token manually.</p>`;
      try { scanner.clear(); } catch (_) {}
      scanner = null;
    }
  }

  startBtn.addEventListener('click', () => startScanner());
  if (stopBtn) stopBtn.addEventListener('click', () => stopScanner());

  if (fileInput) {
    fileInput.addEventListener('change', async () => {
      const file = fileInput.files && fileInput.files[0];
      if (!file) return;
      statusEl.textContent = 'Reading image…';
      try {
        const temp = new Html5Qrcode('reader');
        const decoded = await temp.scanFile(file, true);
        try { temp.clear(); } catch (_) {}
        await processPayload(decoded);
      } catch (err) {
        statusEl.textContent = 'Could not read QR from image.';
        resultEl.innerHTML = `<p style="color:var(--danger)">${escapeHtml(err.message || 'Invalid image')}</p>`;
      } finally {
        fileInput.value = '';
      }
    });
  }

  const manualBtn = document.getElementById('manual-submit');
  const manualInput = document.getElementById('manual-token');
  if (manualBtn && manualInput) {
    manualBtn.addEventListener('click', () => {
      if (manualInput.value.trim()) processPayload(manualInput.value.trim());
    });
    manualInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        manualBtn.click();
      }
    });
  }

  document.querySelectorAll('.quick-token').forEach((btn) => {
    btn.addEventListener('click', () => processPayload(btn.dataset.token || ''));
  });

  if (cfg.autoStart) {
    setTimeout(() => startScanner(), 400);
  }
})();
