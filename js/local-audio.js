/*
 * 각 PC 에 있는 IELTS 듣기 음원 연결 (예: D:\588 SUNDAE\IELTS MOCK TEST\IELTS MOCKTEST AUDIO)
 * - Chrome / Edge: 폴더를 한 번 선택하면 그 PC 브라우저에 기억 (IndexedDB). 세트 번호에 맞는 "SET N AUDIO.mp3" 를 찾아 재생
 * - 다른 브라우저: 파일을 직접 고르는 칸을 보여줌
 * 사용: LocalAudio.mount(document.getElementById('local-audio'), 3)
 */
(function () {
  const DB = 'smeag-local-audio', STORE = 'kv', KEY = 'audioDir';
  const supported = 'showDirectoryPicker' in window;

  function idb(mode, fn) {
    return new Promise((resolve, reject) => {
      const open = indexedDB.open(DB, 1);
      open.onupgradeneeded = () => open.result.createObjectStore(STORE);
      open.onerror = () => reject(open.error);
      open.onsuccess = () => {
        const tx = open.result.transaction(STORE, mode);
        const req = fn(tx.objectStore(STORE));
        tx.oncomplete = () => resolve(req && req.result);
        tx.onerror = () => reject(tx.error);
      };
    });
  }
  const getDir = () => idb('readonly', s => s.get(KEY)).catch(() => null);
  const saveDir = h => idb('readwrite', s => s.put(h, KEY));

  // "SET 3 AUDIO.mp3", "set03 audio.MP3" 등 → 세트 번호로 찾기
  async function findFile(dir, setNo) {
    const re = new RegExp('^set\\s*0*' + setNo + '(?!\\d).*\\.(mp3|m4a|wav|ogg)$', 'i');
    for await (const entry of dir.values()) {
      if (entry.kind === 'file' && re.test(entry.name)) return entry.getFile();
    }
    return null;
  }

  function esc(s) { return String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

  // opts.checkOnly: 재생기 대신 "연결됨" 표시만 (시험 전 안내 화면용)
  function mount(box, setNo, opts) {
    const checkOnly = !!(opts && opts.checkOnly);
    let objectUrl = null;
    const btn = 'bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition text-sm';
    const btn2 = 'bg-white text-purple-700 border border-purple-300 px-3 py-1 rounded-lg hover:bg-purple-50 transition text-xs';

    function render(html, tone) {
      const cls = tone === 'ok' ? 'bg-green-50 border-green-300' : tone === 'warn' ? 'bg-yellow-50 border-yellow-300' : 'bg-purple-50 border-purple-200';
      box.innerHTML = '<div class="p-4 rounded-lg border ' + cls + ' text-sm text-gray-700">' + html + '</div>';
    }

    function play(file, note) {
      if (checkOnly) {
        render('<div class="flex justify-between items-center gap-2"><span>✅ Audio connected: <b>' + esc(file.name) + '</b>'
          + (note ? ' <span class="text-gray-500">(' + esc(note) + ')</span>' : '') + '</span>'
          + (supported ? '<button type="button" data-act="pick" class="' + btn2 + '">Change folder</button>' : '') + '</div>', 'ok');
        return;
      }
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = URL.createObjectURL(file);
      render('<div class="flex justify-between items-center mb-2 gap-2"><span>🎧 <b>' + esc(file.name) + '</b>' + (note ? ' <span class="text-gray-500">(' + esc(note) + ')</span>' : '') + '</span>'
        + (supported ? '<button type="button" data-act="pick" class="' + btn2 + '">Change folder</button>' : '') + '</div>'
        + '<audio controls preload="auto" class="w-full" src="' + objectUrl + '"></audio>', 'ok');
    }

    async function load(dir, ask) {
      let perm = await dir.queryPermission({ mode: 'read' });
      if (perm !== 'granted' && ask) perm = await dir.requestPermission({ mode: 'read' });
      if (perm !== 'granted') {
        render('Audio folder <b>' + esc(dir.name) + '</b> is set on this computer. Click to allow access.<div class="mt-3">'
          + '<button type="button" data-act="allow" class="' + btn + '">Connect audio</button> '
          + '<button type="button" data-act="pick" class="' + btn2 + '">Change folder</button></div>');
        return;
      }
      const file = await findFile(dir, setNo);
      if (file) play(file, dir.name);
      else render('⚠️ <b>SET ' + setNo + ' AUDIO.mp3</b> was not found in folder <b>' + esc(dir.name) + '</b>.<div class="mt-3">'
        + '<button type="button" data-act="pick" class="' + btn + '">Select another folder</button></div>', 'warn');
    }

    async function pick() {
      try {
        const dir = await window.showDirectoryPicker({ id: 'smeag-audio', mode: 'read' });
        await saveDir(dir);
        await load(dir, true);
      } catch (e) { if (e.name !== 'AbortError') render('⚠️ ' + esc(e.message), 'warn'); }
    }

    box.addEventListener('click', async e => {
      const act = e.target.dataset && e.target.dataset.act;
      if (act === 'pick') pick();
      if (act === 'allow') { const dir = await getDir(); if (dir) load(dir, true); else pick(); }
    });

    if (!supported) {
      render('Select the audio file <b>SET ' + setNo + ' AUDIO.mp3</b> on this computer. <span class="text-gray-500">(Chrome or Edge can remember the folder.)</span>'
        + '<div class="mt-3"><input type="file" accept="audio/*" class="text-sm"></div>');
      box.querySelector('input').addEventListener('change', e => { if (e.target.files[0]) play(e.target.files[0]); });
      return;
    }

    getDir().then(dir => {
      if (dir) return load(dir, false);
      render('No audio folder is set on this computer yet. Select the folder that has <b>SET 1 AUDIO.mp3 ~ SET 30 AUDIO.mp3</b> '
        + '<span class="text-gray-500">(e.g. D:\\588 SUNDAE\\IELTS MOCK TEST\\IELTS MOCKTEST AUDIO)</span>.'
        + '<div class="mt-3"><button type="button" data-act="pick" class="' + btn + '">Select audio folder</button></div>');
    }).catch(e => render('⚠️ ' + esc(e.message), 'warn'));
  }

  window.LocalAudio = { mount: mount, supported: supported };
})();
