/*
 * 시험 화면 잠금 — 전체 화면 강제 + 다른 프로그램으로 넘어가기(Alt+Tab 등) 막기/감지
 *   - 브라우저는 페이지가 바뀌면 전체 화면을 반드시 풀고, 사람이 누르지 않으면 다시 켤 수 없음
 *     → 시험 화면을 열면 화면 전체를 덮는 [Start Test] 버튼 한 번으로 전체 화면 시작
 *   - Chrome / Edge (HTTPS 또는 localhost): Keyboard Lock API 로 Alt+Tab · Windows 키를 페이지가 가로챔
 *     (Ctrl+Alt+Del 은 어떤 웹페이지도 막을 수 없음)
 *   - Esc 는 막지 않음: 한 번 누르면 전체 화면이 풀리고 시험은 일반 창에서 그대로 계속 (경고·횟수 없음)
 *   - 다른 프로그램·창으로 넘어가면 시험지를 가리고 경고 + 횟수 표시, 버튼을 눌러야 돌아옴 (전체 화면도 다시 켬)
 * 사용: ExamLock.init({ key, title, resumed, onStart })   — onStart: 처음 전체 화면이 켜진 뒤 한 번 (Listening 음원 시작, 시간 세기 시작)
 */
(function () {
  const store = {
    get(k) { try { return +sessionStorage.getItem(k) || 0; } catch (e) { return 0; } },
    set(k, v) { try { sessionStorage.setItem(k, String(v)); } catch (e) {} },
  };

  // Escape 는 넣지 않음 → 한 번 눌러 전체 화면을 나갈 수 있음
  const KEYS = ['Tab', 'AltLeft', 'AltRight', 'MetaLeft', 'MetaRight', 'F11', 'ContextMenu'];

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

    // 떠난 횟수: 이 탭(sessionStorage)과 서버(o.count, 이어서 보기) 중 큰 값. 폼의 숨은 칸 _leaves 로 서버에 보냄 (자동 저장·제출)
    if ((+o.count || 0) > store.get(vKey)) store.set(vKey, +o.count);
    // 전체 화면을 나간 횟수 (Esc 등): 막지는 않고 세기만 함. 같은 방식으로 _fsexits 로 보냄
    const fKey = 'exam-lock-f-' + (o.key || location.pathname);
    if ((+o.fsCount || 0) > store.get(fKey)) store.set(fKey, +o.fsCount);
    let leavesInput = null, fsInput = null;
    if (o.form) {
      leavesInput = document.createElement('input');
      leavesInput.type = 'hidden'; leavesInput.name = '_leaves'; leavesInput.value = String(store.get(vKey));
      o.form.appendChild(leavesInput);
      fsInput = document.createElement('input');
      fsInput.type = 'hidden'; fsInput.name = '_fsexits'; fsInput.value = String(store.get(fKey));
      o.form.appendChild(fsInput);
    }

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
      // o.resumed: 정전 등으로 끊겼던 시험 → 답안·남은 시간 (Listening 은 음원 위치) 그대로 이어서
      show('<h2>' + (o.title || 'Test') + '</h2>'
        + (o.resumed ? '<p><b>Your test was interrupted.</b><br>Your answers and remaining time have been restored. The test continues from where you stopped.</p>' : '')
        + '<p>The test will open in <b>full screen</b> (press <b>Esc</b> to leave full screen).<br>Do not switch to other programs until you submit.</p>'
        + '<button type="button" class="el-btn" data-el="go">' + (o.resumed ? 'Continue Test' : 'Start Test') + '</button>');
    }

    function showWarn() {
      const n = store.get(vKey);
      show('<h2>Return to the test</h2>'
        + '<p class="el-warn">You left the test screen' + (n ? ' (' + n + (n === 1 ? ' time' : ' times') + ')' : '') + '.</p>'
        + '<p>Switching to other programs is not allowed during the test.<br>The timer keeps running.</p>'
        + '<button type="button" class="el-btn" data-el="go">Return to the test</button>');
    }

    function violation() {
      if (!started || leaving || quiet || away) return;
      away = true;
      store.set(vKey, store.get(vKey) + 1);
      if (leavesInput) leavesInput.value = String(store.get(vKey));
      showWarn();
      if (o.onLeave) { try { o.onLeave(store.get(vKey)); } catch (e) {} }
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

    // 전체 화면에서 나가는 것(Esc)은 허용 — 경고 없이 횟수만 셈 (fKey). 다른 프로그램으로 넘어가면서 풀린 것은 "떠난 횟수"로만 셈
    function onFullChange() {
      if (isFull() || !started || leaving) return;
      setTimeout(() => {
        if (leaving || away || quiet || document.hidden || !document.hasFocus() || isFull()) return;
        store.set(fKey, store.get(fKey) + 1);
        if (fsInput) fsInput.value = String(store.get(fKey));
        if (o.onLeave) { try { o.onLeave(store.get(vKey)); } catch (e) {} }
      }, 400);
    }
    document.addEventListener('fullscreenchange', onFullChange);
    document.addEventListener('webkitfullscreenchange', onFullChange);

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
