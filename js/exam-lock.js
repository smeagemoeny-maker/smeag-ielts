/*
 * 시험 화면 잠금 — 전체 화면 강제 + 다른 프로그램으로 넘어가기(Alt+Tab 등) 막기/감지
 *   - 브라우저는 페이지가 바뀌면 전체 화면을 반드시 풀고, 사람이 누르지 않으면 다시 켤 수 없음
 *     → 시험 화면을 열면 화면 전체를 덮는 [Start Test] 버튼 한 번으로 전체 화면 시작
 *   - Chrome / Edge (HTTPS 또는 localhost): Keyboard Lock API 로 Alt+Tab · Windows 키를 페이지가 가로챔
 *     (Ctrl+Alt+Del 은 어떤 웹페이지도 막을 수 없음)
 *   - Esc 잠금: Keyboard Lock 에 Escape 를 넣어 한 번 누르는 Esc 는 페이지가 가로챔
 *     (Chrome 은 Esc 를 2초 이상 누르고 있으면 브라우저가 강제로 풀어 줌 — 웹페이지가 막을 수 없음)
 *     → 허락 없이 전체 화면이 풀리면 시험지를 가리고 [Return to Full Screen] 버튼을 눌러야 계속 (횟수 셈)
 *   - 감독관 해제: Ctrl+Shift+F12 → 비밀번호(EXIT_PASSWORD) 입력 → 전체 화면 해제, 이후 일반 창에서 계속
 *   - 다른 프로그램·창으로 넘어가면 시험지를 가리고 경고 + 횟수 표시, 버튼을 눌러야 돌아옴 (전체 화면도 다시 켬)
 * 사용: ExamLock.init({ key, title, resumed, onStart, onRestart })   — onStart: 처음 전체 화면이 켜진 뒤 한 번 (Listening 음원 시작, 시간 세기 시작)
 *   onRestart: (이어서 보는 시험일 때만) [Start from the Beginning] 버튼을 보여 주고, 누르면 확인 뒤 호출 (Promise, 실패하면 throw)
 */
(function () {
  const store = {
    get(k) { try { return +sessionStorage.getItem(k) || 0; } catch (e) { return 0; } },
    set(k, v) { try { sessionStorage.setItem(k, String(v)); } catch (e) {} },
  };

  // Escape 포함 → Esc 한 번으로는 전체 화면이 풀리지 않음
  const KEYS = ['Escape', 'Tab', 'AltLeft', 'AltRight', 'MetaLeft', 'MetaRight', 'F11', 'ContextMenu'];

  // 감독관용 전체 화면 해제 비밀번호 (Ctrl+Shift+F12)
  const EXIT_PASSWORD = '123';

  function isFull() { return !!(document.fullscreenElement || document.webkitFullscreenElement); }
  // 이 브라우저가 전체 화면을 지원하는지 (지원 안 하면 경고만 하고 진행)
  function fsSupported() {
    const el = document.documentElement;
    return !!((el.requestFullscreen || el.webkitRequestFullscreen) && document.fullscreenEnabled !== false);
  }

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
    let unlocked = false, pwOpen = false;   // unlocked: 감독관이 비밀번호로 전체 화면을 풀어 줌

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
      + '.el-btn:hover{background:#154a96}'
      + '.el-btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}'
      + '.el-btn2{background:#fff;color:#1a5bb8;border:2px solid #1a5bb8;padding:10px 26px}'
      + '.el-btn2:hover{background:#eef3fb}'
      + '.el-btn:disabled{opacity:.6;cursor:default}';
    document.head.appendChild(style);

    const overlay = document.createElement('div');
    overlay.className = 'el-overlay';
    document.body.appendChild(overlay);

    function show(html) { overlay.innerHTML = '<div class="el-card">' + html + '</div>'; overlay.style.display = 'flex'; }
    function hide() { overlay.style.display = 'none'; }

    function showStart() {
      // o.resumed: 정전 등으로 끊겼던 시험 → 답안·남은 시간 (Listening 은 음원 위치) 그대로 이어서
      show('<h2>' + (o.title || 'Test') + '</h2>'
        + (o.resumed && o.onRestart
          ? '<p><b>Your test was interrupted.</b><br>Choose <b>Continue Test</b> to carry on from where you stopped with your answers kept, or <b>Start from the Beginning</b> to take this section again from the start.</p>'
          : o.resumed ? '<p><b>Your test was interrupted.</b><br>Your answers and remaining time have been restored. The test continues from where you stopped.</p>' : '')
        + '<p>Do not switch to other programs until you submit.</p>'
        + '<div class="el-btns"><button type="button" class="el-btn" data-el="go">' + (o.resumed ? 'Continue Test' : 'Start Test') + '</button>'
        + (o.resumed && o.onRestart ? '<button type="button" class="el-btn el-btn2" data-el="restart">Start from the Beginning</button>' : '')
        + '</div>');
    }

    function showWarn() {
      const n = store.get(vKey);
      show('<h2>Return to the test</h2>'
        + '<p class="el-warn">You left the test screen' + (n ? ' (' + n + (n === 1 ? ' time' : ' times') + ')' : '') + '.</p>'
        + '<p>Switching to other programs is not allowed during the test.<br>The timer keeps running.</p>'
        + '<button type="button" class="el-btn" data-el="go">Return to the test</button>');
    }

    function showFsLost() {
      show('<h2>Return to full screen</h2>'
        + '<p class="el-warn">The test must be taken in full screen.</p>'
        + '<p>The timer keeps running.</p>'
        + '<button type="button" class="el-btn" data-el="go">Return to Full Screen</button>');
    }

    function showPassword() {
      pwOpen = true;
      show('<h2>Exit Full Screen</h2>'
        + '<p>Enter the password to exit full screen.</p>'
        + '<p><input type="password" data-el="pw" autocomplete="off" style="font-size:16px;padding:8px 10px;width:200px;border:1px solid #bbb;border-radius:4px"></p>'
        + '<p class="el-warn" data-el="pwmsg" style="display:none">Incorrect password.</p>'
        + '<div class="el-btns"><button type="button" class="el-btn" data-el="pw-ok">Exit Full Screen</button>'
        + '<button type="button" class="el-btn el-btn2" data-el="pw-cancel">Cancel</button></div>');
      const inp = overlay.querySelector('[data-el="pw"]');
      inp.focus();
      inp.addEventListener('keydown', ev => {
        if (ev.key === 'Enter') { ev.preventDefault(); checkPassword(); }
        else if (ev.key === 'Escape') { ev.preventDefault(); closePassword(); }
      });
    }

    function closePassword() {
      pwOpen = false;
      if (isFull()) hide(); else showFsLost();
    }

    function checkPassword() {
      const inp = overlay.querySelector('[data-el="pw"]');
      if (!inp) return;
      if (inp.value !== EXIT_PASSWORD) {
        inp.value = ''; inp.focus();
        overlay.querySelector('[data-el="pwmsg"]').style.display = '';
        return;
      }
      pwOpen = false; unlocked = true;
      if (navigator.keyboard && navigator.keyboard.unlock) { try { navigator.keyboard.unlock(); } catch (e) {} }
      hide();
      if (isFull()) {
        const ex = document.exitFullscreen || document.webkitExitFullscreen;
        if (ex) { try { Promise.resolve(ex.call(document)).catch(() => {}); } catch (e) {} }
      }
    }

    // Ctrl+Shift+F12: 감독관 비밀번호로 전체 화면 해제 / 전체 화면에서 Esc 는 무시
    document.addEventListener('keydown', e => {
      if (e.ctrlKey && e.shiftKey && (e.key === 'F12' || e.code === 'F12')) {
        e.preventDefault(); e.stopPropagation();
        if (started && !away && !unlocked && !pwOpen) showPassword();
        return;
      }
      if (e.key === 'Escape' && started && !unlocked && !pwOpen && isFull()) e.preventDefault();
    }, true);

    function violation() {
      if (!started || leaving || quiet || away) return;
      away = true;
      store.set(vKey, store.get(vKey) + 1);
      if (leavesInput) leavesInput.value = String(store.get(vKey));
      showWarn();
      if (o.onLeave) { try { o.onLeave(store.get(vKey)); } catch (e) {} }
    }

    overlay.addEventListener('click', async e => {
      // 정전 뒤 다시 열었을 때: 처음부터 다시 (o.onRestart — 저장된 답안·위치를 지우고 새로 시작)
      if (e.target.dataset && e.target.dataset.el === 'restart' && !started && o.onRestart) {
        if (!confirm('Start this section again from the beginning?\n\nAll the answers you have written so far will be deleted.')) return;
        overlay.querySelectorAll('button').forEach(b => { b.disabled = true; });
        e.target.textContent = 'Restarting…';
        leaving = true;
        try { await o.onRestart(); } catch (err) {
          leaving = false;
          overlay.querySelectorAll('button').forEach(b => { b.disabled = false; });
          e.target.textContent = 'Start from the Beginning';
          alert('Could not restart the test. Please check the internet connection and try again.');
        }
        return;
      }
      if (e.target.dataset && e.target.dataset.el === 'pw-ok') { checkPassword(); return; }
      if (e.target.dataset && e.target.dataset.el === 'pw-cancel') { closePassword(); return; }
      if (!(e.target.dataset && e.target.dataset.el === 'go')) return;
      // 감독관이 해제한 뒤에는 전체 화면 없이 돌아감
      if (unlocked) { away = false; hide(); return; }
      const ok = await enterFull();
      if (!ok && !isFull()) {
        if (!fsSupported()) {
          // 전체 화면을 지원하지 않는 브라우저: 경고만 하고 진행
          if (!started) { started = true; hide(); o.onStart && o.onStart(); }
          else { away = false; hide(); }
          return;
        }
        // 지원하는데 거절됨 (창이 아직 앞에 오지 않았을 때 등) → 버튼을 한 번 더 누르게 함. 전체 화면 없이 시험지로 돌아가지 않음
        if (started) { away = false; showFsLost(); }
        return;
      }
      away = false;
      hide();
      if (!started) { started = true; o.onStart && o.onStart(); }
      checkStillFull();
    });

    // 돌아온 뒤 잠깐 사이에 전체 화면이 다시 풀렸으면 (창 전환 직후 등) 다시 가림
    function checkStillFull() {
      setTimeout(() => {
        if (started && !unlocked && !pwOpen && !leaving && !away && fsSupported() && !isFull()) showFsLost();
      }, 600);
    }

    // 안전장치: 시험 중 전체 화면이 아닌 채로 시험지를 누르거나 키를 치면 그 순간 전체 화면으로 되돌림
    function refull() {
      if (!started || unlocked || pwOpen || leaving || away || isFull() || !fsSupported()) return;
      enterFull().then(ok => { if (ok) hide(); });
    }
    document.addEventListener('pointerdown', e => { if (!overlay.contains(e.target)) refull(); }, true);
    document.addEventListener('keydown', e => {
      if (e.ctrlKey && e.shiftKey && (e.key === 'F12' || e.code === 'F12')) return;
      if (!overlay.contains(e.target)) refull();
    }, true);

    // 허락 없이 전체 화면이 풀림 (Esc 길게 누르기 등) → 횟수를 세고 시험지를 가린 뒤 다시 전체 화면으로 돌아오게 함
    // 다른 프로그램으로 넘어가면서 풀린 것은 "떠난 횟수"로만 셈. 감독관 해제(unlocked) 뒤에는 세지 않음
    function onFullChange() {
      if (isFull() || !started || leaving || unlocked) return;
      setTimeout(() => {
        if (leaving || unlocked || away || quiet || document.hidden || !document.hasFocus() || isFull()) return;
        store.set(fKey, store.get(fKey) + 1);
        if (fsInput) fsInput.value = String(store.get(fKey));
        if (!pwOpen) showFsLost();
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
