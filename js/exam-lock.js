/*
 * 시험 화면 잠금 — 전체 화면 강제 + 다른 프로그램으로 넘어가기(Alt+Tab 등) 막기/감지
 *   - 브라우저는 페이지가 바뀌면 전체 화면을 반드시 풀고, 사람이 누르지 않으면 다시 켤 수 없음
 *     → 시험 화면을 열면 화면 전체를 덮는 [Start Test] 버튼 한 번으로 전체 화면 시작
 *   - Chrome / Edge (HTTPS 또는 localhost): Keyboard Lock API 로 Alt+Tab · Windows 키 · Esc 를 페이지가 가로챔
 *     (Esc 는 길게 누르면 풀림 — 브라우저 규칙. Ctrl+Alt+Del 은 어떤 웹페이지도 막을 수 없음)
 *   - 그래도 전체 화면이 풀리거나 창을 벗어나면 시험지를 가리고 경고 + 횟수 표시, 버튼을 눌러야 돌아옴
 * 사용: ExamLock.init({ key, title, onStart })   — onStart: 처음 전체 화면이 켜진 뒤 한 번 (Listening 음원 시작 등)
 */
(function () {
  const store = {
    get(k) { try { return +sessionStorage.getItem(k) || 0; } catch (e) { return 0; } },
    set(k, v) { try { sessionStorage.setItem(k, String(v)); } catch (e) {} },
  };

  const KEYS = ['Escape', 'Tab', 'AltLeft', 'AltRight', 'MetaLeft', 'MetaRight', 'F11', 'ContextMenu'];

  function isFull() { return !!(document.fullscreenElement || document.webkitFullscreenElement); }

  function enterFull() {
    const el = document.documentElement;
    const req = el.requestFullscreen || el.webkitRequestFullscreen;
    if (!req) return Promise.resolve(false);
    let p;
    try { p = req.call(el, { navigationUI: 'hide' }); } catch (e) { return Promise.resolve(false); }
    return Promise.resolve(p).then(() => {
      if (navigator.keyboard && navigator.keyboard.lock) navigator.keyboard.lock(KEYS).catch(() => {});
      return true;
    }, () => false);
  }

  function init(o) {
    o = o || {};
    const vKey = 'exam-lock-v-' + (o.key || location.pathname);
    let started = false, leaving = false, quiet = 0, away = false;

    const style = document.createElement('style');
    style.textContent = '.el-overlay{position:fixed;inset:0;z-index:2000;display:flex;align-items:center;justify-content:center;background:#1f2937}'
      + '.el-card{background:#fff;max-width:540px;width:calc(100% - 32px);padding:30px;border-radius:8px;text-align:center;font:15px/1.6 Arial,Helvetica,sans-serif;color:#222}'
      + '.el-card h2{font-size:21px;font-weight:700;margin:0 0 10px}'
      + '.el-card p{margin:0 0 14px;color:#555}'
      + '.el-card .el-warn{color:#b00020;font-weight:700}'
      + '.el-btn{background:#1a5bb8;color:#fff;border:0;border-radius:4px;padding:12px 28px;font-size:16px;font-weight:700;cursor:pointer}'
      + '.el-btn:hover{background:#154a96}';
    document.head.appendChild(style);

    const overlay = document.createElement('div');
    overlay.className = 'el-overlay';
    document.body.appendChild(overlay);

    function show(html) { overlay.innerHTML = '<div class="el-card">' + html + '</div>'; overlay.style.display = 'flex'; }
    function hide() { overlay.style.display = 'none'; }

    function showStart() {
      show('<h2>' + (o.title || 'Test') + '</h2>'
        + '<p>The test will open in <b>full screen</b>.<br>Do not leave the test screen or switch to other programs until you submit.</p>'
        + '<button type="button" class="el-btn" data-el="go">Start Test</button>');
    }

    function showWarn() {
      const n = store.get(vKey);
      show('<h2>Return to the test</h2>'
        + '<p class="el-warn">You left the test screen' + (n ? ' (' + n + (n === 1 ? ' time' : ' times') + ')' : '') + '.</p>'
        + '<p>Switching to other programs or leaving full screen is not allowed during the test.<br>The timer keeps running.</p>'
        + '<button type="button" class="el-btn" data-el="go">Return to full screen</button>');
    }

    function violation() {
      if (!started || leaving || quiet || away) return;
      away = true;
      store.set(vKey, store.get(vKey) + 1);
      showWarn();
    }

    overlay.addEventListener('click', async e => {
      if (!(e.target.dataset && e.target.dataset.el === 'go')) return;
      const ok = await enterFull();
      if (!ok && !isFull()) {
        // 전체 화면을 지원하지 않는 브라우저: 경고만 하고 진행
        if (!started) { started = true; hide(); o.onStart && o.onStart(); }
        else { away = false; hide(); }
        return;
      }
      away = false;
      hide();
      if (!started) { started = true; o.onStart && o.onStart(); }
    });

    function onFsChange() { if (!isFull()) violation(); }
    document.addEventListener('fullscreenchange', onFsChange);
    document.addEventListener('webkitfullscreenchange', onFsChange);
    // 다른 창으로 이동(Alt+Tab, 작업 표시줄 클릭 등)
    window.addEventListener('blur', () => setTimeout(() => { if (!document.hasFocus()) violation(); }, 150));
    document.addEventListener('visibilitychange', () => { if (document.hidden) violation(); });

    // 제출·다음 단계로 넘어갈 때 풀리는 전체 화면은 위반 아님
    window.addEventListener('beforeunload', () => { leaving = true; });
    window.addEventListener('pagehide', () => { leaving = true; });
    // confirm/alert 창이 잠깐 포커스를 가져가도 위반 아님
    ['confirm', 'alert'].forEach(fn => {
      const orig = window[fn];
      window[fn] = function () { quiet++; try { return orig.apply(window, arguments); } finally { setTimeout(() => { quiet--; }, 300); } };
    });

    // 우클릭 메뉴 막기
    document.addEventListener('contextmenu', e => e.preventDefault());

    showStart();
  }

  window.ExamLock = { init: init };
})();
