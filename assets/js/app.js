/* app.js — AI Study Tracker & Reviewer
   Features: lesson/quiz flow, persistent quiz state, action buttons,
   pinned sidebar, archive sidebar, trash sidebar, image upload,
   retry, score feedback, active-chat-delete navigation fix */
(function () {
  'use strict';

  /* ── Helpers ─────────────────────────────────────────────── */
  const $ = id => document.getElementById(id);
  const csrfToken = () => window.APP_CSRF || document.querySelector('meta[name="csrf-token"]')?.content || '';

  function escHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // Ripple
  document.querySelectorAll('.ripple').forEach(attachRipple);
  function attachRipple(el) {
    el.addEventListener('click', e => {
      const r = document.createElement('span');
      r.className = 'ripple-circle';
      const rect = el.getBoundingClientRect();
      r.style.left = (e.clientX - rect.left - 5) + 'px';
      r.style.top = (e.clientY - rect.top - 5) + 'px';
      el.appendChild(r);
      r.addEventListener('animationend', () => r.remove());
    });
  }

  function autoResize(ta) {
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
  }

  ['topicInput', 'chatInput'].forEach(id => {
    const ta = $(id);
    if (ta) ta.addEventListener('input', () => autoResize(ta));
  });

  /* ── Internet status ─────────────────────────────────────── */
  const netPill = $('netPill');
  let pendingOfflineRetry = null;

  function updateNet() {
    if (!netPill) return;
    if (navigator.onLine) {
      netPill.className = 'net-pill online';
      netPill.innerHTML = '<span class="dot"></span> Connected';
      if (pendingOfflineRetry) {
        const action = pendingOfflineRetry;
        pendingOfflineRetry = null;
        const offlineNotice = document.getElementById('offlineNoticeRow');
        if (offlineNotice) offlineNotice.remove();
        if (typeof action === 'function') action();
      } else {
        const offlineNotice = document.getElementById('offlineNoticeRow');
        if (offlineNotice) offlineNotice.remove();
        if (activePolls && Object.keys(activePolls).length > 0) {
          if (!document.querySelector('.generating')) {
            appendTyping();
          }
        }
      }
    } else {
      netPill.className = 'net-pill offline';
      netPill.innerHTML = '<span class="dot"></span> No Internet';
      const genBubble = document.querySelector('.generating');
      if (genBubble) {
        document.querySelectorAll('.generating').forEach(el => {
          const msgAi = el.closest('.msg.ai');
          if (msgAi) msgAi.remove();
        });
        const offlineNotice = document.getElementById('offlineNoticeRow');
        if (!offlineNotice) {
          appendOfflineErrorNotice();
        }
      }
    }
  }
  updateNet();
  window.addEventListener('online', updateNet);
  window.addEventListener('offline', updateNet);

  /* ── View switcher ───────────────────────────────────────── */
  const views = { home: $('viewHome'), analytics: $('viewAnalytics'), settings: $('viewSettings') };

  function showView(name) {
    Object.entries(views).forEach(([k, el]) => {
      if (!el) return;
      el.style.display = (k === name) ? (k === 'home' ? 'flex' : 'block') : 'none';
    });
    document.querySelectorAll('.side-link[data-view]').forEach(l => l.classList.remove('active'));
    const btn = document.querySelector(`.side-link[data-view="${name}"]`);
    if (btn) btn.classList.add('active');
    // Load contextual data when switching views
    if (name === 'analytics') loadStats();
    if (name === 'settings') loadTrashSettings();
  }

  document.querySelectorAll('.side-link[data-view]').forEach(btn => {
    btn.addEventListener('click', () => {
      const v = btn.dataset.view;
      if (v === 'history') { toggleHistory(); return; }
      if (v === 'home') { showView('home'); return; }
      showView(v);
    });
  });

  // Settings button in side-bottom (uses .side-settings class, data-view="settings")
  document.querySelectorAll('.side-settings[data-view]').forEach(btn => {
    btn.addEventListener('click', () => showView(btn.dataset.view));
  });

  /* ══════════════════════════════════════════════════════════
     ACTIVE CHAT TRACKING
     Track which saved session the user is currently viewing,
     so we can redirect to Home if they delete it.
     ══════════════════════════════════════════════════════════ */
  let currentHistoryId = null;   // id of the open saved session (null = new/unsaved)
  let currentHistorySource = null;   // 'history' | 'pinned' | 'archive' | null

  /* ══════════════════════════════════════════════════════════
     GLOBAL CONTEXT MENU  (History / Pinned items)
     Built once, repositioned on every dots-button click.
     Uses position:fixed so it escapes the sidebar's overflow.
     ══════════════════════════════════════════════════════════ */
  let ctxTarget = null; // { id, type:'history'|'pinned', titleEl, dotsBtn, isPinned }

  const histCtxMenu = (() => {
    const m = document.createElement('div');
    m.className = 'hist-ctx-menu';
    m.style.display = 'none';
    m.innerHTML = `
      <button class="ctx-item" data-action="rename">
        <span class="material-icons">drive_file_rename_outline</span><span>Rename</span>
      </button>
      <button class="ctx-item" data-action="archive">
        <span class="material-icons">inventory_2</span><span>Archive</span>
      </button>
      <button class="ctx-item" data-action="pin">
        <span class="material-icons">push_pin</span><span>Pin</span>
      </button>
      <button class="ctx-item ctx-danger" data-action="delete">
        <span class="material-icons">delete_outline</span><span>Delete</span>
      </button>`;
    document.body.appendChild(m);
    return m;
  })();

  function openCtxMenu(dotsBtn, id, type, titleEl, isPinned) {
    ctxTarget = { id, type, titleEl, dotsBtn, isPinned };

    // Update labels and visibility based on type
    const renameBtn = histCtxMenu.querySelector('[data-action="rename"]');
    const archiveBtn = histCtxMenu.querySelector('[data-action="archive"]');
    const pinBtn = histCtxMenu.querySelector('[data-action="pin"]');

    if (type === 'archive') {
      renameBtn.style.display = 'flex';
      archiveBtn.querySelector('.material-icons').textContent = 'restore';
      archiveBtn.querySelector('span:last-child').textContent = 'Restore';
      pinBtn.style.display = 'none';
    } else if (type === 'trash') {
      renameBtn.style.display = 'none';
      archiveBtn.querySelector('.material-icons').textContent = 'restore';
      archiveBtn.querySelector('span:last-child').textContent = 'Restore';
      pinBtn.style.display = 'none';
    } else {
      renameBtn.style.display = 'flex';
      archiveBtn.querySelector('.material-icons').textContent = 'inventory_2';
      archiveBtn.querySelector('span:last-child').textContent = 'Archive';
      pinBtn.style.display = 'flex';
      const pinText = pinBtn.querySelector('span:last-child');
      if (pinText) pinText.textContent = isPinned ? 'Unpin' : 'Pin';
    }

    // Mark button as open
    document.querySelectorAll('.hist-dots-btn.ctx-open').forEach(b => b.classList.remove('ctx-open'));
    dotsBtn.classList.add('ctx-open');

    // Show (needed before measuring height)
    histCtxMenu.style.display = 'block';
    histCtxMenu.style.animation = 'none';
    void histCtxMenu.offsetWidth; // flush reflow
    histCtxMenu.style.animation = '';

    const rect = dotsBtn.getBoundingClientRect();
    const menuH = histCtxMenu.offsetHeight || 170;
    const menuW = histCtxMenu.offsetWidth || 162;

    // Guard: if the button rect has zero dimensions (element hidden/clipped),
    // fall back to positioning relative to the history item or sidebar.
    const hasValidRect = rect.width > 0 && rect.height > 0;

    let top, left;
    if (hasValidRect) {
      // Flip up if not enough space below
      top = (window.innerHeight - rect.bottom < menuH + 8)
        ? Math.max(8, rect.top - menuH - 4)
        : rect.bottom + 4;

      // Position to the right of the button; if that would go off-screen, flip left
      left = rect.right + 4;
      if (left + menuW > window.innerWidth - 8) {
        left = rect.left - menuW - 4;
      }
      // Final clamp
      if (left < 8) left = 8;
    } else {
      // Fallback: position relative to the parent history-item or the sidebar
      const parentItem = dotsBtn.closest('.history-item');
      const fallbackRect = parentItem
        ? parentItem.getBoundingClientRect()
        : (dotsBtn.closest('.sidebar')?.getBoundingClientRect() || { top: 100, right: 290, bottom: 130 });

      top = Math.min(fallbackRect.top, window.innerHeight - menuH - 8);
      left = fallbackRect.right + 4;
      if (left + menuW > window.innerWidth - 8) {
        left = Math.max(8, fallbackRect.left - menuW - 4);
      }
    }

    // Final safety clamp
    top = Math.max(8, Math.min(top, window.innerHeight - menuH - 8));
    left = Math.max(8, Math.min(left, window.innerWidth - menuW - 8));

    histCtxMenu.style.top = top + 'px';
    histCtxMenu.style.left = left + 'px';

    // Prevent the scroll-close listener from immediately closing this menu
    ctxJustOpened = true;
    clearTimeout(ctxJustOpenedTimer);
    ctxJustOpenedTimer = setTimeout(() => { ctxJustOpened = false; }, 200);
  }

  function closeCtxMenu() {
    histCtxMenu.style.display = 'none';
    document.querySelectorAll('.hist-dots-btn.ctx-open').forEach(b => b.classList.remove('ctx-open'));
    ctxTarget = null;
  }

  // Wire up context menu action buttons
  histCtxMenu.querySelectorAll('.ctx-item').forEach(btn => {
    btn.addEventListener('click', async e => {
      e.stopPropagation();
      const action = btn.dataset.action;
      const target = ctxTarget;
      closeCtxMenu();
      if (!target) return;

      if (action === 'rename') {
        if (target.type === 'trash') return;
        startRename(target);
      } else if (action === 'pin') {
        if (target.type === 'archive' || target.type === 'trash') return;
        await togglePinItem(target.id, target.isPinned);
        historyLoaded = false;
        pinnedLoaded = false;
        if (historyOpen) loadHistory();
        if (pinnedOpen) loadPinned();
      } else if (action === 'archive') {
        if (target.type === 'archive') {
          // Restore
          const fd = new FormData();
          fd.append('csrf_token', csrfToken());
          fd.append('id', target.id);
          try {
            const restoreRes = await apiFetch('POST', 'api.php?action=archive_restore', fd);
            const restoreData = await restoreRes.json();
            archiveLoaded = false; historyLoaded = false;
            loadArchive();
            if (historyOpen) loadHistory();
            // Open the newly restored session so user can continue where they left off
            if (restoreData.ok && restoreData.new_id) {
              openHistory(restoreData.new_id, 'history');
            } else if (currentHistoryId == target.id) {
              resetChat();
            }
          } catch { /* silent */ }
        } else if (target.type === 'trash') {
          // Restore from trash
          const fd = new FormData();
          fd.append('csrf_token', csrfToken());
          fd.append('id', target.id);
          try {
            await apiFetch('POST', 'api.php?action=trash_restore', fd);
            historyLoaded = false;
            if (historyOpen) loadHistory();
            loadTrashSettings();
          } catch { /* silent */ }
        } else {
          askArchive(target.id);
        }
      } else if (action === 'delete') {
        if (target.type === 'archive') {
          if (!confirm('Delete this archived session permanently?')) return;
          const fd = new FormData();
          fd.append('csrf_token', csrfToken());
          fd.append('id', target.id);
          try {
            await apiFetch('POST', 'api.php?action=archive_delete', fd);
            archiveLoaded = false;
            loadArchive();
            if (currentHistoryId === target.id && currentHistorySource === 'archive') {
              resetChat();
            }
          } catch { /* silent */ }
        } else if (target.type === 'trash') {
          pendingTrashDeleteId = target.id;
          const modal = $('trashPermanentModal');
          if (modal) modal.style.display = 'flex';
        } else {
          askDeleteHistory(target.id);
        }
      }
    });
  });

  // Close on outside click
  document.addEventListener('click', e => {
    if (histCtxMenu.style.display !== 'none'
      && !histCtxMenu.contains(e.target)
      && !e.target.closest('.hist-dots-btn')) {
      closeCtxMenu();
    }
  }, true);

  // Close on scroll (sidebar scrolls while menu is open)
  // Use a guard so that micro-scrolls triggered by click don't close immediately
  let ctxJustOpened = false;
  let ctxJustOpenedTimer = null;
  document.addEventListener('scroll', () => {
    if (!ctxJustOpened) closeCtxMenu();
  }, true);

  /* ── Inline Rename ──────────────────────────────────────── */
  function startRename({ id, type, titleEl, dotsBtn }) {
    const originalTitle = titleEl.textContent.trim();

    const input = document.createElement('input');
    input.type = 'text';
    input.value = originalTitle;
    input.className = 'hist-rename-input';

    // Swap title → input
    titleEl.style.display = 'none';
    if (dotsBtn) dotsBtn.style.display = 'none';
    titleEl.parentNode.insertBefore(input, titleEl.nextSibling);
    input.focus();
    input.select();

    let done = false;

    async function commit() {
      if (done) return;
      done = true;
      const newTitle = input.value.trim();
      cleanup();
      if (!newTitle || newTitle === originalTitle) return;
      titleEl.textContent = newTitle;
      const fd = new FormData();
      fd.append('csrf_token', csrfToken());
      fd.append('id', id);
      fd.append('title', newTitle);
      try {
        const actionUrl = type === 'archive' ? 'api.php?action=archive_rename' : 'api.php?action=history_rename';
        await apiFetch('POST', actionUrl, fd);
      } catch {
        titleEl.textContent = originalTitle; // revert on error
      }
    }

    function cancel() {
      if (done) return;
      done = true;
      cleanup();
    }

    function cleanup() {
      if (input.parentNode) input.parentNode.removeChild(input);
      titleEl.style.display = '';
      if (dotsBtn) dotsBtn.style.display = '';
    }

    input.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); commit(); }
      if (e.key === 'Escape') { e.preventDefault(); cancel(); }
    });

    input.addEventListener('blur', () => setTimeout(commit, 120));
  }

  /* ── Marquee hover helpers ──────────────────────────────── */
  function attachMarquee(row) {
    const titleEl = row.querySelector('.hist-title');
    const wrapEl = row.querySelector('.hist-title-wrap');
    if (!titleEl || !wrapEl) return;

    row.addEventListener('mouseenter', () => {
      // Stop any running animation first
      titleEl.classList.remove('play-marquee');
      const overflow = titleEl.scrollWidth - wrapEl.clientWidth;
      if (overflow > 4) {
        titleEl.style.setProperty('--marquee-px', `-${overflow + 8}px`);
        // Delay so cursor settling doesn't trigger instant scroll
        row._marqueeTimer = setTimeout(() => titleEl.classList.add('play-marquee'), 350);
      }
    });

    row.addEventListener('mouseleave', () => {
      clearTimeout(row._marqueeTimer);
      titleEl.classList.remove('play-marquee');
      titleEl.style.removeProperty('--marquee-px');
    });
  }

  /* ── History sidebar ─────────────────────────────────────── */
  let historyOpen = false;
  let historyLoaded = false;

  function toggleHistory() {
    historyOpen = !historyOpen;
    const wrap = $('historyListSide');
    const chevron = document.querySelector('.chevron-hist');
    if (wrap) wrap.style.display = historyOpen ? 'block' : 'none';
    if (chevron) chevron.textContent = historyOpen ? 'expand_less' : 'expand_more';
    if (historyOpen && !historyLoaded) { loadHistory(); historyLoaded = true; }
  }

  function forceUpdateHistorySidebar() {
    if (!historyOpen) {
      historyOpen = true;
      const wrap = $('historyListWrap');
      const chevron = $('historyCollapseBtn')?.querySelector('.chevron');
      if (wrap) wrap.style.display = 'block';
      if (chevron) chevron.textContent = 'expand_less';
    }
    historyLoaded = false;
    loadHistory();
  }

  async function loadHistory() {
    try {
      const res = await apiFetch('GET', 'api.php?action=history_list');
      const data = await res.json();
      if (!data.ok) return;
      renderHistoryList(data.items);
    } catch { /* silent */ }
  }

  function renderHistoryList(items) {
    const el = $('historyItems');
    if (!el) return;
    if (!items || !items.length) {
      el.innerHTML = '<div class="side-empty-note">No history yet.</div>';
      return;
    }
    el.className = '';
    el.innerHTML = items.map(item => `
      <div class="history-item" data-id="${item.id}">
        <span class="material-icons hist-type-icon">chat_bubble_outline</span>
        <div class="hist-title-wrap">
          <span class="hist-title">${escHtml(item.title)}</span>
        </div>
        <button class="hist-dots-btn" title="More options" aria-label="More options">
          <span class="material-icons">more_horiz</span>
        </button>
      </div>
    `).join('');

    el.querySelectorAll('.history-item').forEach(row => {
      const id = +row.dataset.id;
      const titleEl = row.querySelector('.hist-title');
      const dotsBtn = row.querySelector('.hist-dots-btn');

      // Open chat on row click (ignore dots)
      row.addEventListener('click', e => {
        if (e.target.closest('.hist-dots-btn')) return;
        closeCtxMenu();
        openHistory(id, 'history');
      });

      // Marquee on hover
      attachMarquee(row);

      // Three-dot context menu
      dotsBtn.addEventListener('click', e => {
        e.stopPropagation();
        if (ctxTarget && ctxTarget.id === id && histCtxMenu.style.display !== 'none') {
          closeCtxMenu(); return;
        }
        openCtxMenu(dotsBtn, id, 'history', titleEl, false);
      });
    });
  }

  async function togglePinItem(id, currentlyPinned) {
    const action = currentlyPinned ? 'pin_remove' : 'pin_add';
    const fd = new FormData();
    fd.append('csrf_token', csrfToken());
    fd.append('id', id);
    try { await apiFetch('POST', `api.php?action=${action}`, fd); } catch { /* silent */ }
  }

  async function openHistory(id, source = 'history') {
    try {
      const apiUrl = source === 'archive'
        ? `api.php?action=archive_open&id=${id}`
        : `api.php?action=history_open&id=${id}`;
      const res = await apiFetch('GET', apiUrl);
      const data = await res.json();
      if (!data.ok || !data.item) return;

      // Track which session is currently open
      currentHistoryId = id;
      currentHistorySource = source;

      showView('home');
      switchToChat();

      const chatPromptWrap = $('chatPromptWrap');
      const archivedNotice = $('archivedNotice');
      if (chatPromptWrap) chatPromptWrap.style.display = source === 'archive' ? 'none' : 'block';
      if (archivedNotice) archivedNotice.style.display = source === 'archive' ? 'block' : 'none';

      const wrap = $('messagesWrap');
      wrap.innerHTML = '';
      msgCounter = 0;

      localStorage.setItem('activeHistoryId', id);

      let stored = [];
      try {
        stored = JSON.parse(data.item.content);
        if (!Array.isArray(stored)) throw new Error();
      } catch {
        stored = [{ role: 'ai', text: data.item.content, type: 'lesson' }];
      }

      messages.length = 0;
      activeQuiz = null;
      currentMode = null;
      lastLessonText = '';
      let lastLesson = '';
      let calledStartQuiz = false;

      stored.forEach(m => {
        messages.push(m);
        if (m.role === 'user') {
          if (m.imageDataUrl) appendImageChipMsg(m.fileName || 'image', m.imageDataUrl, false);
          else if (m.fileName) appendFileChipMsg(m.fileName, false);
          if (m.text) appendMessage('user', escHtml(m.text), false);
        } else if (m.role === 'ai') {
          if (m.type === 'quiz') {
            let calledStartQuiz = false;

            if (!m.quizState) {
              if (m.text) {
                // Newly generated quiz from background worker
                const quizObj = startQuiz(m.text);
                if (quizObj) {
                  m.quizState = quizObj;
                  calledStartQuiz = true;
                  delete m.text; // Remove raw text to free up space
                  saveHistory();
                }
              } else if (m.quizData && m.quizData.questions && m.quizData.questions.length > 0) {
                // Fallback for older saved structures
                m.quizState = {
                  questions: m.quizData.questions.map(q => ({
                    ...q,
                    choices: q.choices.map(c => typeof c === 'string' ? c.replace(/^[A-D]\.\s*/i, '').trim() : c)
                  })),
                  currentIndex: 0,
                  score: 0,
                  answers: {},
                  completed: false,
                  lessonText: lastLessonText
                };
                saveHistory();
              }
            }

            if (m.quizState) {
              activeQuiz = m.quizState;
              lastLessonText = activeQuiz.lessonText || '';

              const isCompleted = activeQuiz.completed;

              if (!calledStartQuiz) {
                const titleMsg = isCompleted
                  ? `<em style="color:var(--gray)"><span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">assignment</span> Reviewing quiz &mdash; <strong>${activeQuiz.questions.length} questions</strong></em>`
                  : `<em style="color:var(--gray)"><span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">assignment</span> Resuming quiz &mdash; <strong>${activeQuiz.questions.length} question${activeQuiz.questions.length !== 1 ? 's' : ''}</strong>&hellip;</em>`;
                appendMessage('ai', titleMsg, false);
              }

              // Reconstruct answered questions dynamically
              for (let i = 0; i < activeQuiz.currentIndex; i++) {
                if (activeQuiz.answers && activeQuiz.answers[i] !== undefined) {
                  reconstructAnsweredQuestion(i);
                }
              }

              if (!isCompleted && !calledStartQuiz) {
                showQuestion();
              }
            } else {
              // Never show raw quiz JSON — show placeholder
              appendMessage('ai', '<em style="color:var(--gray);font-size:0.82rem"><span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">assignment</span> Quiz session — results saved above</em>', false);
            }
          } else if (m.type === 'quiz_summary') {
            appendMessage('ai', m.html || escHtml(m.text).replace(/\n/g, '<br>'), false);
          } else if (m.type === 'quiz_feedback') {
            // Only render native feedback string if quiz reconstruction is unsupported (old sessions)
            if (!activeQuiz) {
              appendMessage('ai', escHtml(m.text), false);
            }
          } else if (m.type === 'error' || (m.html && m.html.includes('Background generation failed'))) {
            // Strip out the HTML span warning icon to get clean text
            let plainText = m.text || '';
            if (!plainText && m.html) {
              plainText = m.html.replace(/<span[^>]*>.*?<\/span>/g, '').trim();
            }
            appendErrorNotice(plainText || 'Generation failed.', false);
          } else if (m.type === 'error_limit') {
            if (source !== 'archive') {
              showLimit(m.text);
              appendActionButtons('error_limit');
            }
          } else {
            const html = m.text ? (m.type === 'lesson' ? formatLesson(m.text) : escHtml(m.text).replace(/\n/g, '<br>')) : (m.html || '');
            if (m.type === 'lesson') lastLesson = m.text;
            appendMessage('ai', html, false);
          }
        }
      });

      // Restore currentMode based on history so new requests use correct mode/cost
      if (messages.length > 0) {
        const lastAiMessage = messages.slice().reverse().find(m => m.role === 'ai');
        if (lastAiMessage) {
          if (lastAiMessage.type === 'quiz') {
            currentMode = 'quiz';
          } else {
            currentMode = 'lesson';
          }
        } else {
          const lastUserMessage = messages.slice().reverse().find(m => m.role === 'user');
          if (lastUserMessage && lastUserMessage.requestedMode) {
            currentMode = lastUserMessage.requestedMode;
          }
        }
      }

      // Update the UI toggle to match the restored mode
      if (currentMode) {
        document.querySelectorAll('.mode-toggle button').forEach(b => b.classList.remove('active'));
        const targetBtn = document.querySelector(`.mode-toggle button[data-mode="${currentMode}"]`);
        if (targetBtn) targetBtn.classList.add('active');
      }

      const isLastError = stored.length > 0 && (
        stored[stored.length - 1].type === 'error' ||
        (stored[stored.length - 1].html && stored[stored.length - 1].html.includes('Background generation failed'))
      );
      const isLastLimit = stored.length > 0 && stored[stored.length - 1].type === 'error_limit';

      if (isLastError && data.item.generation_status !== 'processing') {
        appendActionButtons('error');
      } else if (isLastLimit && data.item.generation_status !== 'processing') {
        retryGeneration();
      } else if (activeQuiz && activeQuiz.completed && data.item.generation_status !== 'processing') {
        appendActionButtons('quiz_done');
      } else if (lastLesson && data.item.generation_status !== 'processing') {
        lastLessonText = lastLesson;
        if (!activeQuiz) {
          appendActionButtons('lesson');
        }
      }

      if (data.item.generation_status === 'processing') {
        appendTyping();
        startPolling(id);
      } else if (data.item.generation_status === 'pending_retry') {
        // Session was archived while AI was still generating, then restored.
        // The original background worker lost its reference, so we need to re-trigger.
        autoRetryRestoredSession(id);
      }

      wrap.scrollTop = wrap.scrollHeight;
    } catch (e) { console.error('openHistory', e); }
  }

  /* ── Pinned sidebar ──────────────────────────────────────── */
  let pinnedOpen = false;
  let pinnedLoaded = false;

  $('pinnedToggle')?.addEventListener('click', () => {
    pinnedOpen = !pinnedOpen;
    const wrap = $('pinnedListSide');
    const chevron = document.querySelector('.chevron-pin');
    if (wrap) wrap.style.display = pinnedOpen ? 'block' : 'none';
    if (chevron) chevron.textContent = pinnedOpen ? 'expand_less' : 'expand_more';
    if (pinnedOpen && !pinnedLoaded) { loadPinned(); pinnedLoaded = true; }
  });

  async function loadPinned() {
    try {
      const res = await apiFetch('GET', 'api.php?action=pin_list');
      const data = await res.json();
      if (!data.ok) return;
      renderPinnedList(data.items);
    } catch { /* silent */ }
  }

  function renderPinnedList(items) {
    const el = $('pinnedItems');
    if (!el) return;
    if (!items || !items.length) {
      el.innerHTML = '<div class="side-empty-note">No pinned sessions.</div>';
      return;
    }
    el.className = '';
    el.innerHTML = items.map(item => `
      <div class="history-item is-pinned" data-id="${item.id}">
        <span class="material-icons hist-type-icon" style="color:#7dd3fc">push_pin</span>
        <div class="hist-title-wrap">
          <span class="hist-title">${escHtml(item.title)}</span>
        </div>
        <button class="hist-dots-btn" title="More options" aria-label="More options">
          <span class="material-icons">more_horiz</span>
        </button>
      </div>
    `).join('');

    el.querySelectorAll('.history-item').forEach(row => {
      const id = +row.dataset.id;
      const titleEl = row.querySelector('.hist-title');
      const dotsBtn = row.querySelector('.hist-dots-btn');

      // Open chat on row click (ignore dots)
      row.addEventListener('click', e => {
        if (e.target.closest('.hist-dots-btn')) return;
        closeCtxMenu();
        openHistory(id, 'pinned');
      });

      // Marquee on hover
      attachMarquee(row);

      // Three-dot context menu — isPinned = true → shows "Unpin"
      dotsBtn.addEventListener('click', e => {
        e.stopPropagation();
        if (ctxTarget && ctxTarget.id === id && histCtxMenu.style.display !== 'none') {
          closeCtxMenu(); return;
        }
        openCtxMenu(dotsBtn, id, 'pinned', titleEl, true);
      });
    });
  }

  /* ── Archive sidebar ─────────────────────────────────────── */
  let archiveOpen = false;
  let archiveLoaded = false;

  $('archiveToggle')?.addEventListener('click', () => {
    archiveOpen = !archiveOpen;
    const wrap = $('archiveListSide');
    const chevron = document.querySelector('.chevron-arch');
    if (wrap) wrap.style.display = archiveOpen ? 'block' : 'none';
    if (chevron) chevron.textContent = archiveOpen ? 'expand_less' : 'expand_more';
    if (archiveOpen && !archiveLoaded) { loadArchive(); archiveLoaded = true; }
  });

  async function loadArchive() {
    try {
      const res = await apiFetch('GET', 'api.php?action=archive_list');
      const data = await res.json();
      if (!data.ok) return;
      renderArchiveList(data.items);
    } catch { /* silent */ }
  }

  function renderArchiveList(items) {
    const el = $('archiveItems');
    if (!el) return;
    if (!items || !items.length) {
      el.innerHTML = '<div class="side-empty-note">No archived sessions.</div>';
      return;
    }
    el.className = '';
    el.innerHTML = items.map(item => `
      <div class="history-item is-archive" data-id="${item.id}" title="${escHtml(item.title)}">
        <span class="material-icons hist-type-icon" style="color:var(--gold)">inventory_2</span>
        <div class="hist-title-wrap">
          <span class="hist-title">${escHtml(item.title)}</span>
        </div>
        <button class="hist-dots-btn" title="More options" aria-label="More options">
          <span class="material-icons">more_horiz</span>
        </button>
      </div>
    `).join('');

    el.querySelectorAll('.history-item').forEach(row => {
      const id = +row.dataset.id;
      const titleEl = row.querySelector('.hist-title');
      const dotsBtn = row.querySelector('.hist-dots-btn');

      // Open chat on row click
      row.addEventListener('click', e => {
        if (e.target.closest('.hist-dots-btn') || e.target.closest('.hist-rename-input')) return;
        closeCtxMenu();
        openHistory(id, 'archive');
      });

      // Marquee on hover
      attachMarquee(row);

      // Three-dot context menu
      dotsBtn.addEventListener('click', e => {
        e.stopPropagation();
        if (ctxTarget && ctxTarget.id === id && histCtxMenu.style.display !== 'none') {
          closeCtxMenu(); return;
        }
        openCtxMenu(dotsBtn, id, 'archive', titleEl, false);
      });
    });
  }

  /* ════════════════════════════════════════════════════════════
     TRASH — Settings view
     ════════════════════════════════════════════════════════════ */
  let pendingTrashDeleteId = null;

  async function loadTrashSettings() {
    const container = $('trashItemsList');
    const badge = $('trashCountBadge');
    if (!container) return;
    container.innerHTML = '<div style="color:var(--gray);font-size:0.82rem">Loading…</div>';
    try {
      const res = await apiFetch('GET', 'api.php?action=trash_list');
      const data = await res.json();
      if (!data.ok) { container.innerHTML = '<div style="color:var(--red);font-size:0.82rem">Could not load trash.</div>'; return; }
      renderTrashSettings(data.items);
    } catch { container.innerHTML = '<div style="color:var(--red);font-size:0.82rem">Error loading trash.</div>'; }
  }

  function renderTrashSettings(items) {
    const container = $('trashItemsList');
    const badge = $('trashCountBadge');
    const emptyBtn = $('emptyTrashBtn');
    if (!container) return;

    if (badge) {
      if (items && items.length > 0) { badge.textContent = items.length; badge.style.display = 'inline-block'; }
      else { badge.style.display = 'none'; }
    }

    if (emptyBtn) {
      if (items && items.length > 0) { emptyBtn.style.display = 'inline-block'; }
      else { emptyBtn.style.display = 'none'; }
    }

    if (!items || !items.length) {
      container.innerHTML = `
        <div style="text-align:center; padding:32px 0; color:var(--gray);">
          <span class="material-icons" style="font-size:42px; opacity:0.4; margin-bottom:8px;">delete_outline</span>
          <h4 style="font-size:1.1rem; color:var(--black); margin:0 0 4px; font-weight:600;">Trash is Empty</h4>
          <p style="font-size:0.85rem; margin:0;">Deleted chats will appear here when moved to Trash.</p>
        </div>`;
      return;
    }

    container.innerHTML = items.map(item => `
      <div class="history-item is-trash" data-id="${item.id}" title="${escHtml(item.title)}">
        <span class="material-icons hist-type-icon" style="color:var(--red)">delete</span>
        <div class="hist-title-wrap">
          <span class="hist-title">${escHtml(item.title)}</span>
        </div>
        <button class="hist-dots-btn" title="More options" aria-label="More options">
          <span class="material-icons">more_horiz</span>
        </button>
      </div>
    `).join('');

    container.querySelectorAll('.history-item').forEach(row => {
      const id = +row.dataset.id;
      const titleEl = row.querySelector('.hist-title');
      const dotsBtn = row.querySelector('.hist-dots-btn');

      // Prevent click from opening in main view
      row.addEventListener('click', e => {
        if (e.target.closest('.hist-dots-btn')) return;
        closeCtxMenu();
      });

      // Three-dot context menu
      dotsBtn.addEventListener('click', e => {
        e.stopPropagation();
        if (ctxTarget && ctxTarget.id === id && histCtxMenu.style.display !== 'none') {
          closeCtxMenu(); return;
        }
        openCtxMenu(dotsBtn, id, 'trash', titleEl, false);
      });
    });
  }

  // Empty Trash modal handlers
  $('emptyTrashBtn')?.addEventListener('click', () => {
    const modal = $('emptyTrashModal');
    if (modal) modal.style.display = 'flex';
  });

  $('cancelEmptyTrash')?.addEventListener('click', () => {
    const modal = $('emptyTrashModal');
    if (modal) modal.style.display = 'none';
  });

  $('confirmEmptyTrash')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('csrf_token', csrfToken());
    try {
      await apiFetch('POST', 'api.php?action=trash_empty', fd);
      loadTrashSettings();
    } catch { /* silent */ }
    const modal = $('emptyTrashModal');
    if (modal) modal.style.display = 'none';
  });

  // Trash permanent-delete modal handlers
  $('cancelTrashDelete')?.addEventListener('click', () => {
    const modal = $('trashPermanentModal');
    if (modal) modal.style.display = 'none';
    pendingTrashDeleteId = null;
  });

  $('confirmTrashDelete')?.addEventListener('click', async () => {
    if (!pendingTrashDeleteId) return;
    const id = pendingTrashDeleteId;
    const fd = new FormData();
    fd.append('csrf_token', csrfToken());
    fd.append('id', id);
    try {
      await apiFetch('POST', 'api.php?action=trash_delete', fd);
      loadTrashSettings();
    } catch { /* silent */ }
    const modal = $('trashPermanentModal');
    if (modal) modal.style.display = 'none';
    pendingTrashDeleteId = null;
  });

  /* ── Delete / Archive modals ─────────────────────────────── */
  let pendingDeleteId = null;
  let pendingArchiveId = null;
  const deleteModal = $('deleteModal');
  const archiveModal = $('archiveModal');

  function askDeleteHistory(id) { pendingDeleteId = id; if (deleteModal) deleteModal.style.display = 'flex'; }
  function askArchive(id) { pendingArchiveId = id; if (archiveModal) archiveModal.style.display = 'flex'; }

  $('cancelDelete')?.addEventListener('click', () => { if (deleteModal) deleteModal.style.display = 'none'; pendingDeleteId = null; });

  $('confirmDelete')?.addEventListener('click', async () => {
    if (!pendingDeleteId) return;
    const deletingId = pendingDeleteId;
    const fd = new FormData();
    fd.append('csrf_token', csrfToken());
    fd.append('id', deletingId);
    try {
      // history_delete now soft-deletes to Trash in api.php
      const res = await apiFetch('POST', 'api.php?action=history_delete', fd);
      let result = { ok: false };
      try { result = await res.json(); } catch { /* parse error */ }

      if (res.ok && result.ok) {
        // Force reload both lists regardless of their open state
        historyLoaded = false; pinnedLoaded = false;
        // Always reload history to reflect the deletion
        loadHistory();
        if (pinnedOpen) loadPinned();

        if (views.settings && views.settings.style.display !== 'none') {
          loadTrashSettings();
        }

        // BUG FIX #13: If the deleted session is the one the user is currently viewing,
        // clear the chat and navigate home so no invalid state is shown.
        if (currentHistoryId === deletingId) {
          resetChat();
        }
      } else {
        console.error('Delete failed:', result.message || 'Unknown error');
        // If CSRF expired, refresh the token and retry
        if (res.status === 419) {
          alert('Security token expired. Please refresh the page and try again.');
        }
      }
    } catch (err) {
      console.error('Delete error:', err);
    }
    if (deleteModal) deleteModal.style.display = 'none';
    pendingDeleteId = null;
  });

  $('cancelArchive')?.addEventListener('click', () => { if (archiveModal) archiveModal.style.display = 'none'; pendingArchiveId = null; });
  $('confirmArchive')?.addEventListener('click', async () => {
    if (!pendingArchiveId) return;
    const archivingId = pendingArchiveId;
    const fd = new FormData();
    fd.append('csrf_token', csrfToken());
    fd.append('id', archivingId);
    try {
      const res = await apiFetch('POST', 'api.php?action=archive_add', fd);
      const result = await res.json().catch(() => ({}));

      if (res.ok && result.ok) {
        historyLoaded = false; pinnedLoaded = false; archiveLoaded = false;
        if (historyOpen) loadHistory();
        if (pinnedOpen) loadPinned();
        if (archiveOpen) loadArchive();

        // If the archived session was the one currently open, navigate home
        if (currentHistoryId == archivingId) {
          resetChat();
        }
      } else {
        throw new Error(result.message || 'Could not archive session.');
      }
    } catch (err) {
      showToast(err.message || 'Could not archive session.', 'error');
    }
    if (archiveModal) archiveModal.style.display = 'none';
    pendingArchiveId = null;
  });

  /* ── Stats ───────────────────────────────────────────────── */
  async function loadStats() {
    try {
      const res = await apiFetch('GET', 'api.php?action=stats');
      const data = await res.json();
      if (!data.ok) return;
      renderStats(data.stats);
    } catch { /* silent */ }
  }

  function renderStats(s) {
    const grid = $('statsGrid');
    if (!grid) return;
    const cards = [
      { icon: 'trending_up', label: 'Progress', val: s.progress + '%' },
      { icon: 'auto_stories', label: 'Lessons Studied', val: s.lessons },
      { icon: 'assignment_turned_in', label: 'Quizzes Taken', val: s.quizzes },
      { icon: 'score', label: 'Average Score', val: s.average + '%' },
      { icon: 'bolt', label: 'AI Usage (hourly)', val: s.hourly + '/' + s.hourlyLimit },
      { icon: 'calendar_today', label: 'AI Usage (daily)', val: s.daily + '/' + s.dailyLimit },
    ];
    grid.innerHTML = cards.map(c => `
      <article style="background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 12px rgba(26,31,54,.07);display:flex;flex-direction:column;gap:6px">
        <span class="material-icons" style="color:var(--blue);font-size:28px">${c.icon}</span>
        <strong style="font-size:1.4rem;font-weight:800;color:var(--navy)">${c.val}</strong>
        <small style="font-size:0.75rem;color:var(--gray);font-weight:500">${c.label}</small>
      </article>
    `).join('');
  }

  /* ── Mode selection ──────────────────────────────────────── */
  let currentMode = null;

  document.querySelectorAll('.mode-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      currentMode = btn.dataset.mode;
      document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active-mode'));
      btn.classList.add('active-mode');
      const notice = $('modeNotice');
      if (notice) notice.style.display = 'none';
      const ta = $('topicInput');
      if (ta) ta.placeholder = currentMode === 'quiz'
        ? 'Describe your topic for the quiz / paste notes / upload file or image…'
        : 'Describe your topic / paste notes / upload file or image…';
    });
  });

  /* ── File / Image upload ─────────────────────────────────── */
  let pendingFile = null;
  let pendingImageDataUrl = null;

  const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

  function isPendingImage() {
    if (!pendingFile) return false;
    return IMAGE_EXTS.includes(pendingFile.name.split('.').pop().toLowerCase());
  }

  function getActiveBar() {
    const isChat = chatArea && chatArea.style.display !== 'none';
    return isChat ? $('promptBarChat') : $('promptBarEmpty');
  }

  function bindUpload(btnId, inputId) {
    const btn = $(btnId);
    const input = $(inputId);
    if (!btn || !input) return;
    btn.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
      if (input.files[0]) {
        pendingFile = input.files[0];
        const ext = pendingFile.name.split('.').pop().toLowerCase();
        if (IMAGE_EXTS.includes(ext)) {
          const reader = new FileReader();
          reader.onload = e => {
            pendingImageDataUrl = e.target.result;
            showFileInChatbox(pendingFile.name, pendingFile);
          };
          reader.readAsDataURL(pendingFile);
        } else {
          pendingImageDataUrl = null;
          showFileInChatbox(pendingFile.name, null);
        }
      }
      input.value = '';
    });
  }

  bindUpload('uploadBtnEmpty', 'fileInput');
  bindUpload('uploadBtnChat', 'fileInput');

  function showFileInChatbox(name, file) {
    document.querySelectorAll('.chatbox-file-preview').forEach(b => b.remove());
    document.querySelectorAll('.cfp-container').forEach(c => {
      const parentBar = c.closest('.prompt-bar');
      if (parentBar) { parentBar.style.flexWrap = ''; parentBar.style.borderRadius = ''; parentBar.style.alignItems = ''; parentBar.style.paddingTop = ''; }
      c.remove();
    });

    const ext = name.split('.').pop().toLowerCase();
    const isImage = IMAGE_EXTS.includes(ext);
    const preview = document.createElement('div');
    preview.className = 'chatbox-file-preview';

    if (isImage && pendingImageDataUrl) {
      preview.innerHTML = `
        <div class="cfp-img-thumb"><img src="${pendingImageDataUrl}" alt="${escHtml(name)}"></div>
        <span class="cfp-name">${escHtml(name)}</span>
        <button class="cfp-remove" onclick="window.clearPendingFile()" title="Remove">
          <span class="material-icons">close</span>
        </button>`;
    } else {
      const icon = ext === 'pdf' ? 'picture_as_pdf' : ext === 'docx' ? 'description' : 'text_snippet';
      const iconColor = ext === 'pdf' ? '#e74c3c' : '#2058dc';
      preview.innerHTML = `
        <div class="cfp-icon" style="background:${iconColor}">
          <span class="material-icons" style="font-size:18px;color:#fff">${icon}</span>
        </div>
        <span class="cfp-name">${escHtml(name)}</span>
        <button class="cfp-remove" onclick="window.clearPendingFile()" title="Remove">
          <span class="material-icons">close</span>
        </button>`;
    }

    const bar = getActiveBar();
    if (bar) {
      const container = document.createElement('div');
      container.className = 'cfp-container';
      bar.insertBefore(container, bar.firstChild);
      bar.style.flexWrap = 'wrap'; bar.style.borderRadius = '18px'; bar.style.alignItems = 'flex-start'; bar.style.paddingTop = '12px';
      container.appendChild(preview);
    }
  }

  window.clearPendingFile = function () {
    pendingFile = null;
    pendingImageDataUrl = null;
    // BUG FIX: clear preview from both prompt bars
    document.querySelectorAll('.chatbox-file-preview').forEach(b => b.remove());
    document.querySelectorAll('.cfp-container').forEach(c => {
      const parentBar = c.closest('.prompt-bar');
      if (parentBar) { parentBar.style.flexWrap = ''; parentBar.style.borderRadius = ''; parentBar.style.alignItems = ''; parentBar.style.paddingTop = ''; }
      c.remove();
    });
  };

  /* ── Chat state ──────────────────────────────────────────── */
  let messages = [];
  let lastLessonText = '';
  let activeQuiz = null;

  const emptyState = $('emptyState');
  const chatArea = $('chatArea');

  function switchToChat() {
    if (emptyState) emptyState.style.display = 'none';
    if (chatArea) chatArea.style.display = 'flex';
  }

  /* ── Send triggers ───────────────────────────────────────── */
  $('sendBtnEmpty')?.addEventListener('click', () => sendMessage());
  $('topicInput')?.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } });
  $('chatSendBtn')?.addEventListener('click', () => sendMessage());
  $('chatInput')?.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } });

  /* ── New Task ────────────────────────────────────────────── */
  $('newTaskBtn')?.addEventListener('click', () => {
    resetChat();
  });

  function resetChat() {
    messages.length = 0;
    activeQuiz = null;
    pendingFile = null;
    pendingImageDataUrl = null;
    currentMode = null;
    lastLessonText = '';
    currentHistoryId = null;   // clear active session tracking
    currentHistorySource = null;
    localStorage.removeItem('activeHistoryId');
    const wrap = $('messagesWrap');
    if (wrap) wrap.innerHTML = '';
    if (emptyState) emptyState.style.display = 'flex';
    if (chatArea) chatArea.style.display = 'none';
    const chatPromptWrap = $('chatPromptWrap');
    const archivedNotice = $('archivedNotice');
    if (chatPromptWrap) chatPromptWrap.style.display = 'block';
    if (archivedNotice) archivedNotice.style.display = 'none';
    const ti = $('topicInput');
    if (ti) { ti.value = ''; ti.style.height = 'auto'; ti.placeholder = 'Describe your topic / paste notes / upload file or image…'; }
    const ci = $('chatInput');
    if (ci) { ci.value = ''; ci.style.height = 'auto'; }
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active-mode'));
    const notice = $('modeNotice');
    if (notice) notice.style.display = 'none';
    document.querySelectorAll('.chatbox-file-preview, .cfp-container').forEach(b => b.remove());
    [$('promptBarEmpty'), $('promptBarChat')].forEach(bar => {
      if (bar) { bar.style.flexWrap = ''; bar.style.borderRadius = ''; bar.style.alignItems = ''; bar.style.paddingTop = ''; }
    });
    const lw = $('limitWarn');
    if (lw) lw.style.display = 'none';
    showView('home');
  }

  /* ── Main send ───────────────────────────────────────────── */
  let lastFailedPayload = null;

  async function sendMessage() {
    const emptyTA = $('topicInput');
    const chatTA = $('chatInput');
    const isChat = (chatArea && chatArea.style.display !== 'none');
    const ta = isChat ? chatTA : emptyTA;
    const topic = ta ? ta.value.trim() : '';

    if (!topic && !pendingFile) {
      if (!isChat) {
        const notice = $('modeNotice');
        if (notice) { notice.innerHTML = '<span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">arrow_upward</span> Please type a topic or upload a file/image first.'; notice.style.display = 'block'; }
      }
      return;
    }

    // Mode guard
    if (!currentMode && messages.length === 0) {
      const notice = $('modeNotice');
      if (notice) { notice.innerHTML = '<span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">arrow_upward</span> Please choose "Start a lesson" or "Start quiz" first.'; notice.style.display = 'block'; }
      document.querySelectorAll('.mode-btn').forEach(b => { b.classList.add('mode-required-shake'); b.addEventListener('animationend', () => b.classList.remove('mode-required-shake'), { once: true }); });
      return;
    }
    if (!currentMode) currentMode = 'lesson';

    // Quiz persistence: if an active (incomplete) quiz exists and user sends a new message,
    // save the quiz state into messages before continuing.
    if (activeQuiz && !activeQuiz.completed) {
      activeQuiz.completed = true; // Mark as interrupted so it won't be re-shown
    }

    // Clean up file preview from bar
    document.querySelectorAll('.cfp-container').forEach(c => {
      const parentBar = c.closest('.prompt-bar');
      if (parentBar) { parentBar.style.flexWrap = ''; parentBar.style.borderRadius = ''; parentBar.style.alignItems = ''; parentBar.style.paddingTop = ''; }
      c.remove();
    });

    switchToChat();

    // BUG FIX: capture file reference before clearing pendingFile
    const fileForRequest = pendingFile;
    const imageDataForMsg = pendingImageDataUrl;

    // Determine mode FIRST
    const isQuizFromLesson = lastLessonText !== '' && (/quiz/i.test(topic) || /test me/i.test(topic) || /questions/i.test(topic));
    const isQuizRequest = currentMode === 'quiz' || /\bquiz\b/i.test(topic) || /\btest me\b/i.test(topic) || /\bquestions?\b/i.test(topic);
    const mode = isQuizRequest ? 'quiz' : currentMode;

    // Build user message
    const userMsgData = { role: 'user', text: topic, requestedMode: mode };

    if (fileForRequest) {
      userMsgData.fileName = fileForRequest.name;
      if (isPendingImage() && imageDataForMsg) {
        userMsgData.imageDataUrl = imageDataForMsg;
        appendImageChipMsg(fileForRequest.name, imageDataForMsg);
      } else {
        appendFileChipMsg(fileForRequest.name);
      }
    }
    if (topic) appendMessage('user', escHtml(topic));
    messages.push(userMsgData);

    // Save immediately so it appears in recent history BEFORE generating
    if (!currentHistoryId) {
      await saveHistory();
    }

    if (ta) { ta.value = ''; ta.style.height = 'auto'; }
    pendingFile = null;
    pendingImageDataUrl = null;

    // If quiz is active and user typed A/B/C/D, handle as answer
    if (activeQuiz && !activeQuiz.completed && topic) {
      const lower = topic.toLowerCase().trim();
      const idx = ['a', 'b', 'c', 'd'].indexOf(lower);
      if (idx !== -1) { handleQuizAnswer(idx); return; }
    }

    let effectiveTopic = topic;
    if (!effectiveTopic && fileForRequest) {
      effectiveTopic = fileForRequest.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').trim();
    }
    if (isQuizFromLesson && mode === 'quiz' && !topic.includes('\n')) {
      effectiveTopic = lastLessonText + (topic ? '\n\nUser request: ' + topic : '');
    }

    const fd = buildFormData(effectiveTopic, mode, fileForRequest);

    // Add persistent session data
    const cleanMessages = messages.map(m => {
      if (m.imageDataUrl) { const { imageDataUrl, ...rest } = m; return rest; }
      return m;
    });
    const firstUser = messages.find(m => m.role === 'user');
    const titleText = (firstUser?.text || firstUser?.fileName || 'Study Session').substring(0, 80);
    fd.append('history_id', currentHistoryId || 0);
    fd.append('title', titleText);
    fd.append('messages', JSON.stringify(cleanMessages));

    lastFailedPayload = { effectiveTopic, mode, fileForRequest };

    const typingId = appendTyping();
    const activeChatAtRequestTime = currentHistoryId;

    if (!navigator.onLine) {
      removeMessage(typingId);
      appendOfflineErrorNotice();
      pendingOfflineRetry = window.retryGeneration;
      return;
    }

    try {
      let res;
      try {
        res = await apiFetch('POST', 'api.php?action=ai', fd);
      } catch (err) {
        clearTimeout(timer);
        removeMessage(typingId);
        if (!navigator.onLine) {
          appendOfflineErrorNotice();
          pendingOfflineRetry = window.retryGeneration;
        } else if (err.name === 'AbortError') {
          appendErrorNotice('Gemini took too long.');
        } else {
          appendErrorNotice('Network error. Please check your connection.');
        }
        return;
      }

      let data;
      try { const raw = await res.text(); data = JSON.parse(raw); }
      catch { removeMessage(typingId); appendErrorNotice('The server returned an unexpected response.'); return; }
      if (!data.ok) {
        removeMessage(typingId);
        if (data.limited) {
          showLimit(data.message, data.resetAt);
          appendActionButtons('error_limit');
          messages.push({ role: 'ai', type: 'error_limit', text: data.message });
          saveHistory();
          localStorage.setItem('activeHistoryId', currentHistoryId);
        }
        else appendErrorNotice(data.message || 'Something went wrong.');
        return;
      }

      if (data.history_id) {
        if (currentHistoryId === activeChatAtRequestTime) {
          const isNew = (currentHistoryId !== data.history_id);
          currentHistoryId = data.history_id;
          currentHistorySource = 'history';
          localStorage.setItem('activeHistoryId', currentHistoryId);
          if (isNew) forceUpdateHistorySidebar();
        }
      }

      if (data.status === 'processing') {
        startPolling(data.history_id);
      } else {
        removeMessage(typingId);
        const response = data.response;
        if (mode === 'quiz') {
          const quizObj = startQuiz(response);
          messages.push({ role: 'ai', text: response, type: 'quiz', quizState: quizObj });
        } else {
          lastLessonText = response;
          appendMessage('ai', formatLesson(response));
          messages.push({ role: 'ai', text: response, type: 'lesson' });
          appendActionButtons('lesson');
          saveHistory();
          historyLoaded = false;
          if (historyOpen) loadHistory();
        }
      }

    } catch (err) {
      removeMessage(typingId);
      appendErrorNotice(err.message || 'Something went wrong.');
    }
  }

  /* ── Build FormData ──────────────────────────────────────── */
  function buildFormData(topic, mode, file = null) {
    const fd = new FormData();
    fd.append('csrf_token', csrfToken());
    fd.append('topic', topic);
    fd.append('mode', mode);
    if (file) fd.append('file', file);
    return fd;
  }

  /* ── Retry last ──────────────────────────────────────────── */
  window.retryLast = function () {
    if (!lastFailedPayload) {
      // BUG FIX: pick the correct textarea based on which view is active
      const last = messages.filter(m => m.role === 'user').pop();
      if (!last) return;
      const isChat = (chatArea && chatArea.style.display !== 'none');
      const ta = isChat ? $('chatInput') : $('topicInput');
      if (ta) { ta.value = last.text || ''; autoResize(ta); }
    }
    sendMessage();
  };

  /* ── Error notice ────────────────────────────────────────── */
  function appendErrorNotice(msg, showRetry = true) {
    const wrap = $('messagesWrap');
    if (!wrap) return;

    // Remove any existing error pill at the end
    const existing = wrap.lastElementChild;
    if (existing && existing.classList.contains('error-notice-row')) {
      existing.remove();
    }

    const row = document.createElement('div');
    row.className = 'error-notice-row';
    row.id = 'genericErrorRow';

    let html = `
      <div class="error-pill">
        <span class="material-icons" style="font-size:18px;">warning</span>
        <span>${msg}</span>
    `;
    if (showRetry) {
      html += `
        <button class="retry-inline-btn" onclick="window.retryGeneration()">
          <span class="material-icons" style="font-size:16px;">refresh</span> Retry
        </button>
      `;
    }
    html += `</div>`;

    row.innerHTML = html;
    wrap.appendChild(row);
    wrap.scrollTop = wrap.scrollHeight;
  }

  function appendOfflineErrorNotice() {
    const wrap = $('messagesWrap');
    if (!wrap) return;

    // Remove any existing offline notice specifically to avoid duplicates
    const oldNotice = document.getElementById('offlineNoticeRow');
    if (oldNotice) oldNotice.remove();
    
    // Also remove any existing generic error at the end to keep UI clean
    const existing = wrap.lastElementChild;
    if (existing && existing.classList.contains('error-notice-row')) {
      existing.remove();
    }

    const row = document.createElement('div');
    row.className = 'error-notice-row';
    row.id = 'offlineNoticeRow';

    let html = `
      <div class="error-pill">
        <span class="material-icons" style="font-size:18px;">wifi_off</span>
        <span>no internet connection, please return again if connection is available</span>
      </div>
    `;
    row.innerHTML = html;
    wrap.appendChild(row);
    wrap.scrollTop = wrap.scrollHeight;
  }

  /* ── Action buttons ──────────────────────────────────────── */
  function appendActionButtons(context) {
    if (currentHistorySource === 'archive') return;
    const wrap = $('messagesWrap');
    if (!wrap) return;
    // Remove any existing action rows to avoid duplicates
    wrap.querySelectorAll('.post-action-row').forEach(r => r.remove());
    // Also remove error_limit action rows placed outside messagesWrap (near chatbox)
    const chatArea = wrap.closest('.chat-area');
    if (chatArea) chatArea.querySelectorAll(':scope > .post-action-row').forEach(r => r.remove());

    const row = document.createElement('div');
    row.className = 'post-action-row';

    if (context === 'lesson') {
      row.innerHTML = `
        <span style="font-size:0.8rem;color:var(--gray);font-weight:500">What would you like to do next?</span>
        <button class="action-pill ripple" id="btnQuiz">
          <span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">assignment_turned_in</span> Quiz
        </button>
        <button class="action-pill ripple secondary" id="btnMoreLesson">
          <span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">auto_stories</span> More Lesson
        </button>`;
      wrap.appendChild(row);
      wrap.scrollTop = wrap.scrollHeight;
      [row.querySelector('#btnQuiz'), row.querySelector('#btnMoreLesson')].forEach(b => b && attachRipple(b));

      row.querySelector('#btnQuiz')?.addEventListener('click', () => {
        row.remove();
        currentMode = 'quiz';
        appendMessage('user', '<span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">assignment</span> Generate a 20-question quiz based on this lesson.');
        messages.push({ role: 'user', text: 'Generate a 20-question quiz based on this lesson.', requestedMode: 'quiz' });
        triggerQuizFromLesson();
      });
      row.querySelector('#btnMoreLesson')?.addEventListener('click', () => {
        row.remove();
        currentMode = 'lesson';
        appendMessage('user', '<span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">auto_stories</span> Give me a simpler explanation with more examples.');
        messages.push({ role: 'user', text: 'Give me a simpler explanation with more examples.', requestedMode: 'lesson' });
        triggerMoreLesson();
      });

    } else if (context === 'error') {
      row.innerHTML = `
        <span style="font-size:0.8rem;color:var(--gray);font-weight:500">No Connection</span>
        <button class="action-pill ripple" id="btnRetry">
          <span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">refresh</span> Retry
        </button>`;
      wrap.appendChild(row);
      wrap.scrollTop = wrap.scrollHeight;
      attachRipple(row.querySelector('#btnRetry'));

      row.querySelector('#btnRetry')?.addEventListener('click', () => {
        row.remove();
        retryGeneration();
      });

    } else if (context === 'error_limit') {
      row.innerHTML = `
        <span style="font-size:0.8rem;color:var(--gray);font-weight:500">Generation paused due to usage limit.</span>
        <button class="action-pill ripple" id="btnReloadLimit">
          <span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">refresh</span> Reload Page
        </button>
        <button class="action-pill ripple secondary" id="btnNewTaskLimit">
          <span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">add</span> New Task
        </button>`;
      // Place above chatbox: insert after limitWarn, before chatPromptWrap
      const limitWarnEl = $('limitWarn');
      if (limitWarnEl && limitWarnEl.parentNode) {
        row.style.margin = '0 20px 12px';
        limitWarnEl.parentNode.insertBefore(row, limitWarnEl.nextSibling);
      } else {
        wrap.appendChild(row);
      }
      attachRipple(row.querySelector('#btnReloadLimit'));
      attachRipple(row.querySelector('#btnNewTaskLimit'));

      row.querySelector('#btnReloadLimit')?.addEventListener('click', () => {
        location.reload();
      });
      row.querySelector('#btnNewTaskLimit')?.addEventListener('click', () => {
        resetChat();
      });

    } else if (context === 'quiz_done') {
      row.innerHTML = `
        <span style="font-size:0.8rem;color:var(--gray);font-weight:500">Quiz complete! What's next?</span>
        <button class="action-pill ripple" id="btnQuizAgain">
          <span class="material-icons" style="font-size:16px">replay</span>  Quiz Again
        </button>
        <button class="action-pill ripple secondary" id="btnMoreLesson2">
          <span class="material-icons" style="font-size:16px">auto_stories</span>  More Lesson
        </button>`;
      wrap.appendChild(row);
      wrap.scrollTop = wrap.scrollHeight;
      [row.querySelector('#btnQuizAgain'), row.querySelector('#btnMoreLesson2')].forEach(b => b && attachRipple(b));

      row.querySelector('#btnQuizAgain')?.addEventListener('click', () => {
        row.remove();
        appendMessage('user', '<span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">assignment</span> Take the quiz again.');
        messages.push({ role: 'user', text: 'Take the quiz again.', requestedMode: 'quiz' });
        triggerQuizFromLesson();
      });
      row.querySelector('#btnMoreLesson2')?.addEventListener('click', () => {
        row.remove();
        currentMode = 'lesson';
        appendMessage('user', '<span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">auto_stories</span> Give me a simpler explanation with more examples.');
        messages.push({ role: 'user', text: 'Give me a simpler explanation with more examples.' });
        triggerMoreLesson();
      });
    }
  }

  /* ── Retry Generation ────────────────────────────────────── */
  window.retryGeneration = async function retryGeneration() {
    // Remove the error message from the frontend state (supports legacy errors too)
    messages = messages.filter(m => m.type !== 'error' && m.type !== 'error_limit' && !(m.html && m.html.includes('Background generation failed')));

    // Clean up DOM: Remove specific error rows
    const genericErr = document.getElementById('genericErrorRow');
    if (genericErr) genericErr.remove();
    const offlineNotice = document.getElementById('offlineNoticeRow');
    if (offlineNotice) offlineNotice.remove();
    
    // Legacy cleanup
    const errNodes = document.querySelectorAll('.msg.ai, .error-notice-row');
    errNodes.forEach(node => {
      if (node.innerHTML.includes('warning') && (node.innerHTML.includes('failed') || node.innerHTML.includes('Failed') || node.innerHTML.includes('internet connection'))) {
        node.remove();
      }
    });

    const limitWarn = document.getElementById('limitWarn');
    if (limitWarn) limitWarn.remove();
    document.querySelectorAll('.post-action-row').forEach(r => r.remove());

    // Find the last user message to use as the prompt/title
    const lastUserMsg = messages.slice().reverse().find(m => m.role === 'user');
    let prompt = lastUserMsg ? (lastUserMsg.text || '') : '';
    let fileForRetry = null;
    
    if (lastFailedPayload) {
      prompt = lastFailedPayload.effectiveTopic;
      fileForRetry = lastFailedPayload.fileForRequest;
    } else if (!prompt) {
      prompt = 'Retry generation';
    }

    const isQuizRequest = currentMode === 'quiz' || /\bquiz\b/i.test(prompt) || /\btest me\b/i.test(prompt) || /\bquestions?\b/i.test(prompt);
    const mode = isQuizRequest ? 'quiz' : (currentMode || 'lesson');
    const isQuizFromLesson = lastLessonText !== '' && mode === 'quiz';

    try { await saveHistory(); } catch(e) {} // Safely overwrite DB without blocking

    const typingId = appendTyping();
    const activeChatAtRequestTime = currentHistoryId;

    if (!navigator.onLine) {
      removeMessage(typingId);
      appendOfflineErrorNotice();
      pendingOfflineRetry = window.retryGeneration;
      return;
    }

    try {
      // Pass the prompt for the topic so api.php doesn't throw an error.
      let effectivePrompt = prompt;
      if (isQuizFromLesson && !prompt.includes('\n')) {
        effectivePrompt = lastLessonText + (prompt ? '\n\nUser request: ' + prompt : '');
      } else if (mode === 'lesson' && lastLessonText !== '' && prompt === 'Give me a simpler explanation with more examples.') {
        effectivePrompt = lastLessonText + '\n\n---\nUser request: Please provide a more in-depth yet simpler explanation. Add more examples, analogies, and break down complex concepts further. Target a complete beginner.';
      }

      const fd = buildFormData(effectivePrompt, mode, fileForRetry);

      const cleanMessages = messages.map(m => {
        if (m.imageDataUrl) { const { imageDataUrl, ...rest } = m; return rest; }
        return m;
      });
      const titleText = (lastUserMsg?.text || lastUserMsg?.fileName || 'Study Session').substring(0, 80);
      fd.append('history_id', currentHistoryId || 0);
      fd.append('title', titleText);
      fd.append('messages', JSON.stringify(cleanMessages));

      const res = await apiFetch('POST', 'api.php?action=ai', fd);
      let data;
      try { data = JSON.parse(await res.text()); }
      catch { removeMessage(typingId); appendErrorNotice('Unexpected response. Please try again.'); return; }

      if (!data.ok) {
        removeMessage(typingId);
        if (data.limited) { showLimit(data.message, data.resetAt); appendActionButtons('error_limit'); }
        else appendErrorNotice(data.message || 'Something went wrong.');
        return;
      }

      if (data.status === 'processing') {
        startPolling(data.history_id);
      }
    } catch (err) {
      removeMessage(typingId);
      if (!navigator.onLine) {
        appendOfflineErrorNotice();
        pendingOfflineRetry = window.retryGeneration;
      } else {
        appendErrorNotice(err.message || 'Could not retry generation.');
      }
    }
  }

  /* ── Auto-retry for restored sessions (archived mid-generation) ── */
  async function autoRetryRestoredSession(historyId) {
    // Find the last user message to determine what to re-generate
    const lastUserMsg = messages.slice().reverse().find(m => m.role === 'user');
    if (!lastUserMsg) return; // Nothing to retry

    const prompt = lastUserMsg.text || '';
    const isQuizRequest = currentMode === 'quiz' || /\bquiz\b/i.test(prompt) || /\btest me\b/i.test(prompt) || /\bquestions?\b/i.test(prompt);
    const mode = isQuizRequest ? 'quiz' : (currentMode || 'lesson');

    const isQuizFromLesson = lastLessonText !== '' && mode === 'quiz';
    let effectivePrompt = prompt;
    if (isQuizFromLesson && !prompt.includes('\n')) {
      effectivePrompt = lastLessonText + (prompt ? '\n\nUser request: ' + prompt : '');
    } else if (mode === 'lesson' && lastLessonText !== '' && prompt === 'Give me a simpler explanation with more examples.') {
      effectivePrompt = lastLessonText + '\n\n---\nUser request: Please provide a more in-depth yet simpler explanation. Add more examples, analogies, and break down complex concepts further. Target a complete beginner.';
    }

    const typingId = appendTyping();

    try {
      const fd = buildFormData(effectivePrompt, mode, null);

      const cleanMessages = messages.map(m => {
        if (m.imageDataUrl) { const { imageDataUrl, ...rest } = m; return rest; }
        return m;
      });
      const titleText = (lastUserMsg.text || lastUserMsg.fileName || 'Study Session').substring(0, 80);
      fd.append('history_id', historyId);
      fd.append('title', titleText);
      fd.append('messages', JSON.stringify(cleanMessages));

      const res = await apiFetch('POST', 'api.php?action=ai', fd);
      let data;
      try { data = JSON.parse(await res.text()); }
      catch { removeMessage(typingId); appendErrorNotice('Unexpected response. Please try again.'); return; }

      if (!data.ok) {
        removeMessage(typingId);
        if (data.limited) { showLimit(data.message, data.resetAt); appendActionButtons('error_limit'); }
        else appendErrorNotice(data.message || 'Something went wrong.');
        return;
      }

      if (data.history_id) {
        currentHistoryId = data.history_id;
        currentHistorySource = 'history';
        localStorage.setItem('activeHistoryId', currentHistoryId);
      }

      if (data.status === 'processing') {
        startPolling(data.history_id);
      }
    } catch (err) {
      removeMessage(typingId);
      if (!navigator.onLine) {
        appendOfflineErrorNotice();
        pendingOfflineRetry = () => autoRetryRestoredSession(historyId);
      } else {
        appendErrorNotice(err.message || 'Could not resume generation.');
      }
    }
  }

  /* ── Quiz from lesson ────────────────────────────────────── */
  async function triggerQuizFromLesson() {
    if (!lastLessonText) {
      // Fallback: use the first user message as the topic for quiz generation
      const firstUser = messages.find(m => m.role === 'user');
      if (firstUser && firstUser.text) {
        lastLessonText = firstUser.text;
      } else {
        const ci = $('chatInput');
        if (ci) { ci.value = 'Create a 20-question quiz on this topic'; ci.focus(); }
        return;
      }
    }
    // Save the "Generate quiz" message to history
    await saveHistory();

    const typingId = appendTyping();
    const activeChatAtRequestTime = currentHistoryId;
    try {
      const fd = buildFormData(lastLessonText, 'quiz', null);

      const cleanMessages = messages.map(m => {
        if (m.imageDataUrl) { const { imageDataUrl, ...rest } = m; return rest; }
        return m;
      });
      const firstUser = messages.find(m => m.role === 'user');
      const titleText = (firstUser?.text || firstUser?.fileName || 'Study Session').substring(0, 80);
      fd.append('history_id', currentHistoryId || 0);
      fd.append('title', titleText);
      fd.append('messages', JSON.stringify(cleanMessages));

      const res = await apiFetch('POST', 'api.php?action=ai', fd);
      let data;
      try { data = JSON.parse(await res.text()); }
      catch { removeMessage(typingId); appendErrorNotice('Unexpected response. Please try again.'); return; }
      if (!data.ok) {
        removeMessage(typingId);
        if (data.limited) { showLimit(data.message, data.resetAt); appendActionButtons('error_limit'); }
        else appendErrorNotice(data.message || 'Something went wrong.');
        return;
      }

      if (data.history_id) {
        if (currentHistoryId === activeChatAtRequestTime) {
          const isNew = (currentHistoryId !== data.history_id);
          currentHistoryId = data.history_id;
          currentHistorySource = 'history';
          localStorage.setItem('activeHistoryId', currentHistoryId);
          if (isNew) forceUpdateHistorySidebar();
        }
      }

      if (data.status === 'processing') {
        startPolling(data.history_id);
      } else {
        removeMessage(typingId);
        const quizObj = startQuiz(data.response);
        messages.push({ role: 'ai', text: data.response, type: 'quiz', quizState: quizObj });
        saveHistory();
      }
    } catch (err) {
      removeMessage(typingId);
      if (!navigator.onLine) {
        appendOfflineErrorNotice();
        pendingOfflineRetry = triggerQuizFromLesson;
      } else {
        appendErrorNotice(err.message || 'Could not generate quiz.');
      }
    }
  }

  /* ── More lesson ─────────────────────────────────────────── */
  async function triggerMoreLesson() {
    if (!lastLessonText) {
      // Fallback: use the first user message as the topic for lesson generation
      const firstUser = messages.find(m => m.role === 'user');
      if (firstUser && firstUser.text) {
        lastLessonText = firstUser.text;
      } else {
        const ci = $('chatInput');
        if (ci) { ci.value = 'Explain this topic with simpler language and more examples'; ci.focus(); }
        return;
      }
    }
    // Save the "More lesson" message to history
    await saveHistory();

    const typingId = appendTyping();
    const activeChatAtRequestTime = currentHistoryId;
    try {
      const prompt = lastLessonText + '\n\n---\nUser request: Please provide a more in-depth yet simpler explanation. Add more examples, analogies, and break down complex concepts further. Target a complete beginner.';
      const fd = buildFormData(prompt, 'lesson', null);

      const cleanMessages = messages.map(m => {
        if (m.imageDataUrl) { const { imageDataUrl, ...rest } = m; return rest; }
        return m;
      });
      const firstUser = messages.find(m => m.role === 'user');
      const titleText = (firstUser?.text || firstUser?.fileName || 'Study Session').substring(0, 80);
      fd.append('history_id', currentHistoryId || 0);
      fd.append('title', titleText);
      fd.append('messages', JSON.stringify(cleanMessages));

      const res = await apiFetch('POST', 'api.php?action=ai', fd);
      let data;
      try { data = JSON.parse(await res.text()); }
      catch { removeMessage(typingId); appendErrorNotice('Unexpected response. Please try again.'); return; }
      if (!data.ok) {
        removeMessage(typingId);
        if (data.limited) { showLimit(data.message, data.resetAt); appendActionButtons('error_limit'); }
        else appendErrorNotice(data.message || 'Something went wrong.', false);
        return;
      }

      if (data.history_id) {
        if (currentHistoryId === activeChatAtRequestTime) {
          const isNew = (currentHistoryId !== data.history_id);
          currentHistoryId = data.history_id;
          currentHistorySource = 'history';
          localStorage.setItem('activeHistoryId', currentHistoryId);
          if (isNew) forceUpdateHistorySidebar();
        }
      }

      if (data.status === 'processing') {
        startPolling(data.history_id);
      } else {
        removeMessage(typingId);
        lastLessonText = data.response;
        appendMessage('ai', formatLesson(data.response));
        messages.push({ role: 'ai', text: data.response, type: 'lesson' });
        appendActionButtons('lesson');
        saveHistory();
        historyLoaded = false;
        if (historyOpen) loadHistory();
      }
    } catch (err) {
      removeMessage(typingId);
      appendErrorNotice(err.message || 'Could not generate lesson.');
    }
  }

  /* ── Quiz flow ───────────────────────────────────────────── */
  function extractJson(str) {
    let s = str.replace(/```[a-z]*\n?/gi, '').replace(/```/g, '').trim();
    try { return JSON.parse(s); } catch { }
    const start = s.indexOf('{'); const end = s.lastIndexOf('}');
    if (start !== -1 && end !== -1 && end > start) { try { return JSON.parse(s.slice(start, end + 1)); } catch { } }
    const aStart = s.indexOf('['); const aEnd = s.lastIndexOf(']');
    if (aStart !== -1 && aEnd !== -1 && aEnd > aStart) { try { const arr = JSON.parse(s.slice(aStart, aEnd + 1)); return { questions: arr }; } catch { } }
    return null;
  }

  function startQuiz(jsonStr) {
    const parsed = extractJson(jsonStr);
    if (!parsed || !parsed.questions || !parsed.questions.length) {
      appendErrorNotice('Could not read quiz data. Please try again.');
      return;
    }
    // Strip any leading letter labels (A. B. etc.) from choices
    parsed.questions = parsed.questions.map(q => ({
      ...q,
      choices: q.choices.map(c => c.replace(/^[A-D]\.\s*/i, '').trim())
    }));

    // Quiz persistence: always render from state, not raw JSON
    activeQuiz = {
      questions: parsed.questions,
      score: 0,
      currentIndex: 0,
      answers: {},
      completed: false,
      lessonText: lastLessonText // remember the lesson this quiz was based on
    };

    const total = activeQuiz.questions.length;
    appendMessage('ai', `<em style="color:var(--gray)"><span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">assignment</span> Starting quiz — <strong>${total} question${total !== 1 ? 's' : ''}</strong>…</em>`);
    setTimeout(() => showQuestion(), 300);
    return activeQuiz;
  }

  function showQuestion() {
    if (!activeQuiz || activeQuiz.currentIndex >= activeQuiz.questions.length) {
      finishQuiz(); return;
    }
    const q = activeQuiz.questions[activeQuiz.currentIndex];
    const txt = `<strong>Question ${activeQuiz.currentIndex + 1} of ${activeQuiz.questions.length}</strong><br>${escHtml(q.question)}<br><br>`
      + q.choices.map((c, i) => `<strong>${String.fromCharCode(65 + i)}.</strong> ${escHtml(c)}`).join('<br>');
    appendMessage('ai', txt);
    appendChoiceButtons(activeQuiz.currentIndex, q.choices);
  }

  function reconstructAnsweredQuestion(qIdx) {
    if (!activeQuiz || !activeQuiz.questions[qIdx]) return;
    const q = activeQuiz.questions[qIdx];
    const answerIdx = activeQuiz.answers[qIdx];
    const correct = (answerIdx === q.answer);

    // 1. Render Question
    const txt = `<strong>Question ${qIdx + 1} of ${activeQuiz.questions.length}</strong><br>${escHtml(q.question)}<br><br>`
      + q.choices.map((c, i) => `<strong>${String.fromCharCode(65 + i)}.</strong> ${escHtml(c)}`).join('<br>');
    appendMessage('ai', txt, false);

    // 2. Render Choices with locked states
    const wrap = $('messagesWrap');
    const row = document.createElement('div');
    row.className = 'quiz-choices';
    q.choices.forEach((c, i) => {
      const btn = document.createElement('button');
      btn.className = 'choice-btn';
      btn.disabled = true;
      btn.innerHTML = `<span class="choice-letter">${String.fromCharCode(65 + i)}</span><span class="choice-text">${escHtml(c)}</span>`;
      if (i === q.answer) btn.classList.add('correct');
      if (i === answerIdx && !correct) btn.classList.add('wrong');
      row.appendChild(btn);
    });
    wrap.appendChild(row);

    // 3. Render Feedback
    const letter = String.fromCharCode(65 + q.answer);
    const resultMsg = correct
      ? `<span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;color:var(--green)">check_circle</span> <strong>${letter} — Correct!</strong>`
      : `<span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;color:var(--red)">cancel</span> The correct answer is <strong>${letter}. ${escHtml(q.choices[q.answer])}</strong><br><em style="font-size:0.82rem;color:var(--gray)">${escHtml(q.explanation || '')}</em>`;
    appendMessage('ai', resultMsg, false);
  }

  function appendChoiceButtons(qIdx, choices) {
    const wrap = $('messagesWrap');
    const row = document.createElement('div');
    row.className = 'quiz-choices';
    row.id = 'choices-' + qIdx;
    choices.forEach((c, i) => {
      const btn = document.createElement('button');
      btn.className = 'choice-btn ripple';
      if (currentHistorySource === 'archive') {
        btn.disabled = true;
        btn.style.cursor = 'default';
      }
      btn.innerHTML = `<span class="choice-letter">${String.fromCharCode(65 + i)}</span><span class="choice-text">${escHtml(c)}</span>`;
      attachRipple(btn);
      btn.addEventListener('click', () => handleQuizAnswer(i, qIdx));
      row.appendChild(btn);
    });
    wrap.appendChild(row);
    wrap.scrollTop = wrap.scrollHeight;
  }

  function handleQuizAnswer(answerIdx, qIdx) {
    if (!activeQuiz) return;
    const actualQIdx = (qIdx !== undefined) ? qIdx : activeQuiz.currentIndex;
    if (activeQuiz.answers[actualQIdx] !== undefined) return; // already answered

    const q = activeQuiz.questions[actualQIdx];
    const correct = (answerIdx === q.answer);
    if (correct) activeQuiz.score++;
    activeQuiz.answers[actualQIdx] = answerIdx;

    const row = $('choices-' + actualQIdx);
    if (row) {
      row.querySelectorAll('.choice-btn').forEach((btn, i) => {
        btn.disabled = true;
        if (i === q.answer) btn.classList.add('correct');
        if (i === answerIdx && !correct) btn.classList.add('wrong');
      });
    }

    const letter = String.fromCharCode(65 + q.answer);
    const resultMsg = correct
      ? `<span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;color:var(--green)">check_circle</span> <strong>${letter} — Correct!</strong> (${activeQuiz.score} point${activeQuiz.score !== 1 ? 's' : ''} so far)`
      : `<span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;color:var(--red)">cancel</span> The correct answer is <strong>${letter}. ${escHtml(q.choices[q.answer])}</strong><br><em style="font-size:0.82rem;color:var(--gray)">${escHtml(q.explanation || '')}</em>`;

    appendMessage('ai', resultMsg);
    messages.push({ role: 'ai', text: correct ? `Correct! ${letter}` : `Wrong. Correct: ${letter}. ${q.explanation || ''}`, type: 'quiz_feedback' });

    activeQuiz.currentIndex++;

    // Save progress immediately so it is not lost on navigation
    saveHistory();

    setTimeout(() => {
      if (activeQuiz && activeQuiz.currentIndex < activeQuiz.questions.length) showQuestion();
      else finishQuiz();
    }, 900);
  }

  function finishQuiz() {
    if (!activeQuiz) return;
    const total = activeQuiz.questions.length;
    const score = activeQuiz.score;
    const pct = Math.round((score / total) * 100);

    let title, rec, color, bg, border;
    if (pct >= 90) {
      title = '<span class="material-icons" style="font-size:24px;vertical-align:middle;margin-right:6px;color:var(--gold)">celebration</span> Great! You are confident enough to take your exam.';
      rec = 'You have demonstrated strong mastery of the lesson.';
      color = 'var(--green)';
      bg = '#e6f4ee';
      border = '#007a3f';
    } else if (pct >= 70) {
      title = '<span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;color:var(--green)">check_circle</span> You are ready for your quiz or exam.';
      rec = 'You may continue studying for additional confidence.';
      color = 'var(--blue)';
      bg = '#e8f0ff';
      border = '#2058dc';
    } else if (pct >= 31) {
      title = '<span class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;color:var(--red)">warning</span>️ Not enough yet.';
      rec = 'Continue studying and try another lesson before retaking the quiz.';
      color = '#c07400';
      bg = '#fff7e6';
      border = '#f0a500';
    } else {
      title = '<span class="material-icons" style="font-size:24px;vertical-align:middle;margin-right:6px;color:var(--blue)">menu_book</span> You need to study more.';
      rec = 'AI suggests reviewing the lesson and taking the quiz again.';
      color = 'var(--red)';
      bg = '#fff0f0';
      border = '#ff3131';
    }

    const summaryHtml = `
      <div style="background:${bg};border-radius:14px;padding:20px 24px;border-left:5px solid ${border};animation:slideUp 0.35s ease both">
        <div style="font-size:2.4rem;font-weight:800;color:${color};line-height:1">${pct}%</div>
        <div style="font-size:0.82rem;color:var(--gray);margin:4px 0 14px">You scored <strong>${score}</strong> out of <strong>${total}</strong></div>
        <div style="font-size:1rem;font-weight:700;color:${color};margin-bottom:6px">${title}</div>
        <div style="font-size:0.82rem;color:var(--gray)">${rec}</div>
      </div>`;

    appendMessage('ai', summaryHtml);
    messages.push({ role: 'ai', html: summaryHtml, text: `Quiz complete. Score: ${score}/${total} (${pct}%)`, type: 'quiz_summary' });

    // Save score to DB
    const fd = new FormData();
    fd.append('csrf_token', csrfToken());
    fd.append('score', score);
    fd.append('total', total);
    apiFetch('POST', 'api.php?action=save_score', fd).catch(() => { });

    activeQuiz.completed = true;
    saveHistory();

    // Always show action buttons after quiz completes
    setTimeout(() => appendActionButtons('quiz_done'), 400);
  }

  /* ── Save history ────────────────────────────────────────── */
  async function saveHistory() {
    if (messages.length === 0) return;
    const firstUser = messages.find(m => m.role === 'user');
    const titleText = (firstUser?.text || firstUser?.fileName || 'Study Session').substring(0, 80);
    // Strip image data URLs from stored messages (too large for DB)
    const cleanMessages = messages.map(m => {
      if (m.imageDataUrl) { const { imageDataUrl, ...rest } = m; return rest; }
      return m;
    });
    const fd = new FormData();
    fd.append('csrf_token', csrfToken());
    fd.append('title', titleText);
    fd.append('content', JSON.stringify(cleanMessages));

    if (currentHistoryId && (currentHistorySource === 'history' || currentHistorySource === 'pinned')) {
      fd.append('id', currentHistoryId);
    }

    try {
      const res = await apiFetch('POST', 'api.php?action=save_history', fd);
      const data = await res.json();
      if (data.ok && data.id) {
        currentHistoryId = data.id;
        currentHistorySource = 'history';
      }
      historyLoaded = false;
      if (historyOpen) loadHistory();
    } catch { /* silent */ }
  }

  /* ── Message rendering ───────────────────────────────────── */
  let msgCounter = 0;

  function appendFileChipMsg(fileName, animate = true) {
    const wrap = $('messagesWrap');
    const chip = document.createElement('div');
    chip.className = 'file-chip-msg' + (animate ? ' slide-up' : '');
    const ext = fileName.split('.').pop().toLowerCase();
    const icon = ext === 'pdf' ? 'picture_as_pdf' : ext === 'docx' ? 'description' : 'text_snippet';
    const iconBg = ext === 'pdf' ? '#e74c3c' : '#2058dc';
    chip.innerHTML = `<div class="fcm-inner"><div class="fcm-icon" style="background:${iconBg}"><span class="material-icons">${icon}</span></div><span class="fcm-name">${escHtml(fileName)}</span></div>`;
    wrap.appendChild(chip);
    wrap.scrollTop = wrap.scrollHeight;
  }

  function appendImageChipMsg(fileName, dataUrl, animate = true) {
    const wrap = $('messagesWrap');
    const chip = document.createElement('div');
    chip.className = 'img-chip-msg' + (animate ? ' slide-up' : '');
    chip.innerHTML = `
      <div class="img-chip-inner">
        ${dataUrl ? `<img src="${dataUrl}" alt="${escHtml(fileName)}">` : ''}
        <span class="img-chip-name">${escHtml(fileName)}</span>
      </div>`;
    wrap.appendChild(chip);
    wrap.scrollTop = wrap.scrollHeight;
  }

  function appendMessage(role, html, animate = true) {
    const wrap = $('messagesWrap');
    const msgId = 'msg-' + (++msgCounter);
    const isUser = (role === 'user');
    const div = document.createElement('div');
    div.className = `msg ${role}`;
    div.id = msgId;
    if (animate) div.style.animation = 'slideUp 0.3s ease both';
    const logoImg = `<img src="assets/img/logo.png" alt="AI" class="msg-avatar-img">`;
    div.innerHTML = isUser
      ? `<div class="msg-avatar" style="background:var(--blue)"><span class="material-icons" style="font-size:18px;color:#fff">person</span></div><div class="msg-bubble">${html}</div>`
      : `<div class="msg-avatar ai-avatar">${logoImg}</div><div class="msg-bubble lesson-content">${html}</div>`;
    wrap.appendChild(div);
    wrap.scrollTop = wrap.scrollHeight;
    return msgId;
  }

  function appendTyping() {
    const wrap = $('messagesWrap');
    const msgId = 'msg-' + (++msgCounter);
    const logoImg = `<img src="assets/img/logo.png" alt="AI" class="msg-avatar-img">`;
    const div = document.createElement('div');
    div.className = 'msg ai';
    div.id = msgId;
    div.innerHTML = `<div class="msg-avatar ai-avatar">${logoImg}</div><div class="msg-bubble generating"><span class="typing-dots"><span></span><span></span><span></span></span></div>`;
    wrap.appendChild(div);
    wrap.scrollTop = wrap.scrollHeight;
    return msgId;
  }

  function removeMessage(id) { const el = $(id); if (el) el.remove(); }

  /* ── Lesson formatting ───────────────────────────────────── */
  function formatLesson(text) {
    return escHtml(text)
      .replace(/\n/g, '<br>')
      .replace(/(\d+\.\s+[A-Z][^<]{3,60})<br>/g, '<h3 style="margin:14px 0 6px;color:var(--navy)">$1</h3>')
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.+?)\*/g, '<em>$1</em>')
      .replace(/^- (.+)/gm, '<li style="margin-left:18px;list-style:disc">$1</li>');
  }

  /* ── Limit warning ───────────────────────────────────────── */
  function showLimit(msg, resetAt) {
    const warn = $('limitWarn');
    if (!warn) return;
    let countdown = '';
    if (resetAt) {
      const diff = Math.max(0, resetAt * 1000 - Date.now());
      const mins = Math.ceil(diff / 60000);
      countdown = ` (resets in ~${mins} min)`;
    }
    warn.textContent = msg + countdown;
    warn.style.display = 'block';
  }

  /* ── API fetch helper ────────────────────────────────────── */
  function apiFetch(method, url, body = null, signal = null) {
    const opts = { method, signal };
    if (body) opts.body = body;
    return fetch(url, opts).then(async res => {
      if (res.status === 403) {
        try {
          const clone = res.clone();
          const data = await clone.json();
          if (data.suspended) {
            window.location.href = 'dashboard.php';
          }
        } catch (e) {}
      }
      return res;
    });
  }

  /* ── Settings UI Interactions ──────────────────────────────── */

  // More Info Modal
  const moreAccountInfoBtn = $('moreAccountInfoBtn');
  const moreInfoModal = $('moreInfoModal');
  const cancelMoreInfo = $('cancelMoreInfo');

  if (moreAccountInfoBtn && moreInfoModal) {
    moreAccountInfoBtn.addEventListener('click', () => {
      moreInfoModal.style.display = 'flex';
    });

    cancelMoreInfo?.addEventListener('click', () => {
      moreInfoModal.style.display = 'none';
    });
  }

  // Edit Profile Modal
  const editProfileBtn = $('editProfileBtn');
  const editProfileModal = $('editProfileModal');
  const cancelEditProfile = $('cancelEditProfile');
  const saveEditProfile = $('saveEditProfile');

  if (editProfileBtn && editProfileModal) {
    editProfileBtn.addEventListener('click', () => {
      if (window.STUDY_USER) {
        $('editUsername').value = window.STUDY_USER.name || '';
        $('editBirthday').value = window.STUDY_USER.birthday || '';
        $('editGender').value = window.STUDY_USER.gender || '';
      }
      $('editCurrentPassword').value = '';
      $('editNewPassword').value = '';
      $('editConfirmPassword').value = '';

      moreInfoModal.style.display = 'none';
      editProfileModal.style.display = 'flex';
    });

    cancelEditProfile?.addEventListener('click', () => {
      editProfileModal.style.display = 'none';
      moreInfoModal.style.display = 'flex';
    });

    saveEditProfile?.addEventListener('click', async () => {
      const username = $('editUsername').value.trim();
      const birthday = $('editBirthday').value;
      const gender = $('editGender').value;
      const currentPassword = $('editCurrentPassword').value;
      const newPassword = $('editNewPassword').value;
      const confirmPassword = $('editConfirmPassword').value;

      if (!username || !birthday || !gender) {
        alert('Username, birthday, and gender are required.');
        return;
      }

      if (username.length < 3) {
        alert('Username must be at least 3 characters long.');
        return;
      }

      if (newPassword) {
        if (!currentPassword) {
          alert('You must enter your current password to set a new one.');
          return;
        }
        if (newPassword !== confirmPassword) {
          alert('New passwords do not match.');
          return;
        }
      }

      saveEditProfile.textContent = 'Saving...';
      saveEditProfile.disabled = true;

      const fd = new FormData();
      fd.append('csrf_token', csrfToken());
      fd.append('username', username);
      fd.append('birthday', birthday);
      fd.append('gender', gender);
      if (currentPassword) fd.append('current_password', currentPassword);
      if (newPassword) fd.append('new_password', newPassword);
      if (confirmPassword) fd.append('confirm_password', confirmPassword);

      try {
        const res = await apiFetch('POST', 'api.php?action=update_profile', fd);
        const data = await res.json();
        if (data.ok) {
          window.location.reload(); // Reload to refresh user state seamlessly
        } else {
          alert('Error: ' + data.message);
          saveEditProfile.textContent = 'Save Changes';
          saveEditProfile.disabled = false;
        }
      } catch (err) {
        alert('Error saving profile: ' + err.message);
        saveEditProfile.textContent = 'Save Changes';
        saveEditProfile.disabled = false;
      }
    });
  }

  // Delete Account Modal
  const deleteAccountTriggerBtn = $('deleteAccountTriggerBtn');
  const deleteAccountModal = $('deleteAccountModal');
  const cancelDeleteAccount = $('cancelDeleteAccount');
  const confirmDeleteAccount = $('confirmDeleteAccount');

  if (deleteAccountTriggerBtn && deleteAccountModal) {
    deleteAccountTriggerBtn.addEventListener('click', () => {
      moreInfoModal.style.display = 'none';
      $('deleteAccountPassword').value = '';
      deleteAccountModal.style.display = 'flex';
    });

    cancelDeleteAccount?.addEventListener('click', () => {
      deleteAccountModal.style.display = 'none';
      moreInfoModal.style.display = 'flex';
    });

    confirmDeleteAccount?.addEventListener('click', async () => {
      const password = $('deleteAccountPassword').value;
      if (!password) {
        alert('Password is required to delete your account.');
        return;
      }

      confirmDeleteAccount.textContent = 'Deleting...';
      confirmDeleteAccount.disabled = true;

      const fd = new FormData();
      fd.append('csrf_token', csrfToken());
      fd.append('password', password);

      try {
        const res = await apiFetch('POST', 'api.php?action=delete_account', fd);
        const data = await res.json();
        if (data.ok) {
          window.location.href = 'index.php'; // Will redirect to login since session is destroyed
        } else {
          alert('Error: ' + data.message);
          confirmDeleteAccount.textContent = 'Delete Account';
          confirmDeleteAccount.disabled = false;
        }
      } catch (err) {
        alert('Error deleting account: ' + err.message);
        confirmDeleteAccount.textContent = 'Delete Account';
        confirmDeleteAccount.disabled = false;
      }
    });
  }

  // Clear Data Modal
  const settingsClearDataBtn = $('settingsClearDataBtn');
  const clearDataModal = $('clearDataModal');
  const cancelClearData = $('cancelClearData');
  const confirmClearData = $('confirmClearData');
  const clearDataInput = $('clearDataInput');

  settingsClearDataBtn?.addEventListener('click', async () => {
    const origText = settingsClearDataBtn.innerHTML;
    settingsClearDataBtn.disabled = true;
    settingsClearDataBtn.textContent = 'Checking...';
    try {
      const res = await apiFetch('GET', 'api.php?action=check_data_exists');
      const data = await res.json();
      if (!data.hasData) {
        alert('No data available to clear.');
        settingsClearDataBtn.disabled = false;
        settingsClearDataBtn.innerHTML = origText;
        return;
      }
    } catch (e) {
      console.error(e);
    }
    settingsClearDataBtn.disabled = false;
    settingsClearDataBtn.innerHTML = origText;

    if (clearDataModal) {
      clearDataModal.style.display = 'flex';
      if (clearDataInput) {
        clearDataInput.value = '';
        clearDataInput.dispatchEvent(new Event('input'));
      }
    }
  });

  cancelClearData?.addEventListener('click', () => {
    if (clearDataModal) clearDataModal.style.display = 'none';
  });

  clearDataInput?.addEventListener('input', (e) => {
    if (e.target.value === 'CLEAR') {
      confirmClearData.disabled = false;
      confirmClearData.style.opacity = '1';
      confirmClearData.style.cursor = 'pointer';
    } else {
      confirmClearData.disabled = true;
      confirmClearData.style.opacity = '0.5';
      confirmClearData.style.cursor = 'not-allowed';
    }
  });

  confirmClearData?.addEventListener('click', async () => {
    if (!clearDataInput || clearDataInput.value !== 'CLEAR') return;

    // Clear frontend caches
    localStorage.clear();
    sessionStorage.clear();

    confirmClearData.disabled = true;
    confirmClearData.textContent = 'Clearing...';

    const fd = new FormData();
    fd.append('csrf_token', csrfToken());
    try {
      const res = await apiFetch('POST', 'api.php?action=clear_data', fd);
      const data = await res.json();
      if (data.ok) {
        if (clearDataModal) clearDataModal.style.display = 'none';

        // Reset local state completely
        messages.length = 0;
        activeQuiz = null;
        currentHistoryId = null;
        lastLessonText = '';

        // Force reload all lists to show empty states
        historyLoaded = false;
        pinnedLoaded = false;
        archiveLoaded = false;

        if (historyOpen) loadHistory();
        if (pinnedOpen) loadPinned();
        if (archiveOpen) loadArchive();
        loadTrashSettings();
        loadStats();

        resetChat();
        showView('home');

        alert('All learning data has been successfully cleared.');
      } else {
        alert('Error: ' + (data.message || 'Failed to clear data.'));
      }
    } catch (err) {
      console.error(err);
      alert('Failed to clear data. Please try again.');
    } finally {
      confirmClearData.textContent = 'Clear Data';
      confirmClearData.disabled = false;
    }
  });

  // Logout Modal
  const sidebarLogoutBtn = $('sidebarLogoutBtn');
  const settingsLogoutBtn = $('settingsLogoutBtn');
  const logoutModal = $('logoutModal');
  const cancelLogout = $('cancelLogout');
  const confirmLogout = $('confirmLogout');

  function openLogoutModal() {
    if (logoutModal) logoutModal.style.display = 'flex';
  }

  sidebarLogoutBtn?.addEventListener('click', openLogoutModal);
  settingsLogoutBtn?.addEventListener('click', openLogoutModal);

  cancelLogout?.addEventListener('click', () => {
    logoutModal.style.display = 'none';
  });

  confirmLogout?.addEventListener('click', () => {
    window.location.href = 'logout.php';
  });


  var activePolls = {};
  function startPolling(id) {
    if (activePolls[id]) return;
    activePolls[id] = setInterval(async () => {
      try {
        const res = await apiFetch('GET', `api.php?action=history_open&id=${id}`);
        const data = await res.json();
        if (data.ok && data.item) {
          if (data.item.generation_status === 'idle') {
            clearInterval(activePolls[id]);
            delete activePolls[id];

            historyLoaded = false;
            if (historyOpen) loadHistory();

            if (currentHistoryId === id) {
              openHistory(id, 'history');
            }
          }
        }
      } catch (err) { /* silent */ }
    }, 3000);
  }

  // Auto-recover active session on refresh
  const savedId = localStorage.getItem('activeHistoryId');
  if (savedId) {
    openHistory(savedId, 'history');
  }

  /* ════════════════════════════════════════════════════════════
     RATINGS & FEEDBACK
     ════════════════════════════════════════════════════════════ */
  
  // Toast notifications
  function showToast(message, type = 'success') {
    let container = $('toastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toastContainer';
      document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    const icon = type === 'success' ? 'check_circle' : 'error';
    toast.innerHTML = `<span class="material-icons">${icon}</span><span>${escHtml(message)}</span>`;
    
    container.appendChild(toast);
    
    // trigger animation
    setTimeout(() => toast.classList.add('show'), 10);
    
    // auto remove
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  }

  // Rate Us Modal
  const rateUsModal = $('rateUsModal');
  let currentRating = 0;
  
  $('openRateUsBtn')?.addEventListener('click', () => {
    currentRating = 0;
    document.querySelectorAll('.star-rating .star').forEach(s => {
      s.textContent = 'star_border';
      s.classList.remove('active');
    });
    const err = $('ratingError');
    if (err) err.textContent = '';
    if (rateUsModal) rateUsModal.style.display = 'flex';
  });

  $('closeRateUsModal')?.addEventListener('click', () => { if (rateUsModal) rateUsModal.style.display = 'none'; });
  $('cancelRateUs')?.addEventListener('click', () => { if (rateUsModal) rateUsModal.style.display = 'none'; });
  
  const stars = document.querySelectorAll('.star-rating .star');
  stars.forEach(star => {
    star.addEventListener('mouseover', function() {
      const val = parseInt(this.dataset.value);
      stars.forEach(s => {
        if (parseInt(s.dataset.value) <= val) {
          s.textContent = 'star';
          s.classList.add('hover');
        } else {
          if (!s.classList.contains('active')) s.textContent = 'star_border';
          s.classList.remove('hover');
        }
      });
    });
    
    star.addEventListener('mouseout', function() {
      stars.forEach(s => {
        s.classList.remove('hover');
        s.textContent = parseInt(s.dataset.value) <= currentRating ? 'star' : 'star_border';
      });
    });
    
    star.addEventListener('click', function() {
      currentRating = parseInt(this.dataset.value);
      stars.forEach(s => {
        s.classList.toggle('active', parseInt(s.dataset.value) <= currentRating);
      });
      const err = $('ratingError');
      if (err) err.textContent = '';
    });
  });

  $('submitRateUs')?.addEventListener('click', async () => {
    if (currentRating < 1 || currentRating > 5) {
      const err = $('ratingError');
      if (err) err.textContent = 'Please select a rating.';
      return;
    }
    const btn = $('submitRateUs');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="material-icons" style="animation: spin 1s linear infinite;">autorenew</span> Submitting...';
    btn.disabled = true;
    
    try {
      const res = await fetch('api.php?action=submit_rating', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
        body: JSON.stringify({ rating: currentRating })
      });
      const data = await res.json();
      if (data.ok) {
        showToast(data.message || 'Thank you for your rating!', 'success');
        if (rateUsModal) rateUsModal.style.display = 'none';
      } else {
        showToast(data.message || 'Submission failed.', 'error');
      }
    } catch (e) {
      showToast('A network error occurred.', 'error');
    } finally {
      btn.innerHTML = originalText;
      btn.disabled = false;
    }
  });

  // Feedback Modal
  const feedbackModal = $('sendFeedbackModal');
  const feedbackTextarea = $('feedbackTextarea');
  const feedbackWordCount = $('feedbackWordCount');
  const submitFeedback = $('submitFeedback');
  
  $('openFeedbackBtn')?.addEventListener('click', () => {
    if (feedbackTextarea) feedbackTextarea.value = '';
    if (feedbackWordCount) {
      feedbackWordCount.textContent = '0 / 300 words';
      feedbackWordCount.style.color = 'var(--gray)';
    }
    if (submitFeedback) {
      submitFeedback.disabled = true;
      submitFeedback.style.opacity = '0.5';
      submitFeedback.style.cursor = 'not-allowed';
    }
    if (feedbackModal) feedbackModal.style.display = 'flex';
  });

  $('closeFeedbackModal')?.addEventListener('click', () => { if (feedbackModal) feedbackModal.style.display = 'none'; });
  $('cancelFeedback')?.addEventListener('click', () => { if (feedbackModal) feedbackModal.style.display = 'none'; });
  
  if (feedbackTextarea) {
    feedbackTextarea.addEventListener('input', function() {
      const text = this.value.trim();
      const words = text.length > 0 ? text.split(/\s+/).length : 0;
      if (feedbackWordCount) {
        feedbackWordCount.textContent = `${words} / 300 words`;
        if (words > 300) {
          feedbackWordCount.style.color = 'var(--red)';
        } else {
          feedbackWordCount.style.color = 'var(--gray)';
        }
      }
      if (submitFeedback) {
        if (words > 0 && words <= 300) {
          submitFeedback.disabled = false;
          submitFeedback.style.opacity = '1';
          submitFeedback.style.cursor = 'pointer';
        } else {
          submitFeedback.disabled = true;
          submitFeedback.style.opacity = '0.5';
          submitFeedback.style.cursor = 'not-allowed';
        }
      }
    });
  }

  $('submitFeedback')?.addEventListener('click', async () => {
    const text = feedbackTextarea.value.trim();
    if (!text) return;
    
    const btn = $('submitFeedback');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="material-icons" style="animation: spin 1s linear infinite;">autorenew</span> Submitting...';
    btn.disabled = true;
    
    try {
      const res = await fetch('api.php?action=submit_feedback', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
        body: JSON.stringify({ message: text })
      });
      const data = await res.json();
      if (data.ok) {
        showToast(data.message || 'Thank you for your feedback!', 'success');
        if (feedbackModal) feedbackModal.style.display = 'none';
      } else {
        showToast(data.message || 'Submission failed.', 'error');
      }
    } catch (e) {
      showToast('A network error occurred.', 'error');
    } finally {
      btn.innerHTML = originalText;
      btn.disabled = false;
    }
  });

  // Modal ESC key & outside click handling
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      if (rateUsModal && rateUsModal.style.display !== 'none') rateUsModal.style.display = 'none';
      if (feedbackModal && feedbackModal.style.display !== 'none') feedbackModal.style.display = 'none';
    }
  });
  
  [rateUsModal, feedbackModal].forEach(modal => {
    if (modal) {
      modal.addEventListener('click', e => {
        if (e.target === modal) modal.style.display = 'none';
      });
    }
  });

})();
