/*
 * IELTS Listening 시험 음원 — 재생기를 보여주지 않고 자동 재생. 응시자는 멈추거나 앞뒤로 옮길 수 없음 (음량만 조절)
 *   - 음원: 서버 파일(serverUrl)이 있으면 그것, 없으면 PC 음원 폴더의 "SET N AUDIO.mp3" (js/local-audio.js)
 *   - 브라우저가 소리 자동 재생을 막으면 화면을 덮는 "Start Listening Test" 버튼 한 번으로 시작
 *   - 새로고침해도 처음부터 다시 나오지 않고, 시작한 시각 기준으로 이어서 재생 (localStorage — 브라우저를 껐다 켜도 이어짐)
 * 사용: ExamAudio.start({ box, setNo, serverUrl, key, onEnded })
 */
(function () {
  const store = {
    get(k) { try { return +localStorage.getItem(k) || 0; } catch (e) { return 0; } },
    set(k, v) { try { localStorage.setItem(k, String(v)); } catch (e) {} },
    del(k) { try { localStorage.removeItem(k); } catch (e) {} },
  };

  function esc(s) { return String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

  function start(o) {
    const audio = new Audio();
    audio.preload = 'auto';
    let started = false, done = false;

    const style = document.createElement('style');
    style.textContent = '.ea-overlay{position:fixed;inset:0;z-index:100;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.55)}'
      + '.ea-card{background:#fff;max-width:520px;width:calc(100% - 32px);padding:28px;border-radius:8px;text-align:center;font:15px/1.6 Arial,Helvetica,sans-serif;color:#222}'
      + '.ea-card h2{font-size:20px;font-weight:700;margin:0 0 10px}'
      + '.ea-card p{margin:0 0 18px;color:#555}'
      + '.ea-btn{background:#1a5bb8;color:#fff;border:0;border-radius:4px;padding:12px 26px;font-size:16px;font-weight:700;cursor:pointer}'
      + '.ea-btn:hover{background:#154a96}.ea-btn2{background:#fff;color:#1a5bb8;border:1px solid #1a5bb8;padding:6px 14px;font-size:13px;margin-top:14px}'
      + '.ea-status{display:flex;align-items:center;gap:12px;font-size:14px;color:#333}'
      + '.ea-status input[type=range]{width:120px;accent-color:#1a5bb8}';
    document.head.appendChild(style);

    const overlay = document.createElement('div');
    overlay.className = 'ea-overlay';
    document.body.appendChild(overlay);

    function showOverlay(html) { overlay.innerHTML = '<div class="ea-card">' + html + '</div>'; overlay.style.display = 'flex'; }
    function hideOverlay() { overlay.style.display = 'none'; }

    function showPlaying() {
      o.box.innerHTML = '<div class="ea-status"><span>🔊 Audio playing</span>'
        + '<label style="display:flex;align-items:center;gap:6px">Volume <input type="range" min="0" max="1" step="0.05" value="' + audio.volume + '"></label></div>';
      o.box.querySelector('input').addEventListener('input', e => { audio.volume = +e.target.value; });
    }

    function finish() {
      if (done) return;
      done = true;
      o.box.innerHTML = '<div class="ea-status">🔇 Audio finished</div>';
      hideOverlay();
      o.onEnded && o.onEnded();
    }

    audio.addEventListener('ended', finish);
    // 멈춤 금지: 끝나기 전에 멈추면 바로 다시 재생
    audio.addEventListener('pause', () => { if (started && !done && !audio.ended) audio.play().catch(() => {}); });
    audio.addEventListener('error', () => {
      if (!done) showOverlay('<h2>Audio error</h2><p>The audio file could not be played. Please call the supervisor.</p>');
    });
    // 키보드 미디어 키(일시정지·앞뒤 이동) 막기
    if ('mediaSession' in navigator) {
      ['pause', 'stop', 'seekbackward', 'seekforward', 'seekto', 'previoustrack', 'nexttrack'].forEach(a => {
        try { navigator.mediaSession.setActionHandler(a, () => {}); } catch (e) {}
      });
    }

    const waitMeta = () => audio.readyState >= 1 ? Promise.resolve()
      : new Promise(res => audio.addEventListener('loadedmetadata', res, { once: true }));

    // 재생 시작 (이미 시작했던 시험이면 지난 시간만큼 건너뜀). 브라우저가 막으면 false
    async function begin() {
      const t0 = store.get(o.key);
      if (t0) {
        await waitMeta();
        const off = (Date.now() - t0) / 1000;
        if (isFinite(audio.duration) && off >= audio.duration - 0.5) { finish(); return true; }
        audio.currentTime = off;
      }
      try { await audio.play(); } catch (e) { return false; }
      started = true;
      if (!t0) store.set(o.key, Date.now() - audio.currentTime * 1000);
      hideOverlay();
      showPlaying();
      return true;
    }

    function askStart() {
      showOverlay('<h2>Listening Test</h2><p>The audio will start when you click the button.<br>It cannot be paused or replayed.</p>'
        + '<button type="button" class="ea-btn" data-ea="go">Start Listening Test</button>');
    }

    async function useFile(file) {
      audio.src = URL.createObjectURL(file);
      if (!(await begin())) askStart();
    }

    // PC 음원 폴더에서 파일 찾기. 사람이 눌러야 하는 단계면 안내 화면을 띄우고 false
    async function loadLocal(ask) {
      const L = window.LocalAudio;
      if (!L || !L.supported) {
        showOverlay('<h2>Select the audio</h2><p>Select <b>SET ' + o.setNo + ' AUDIO.mp3</b> on this computer. Please call the supervisor.</p>'
          + '<input type="file" accept="audio/*" data-ea="file">');
        return false;
      }
      let dir = await L.getDir();
      if (!dir) {
        showOverlay('<h2>Audio not set up</h2><p>The audio folder is not set on this computer. Please call the supervisor.</p>'
          + '<button type="button" class="ea-btn" data-ea="pick">Select audio folder</button>');
        return false;
      }
      let perm = await dir.queryPermission({ mode: 'read' });
      if (perm !== 'granted' && ask) perm = await dir.requestPermission({ mode: 'read' });
      if (perm !== 'granted') { askStart(); return false; }
      const file = await L.findFile(dir, o.setNo);
      if (!file) {
        showOverlay('<h2>Audio not found</h2><p><b>SET ' + o.setNo + ' AUDIO.mp3</b> was not found in folder <b>' + esc(dir.name)
          + '</b>. Please call the supervisor.</p><button type="button" class="ea-btn" data-ea="pick">Select audio folder</button>');
        return false;
      }
      await useFile(file);
      return true;
    }

    overlay.addEventListener('click', async e => {
      const act = e.target.dataset && e.target.dataset.ea;
      if (act === 'go') {
        if (audio.src) { if (!(await begin())) askStart(); }
        else await loadLocal(true);          // 폴더 권한 허용 + 재생을 같은 클릭으로
      } else if (act === 'pick') {
        try { await window.LocalAudio.pickDir(); await loadLocal(true); }
        catch (err) { if (err.name !== 'AbortError') showOverlay('<h2>Error</h2><p>' + esc(err.message) + '</p>'); }
      }
    });
    overlay.addEventListener('change', e => {
      if (e.target.dataset && e.target.dataset.ea === 'file' && e.target.files[0]) useFile(e.target.files[0]);
    });

    o.box.innerHTML = '<div class="ea-status">Loading audio…</div>';
    (async () => {
      if (o.serverUrl) { audio.src = o.serverUrl; if (!(await begin())) askStart(); }
      else await loadLocal(false);
    })().catch(err => showOverlay('<h2>Error</h2><p>' + esc(err.message) + '</p>'));

    return { clear: () => store.del(o.key) };
  }

  window.ExamAudio = { start: start, clear: k => store.del(k) };
})();
