<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$user = current_user();
if (!$user) {
    header('Location: index.php?mode=login');
    exit;
}
if (!$user['birthday'] || !$user['gender']) {
    header('Location: onboarding.php');
    exit;
}
$firstName = explode(' ', $user['username'])[0];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — <?= APP_NAME ?></title>
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="app-body">

  <!-- ══ SIDEBAR ══════════════════════════════════════════════ -->
  <aside class="sidebar slide-up">

    <div class="side-logo">
      <img src="assets/img/logo.png" alt="Study Tracker Logo" class="side-logo-img">
      <div class="side-logo-text">
        <div class="study">STUDY</div>
        <div class="tracker">TRACKER</div>
      </div>
    </div>

    <button class="new-task-btn ripple" id="newTaskBtn">
      <span class="material-icons">add</span> New Task
    </button>

    <span class="menu-label">MENU</span>
    <nav class="side-nav" style="overflow-y:auto;flex:1">

      <!-- Home -->
      <button class="side-link active" data-view="home">
        <span class="material-icons">home</span> Home
      </button>

      <!-- Pinned -->
      <button class="side-link" id="pinnedToggle">
        <span class="material-icons">push_pin</span> Pinned
        <span class="material-icons chevron-pin" style="margin-left:auto;font-size:16px">expand_more</span>
      </button>
      <div id="pinnedListSide" class="history-list-wrap" style="display:none;max-height:180px;overflow-y:auto">
        <div id="pinnedItems" class="history-skeleton">
          <div class="skel-line"></div>
          <div class="skel-line"></div>
        </div>
      </div>

      <!-- History / Recent -->
      <button class="side-link" data-view="history">
        <span class="material-icons">fact_check</span> Recent &amp; History
        <span class="material-icons chevron-hist" style="margin-left:auto;font-size:16px">expand_more</span>
      </button>
      <div id="historyListSide" class="history-list-wrap" style="display:none;max-height:220px;overflow-y:auto">
        <div id="historyItems" class="history-skeleton">
          <div class="skel-line"></div>
          <div class="skel-line"></div>
          <div class="skel-line"></div>
        </div>
      </div>

      <!-- Archive -->
      <button class="side-link" id="archiveToggle">
        <span class="material-icons">inventory_2</span> Archive
        <span class="material-icons chevron-arch" style="margin-left:auto;font-size:16px">expand_more</span>
      </button>
      <div id="archiveListSide" class="history-list-wrap" style="display:none;max-height:180px;overflow-y:auto">
        <div id="archiveItems" class="history-skeleton">
          <div class="skel-line"></div>
          <div class="skel-line"></div>
        </div>
      </div>

      <!-- Analytics -->
      <button class="side-link" data-view="analytics">
        <span class="material-icons">bar_chart</span> Analytics
      </button>

    </nav>

    <div class="side-bottom">
      <button class="side-settings" data-view="settings">
        <span class="material-icons">settings</span> Settings
      </button>
      <button id="sidebarLogoutBtn" class="side-logout" style="width: 100%; border: none; background: transparent; cursor: pointer;">
        <span class="material-icons">logout</span> Log out
      </button>
    </div>
  </aside>

  <!-- ══ MAIN PANEL ════════════════════════════════════════════ -->
  <main class="main-panel">

    <header class="topbar">
      <div>
        <h1>Welcome, <?= e($firstName) ?></h1>
        <p>Prepare lessons, review notes, and generate exam-ready quizzes.</p>
      </div>
      <div class="net-pill" id="netPill">
        <span class="dot"></span> Checking…
      </div>
    </header>

    <div class="content-area">

      <!-- ── HOME VIEW ──────────────────────────────────────── -->
      <div id="viewHome" class="workspace fade-in">

        <div class="empty-state" id="emptyState">
          <h2>Let's get started....</h2>

          <div class="mode-actions">
            <button class="mode-btn" data-mode="lesson">
              <span class="material-icons" style="vertical-align:middle;font-size:18px;margin-right:6px">auto_stories</span>Start a lesson
            </button>
            <button class="mode-btn" data-mode="quiz">
              <span class="material-icons" style="vertical-align:middle;font-size:18px;margin-right:6px">assignment_turned_in</span>Start quiz
            </button>
          </div>
          <div id="modeNotice" style="display:none;color:#e05c5c;font-size:0.82rem;font-weight:600;margin-top:-12px;text-align:center"></div>

          <div class="prompt-bar-wrap" style="width:100%;max-width:640px">
            <input type="file" id="fileInput" accept=".pdf,.docx,.txt,.jpg,.jpeg,.png,.gif,.webp,.bmp" hidden>
            <div class="prompt-bar" id="promptBarEmpty">
              <button class="upload-icon-btn ripple" id="uploadBtnEmpty" title="Upload file (PDF, DOCX, TXT) or image (JPG, PNG, etc.)">
                <span class="material-icons">upload</span>
              </button>
              <textarea id="topicInput" rows="1" placeholder="Describe your topic / paste notes / upload file or image…"></textarea>
              <button class="send-btn ripple" id="sendBtnEmpty" title="Send">
                <span class="material-icons">arrow_upward</span>
              </button>
            </div>
          </div>
        </div>

        <div class="chat-area" id="chatArea" style="display:none">
          <div class="messages-wrap" id="messagesWrap"></div>
          <div class="limit-warn" id="limitWarn" style="display:none"></div>
          <div class="chat-prompt-wrap" id="chatPromptWrap">
            <div class="prompt-bar" id="promptBarChat">
              <button class="upload-icon-btn ripple" id="uploadBtnChat" title="Upload file or image">
                <span class="material-icons">upload</span>
              </button>
              <textarea id="chatInput" rows="1" placeholder="Ask about your lesson or type quiz…"></textarea>
              <button class="send-btn ripple" id="chatSendBtn" title="Send">
                <span class="material-icons">arrow_upward</span>
              </button>
            </div>
          </div>
          <div id="archivedNotice" style="display:none; text-align:center; padding: 24px; color: var(--gray); font-size: 0.95rem; background: var(--bg-card); border-top: 1px solid var(--border);">
            <span class="material-icons" style="font-size: 20px; vertical-align: middle; margin-right: 6px;">inventory_2</span>
            This chat is archived. Restore it to continue the conversation.
          </div>
        </div>

      </div>

      <!-- ── ANALYTICS VIEW ─────────────────────────────────── -->
      <div id="viewAnalytics" style="display:none;flex:1;overflow-y:auto;padding:28px">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin-bottom:24px" id="statsGrid">
          <!-- populated by JS -->
        </div>
      </div>

      <!-- ── SETTINGS VIEW ──────────────────────────────────── -->
      <div id="viewSettings" style="display:none" class="settings-view">
        <h2>Settings</h2>
        <div class="settings-section">
          <h3 style="display:flex; justify-content:space-between; align-items:center;">
            Account
            <button id="moreAccountInfoBtn" style="background:transparent; border:none; color:var(--blue); font-size:0.85rem; cursor:pointer; font-weight:600; transition: transform 0.2s ease;">[ More Info ]</button>
          </h3>
          <div class="settings-row">
            <label>Username</label>
            <span id="displayUsername"><?= e($user['username']) ?></span>
          </div>
          <div class="settings-row">
            <label>Email</label>
            <span><?= e($user['email']) ?></span>
          </div>
          <div class="settings-row">
            <label>Date Joined</label>
            <span><?= date('F j, Y', strtotime($user['created_at'])) ?></span>
          </div>
        </div>
        <div class="settings-section">
          <h3>AI Limits</h3>
          <div class="settings-row">
            <label>Hourly Limit</label>
            <span><?= HOURLY_AI_LIMIT ?> requests/hour</span>
          </div>
          <div class="settings-row">
            <label>Daily Limit</label>
            <span><?= DAILY_AI_LIMIT ?> requests/day</span>
          </div>
        </div>

        <!-- ── TRASH ─────────────────────────────────────── -->
        <div class="settings-section">
          <h3 style="display:flex; justify-content:space-between; align-items:center;">
            <span style="display:flex;align-items:center;gap:8px">
              <span class="material-icons" style="font-size:18px;color:var(--red)">delete</span>
              Trash
              <span id="trashCountBadge" style="font-size:0.72rem;font-weight:600;background:var(--red);color:#fff;padding:2px 7px;border-radius:20px;display:none"></span>
            </span>
            <button id="emptyTrashBtn" style="background:transparent; border:none; color:var(--red); font-size:0.85rem; cursor:pointer; font-weight:600; display:none; transition: opacity 0.2s ease;">[ Empty Trash ]</button>
          </h3>
          <p style="font-size:0.8rem;color:var(--gray);margin-bottom:12px">Deleted sessions are kept here. Restore them or remove them permanently.</p>
          <div id="trashItemsList" style="max-height: 250px; overflow-y: auto; overflow-x: hidden; border-radius: 8px; padding-right: 4px;">
            <div style="color:var(--gray);font-size:0.82rem">Loading…</div>
          </div>
        </div>

        <div class="settings-section">
          <h3>Danger Zone</h3>
          <div class="settings-row">
            <label>Log out of all sessions</label>
            <button id="settingsLogoutBtn" style="color:var(--red);font-size:0.83rem;font-weight:600; background:transparent; border:none; cursor:pointer; padding:0;">Log out</button>
          </div>
          <div class="settings-row" style="margin-top: 10px;">
            <label>Clear Data</label>
            <button id="settingsClearDataBtn" style="color:var(--red);font-size:0.83rem;font-weight:600; background:transparent; border:none; cursor:pointer; padding:0;">Clear Data</button>
          </div>
        </div>
      </div>

    </div>
  </main>

  <!-- Delete (→ Trash) confirm modal -->
  <div class="modal-overlay" id="deleteModal" style="display:none">
    <div class="modal-card">
      <h3>Move to Trash?</h3>
      <p>This session will be moved to Trash. You can restore it from Settings → Trash.</p>
      <div class="modal-actions">
        <button class="btn-cancel ripple" id="cancelDelete">Cancel</button>
        <button class="btn-danger ripple" id="confirmDelete">Move to Trash</button>
      </div>
    </div>
  </div>

  <!-- Clear Data confirm modal -->
  <div class="modal-overlay" id="clearDataModal" style="display:none">
    <div class="modal-card">
      <h3>Clear All Data?</h3>
      <p style="margin-bottom:8px; font-size:0.9rem;">This action will permanently remove all lessons, quizzes, analytics, progress records, chat history, and recent tasks.</p>
      <p style="margin-bottom:16px; font-size:0.9rem;">Your account will remain active, but all learning data will be erased. <strong style="color:var(--red)">This action cannot be undone.</strong></p>
      
      <label style="font-size:0.85rem; font-weight:600; display:block; margin-bottom:6px;">Type CLEAR to continue</label>
      <input type="text" id="clearDataInput" placeholder="CLEAR" style="width:100%; padding:10px; border-radius:6px; border:2px solid var(--silver); margin-bottom:16px; font-size:0.9rem; box-sizing:border-box; outline:none;">

      <div class="modal-actions">
        <button class="btn-cancel ripple" id="cancelClearData">Cancel</button>
        <button class="btn-danger ripple" id="confirmClearData" disabled style="opacity:0.5; cursor:not-allowed;">Clear Data</button>
      </div>
    </div>
  </div>

  <!-- Archive confirm modal -->
  <div class="modal-overlay" id="archiveModal" style="display:none">
    <div class="modal-card">
      <h3>Archive session?</h3>
      <p>This session will be moved to the Archive. You can restore it later.</p>
      <div class="modal-actions">
        <button class="btn-cancel ripple" id="cancelArchive">Cancel</button>
        <button class="btn-danger ripple" style="background:var(--blue)" id="confirmArchive">Archive</button>
      </div>
    </div>
  </div>

  <!-- Trash permanent-delete confirm modal -->
  <div class="modal-overlay" id="trashPermanentModal" style="display:none">
    <div class="modal-card">
      <h3>Delete permanently?</h3>
      <p>This session will be removed forever. This cannot be undone.</p>
      <div class="modal-actions">
        <button class="btn-cancel ripple" id="cancelTrashDelete">Cancel</button>
        <button class="btn-danger ripple" id="confirmTrashDelete">Delete Forever</button>
      </div>
    </div>
  </div>

  <!-- Empty Trash confirm modal -->
  <div class="modal-overlay" id="emptyTrashModal" style="display:none">
    <div class="modal-card">
      <h3>Empty Trash?</h3>
      <p>Are you sure you want to permanently delete all items in Trash? This cannot be undone.</p>
      <div class="modal-actions">
        <button class="btn-cancel ripple" id="cancelEmptyTrash">Cancel</button>
        <button class="btn-danger ripple" id="confirmEmptyTrash">Empty Trash</button>
      </div>
    </div>
  </div>

  <!-- Logout confirm modal -->
  <div class="modal-overlay" id="logoutModal" style="display:none">
    <div class="modal-card">
      <h3>Are you sure you want to logout?</h3>
      <p>You will need to enter your email and password to access your account again.</p>
      <div class="modal-actions">
        <button class="btn-cancel ripple" id="cancelLogout">Cancel</button>
        <button class="btn-danger ripple" id="confirmLogout">Logout</button>
      </div>
    </div>
  </div>

  <!-- More Info Modal -->
  <div class="modal-overlay" id="moreInfoModal" style="display:none">
    <div class="modal-card" style="max-width: 440px">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 20px;">
        <h3 style="margin:0;">Account Details</h3>
        <button id="cancelMoreInfo" style="background:transparent; border:none; cursor:pointer; color:var(--gray); padding:4px; display:flex; transition:color 0.2s;" onmouseover="this.style.color='var(--white)'" onmouseout="this.style.color='var(--gray)'">
          <span class="material-icons" style="font-size:20px;">close</span>
        </button>
      </div>
      <div style="text-align: left; margin-bottom: 20px;">
        <div class="settings-row" style="margin-bottom: 12px; border-bottom: none;">
          <label style="color:var(--gray)">Username</label>
          <span id="miUsername"><?= e($user['username']) ?></span>
        </div>
        <div class="settings-row" style="margin-bottom: 12px; border-bottom: none;">
          <label style="color:var(--gray)">Email</label>
          <span id="miEmail"><?= e($user['email']) ?></span>
        </div>
        <div class="settings-row" style="margin-bottom: 12px; border-bottom: none;">
          <label style="color:var(--gray)">Date Joined</label>
          <span><?= date('F j, Y', strtotime($user['created_at'])) ?></span>
        </div>
        <div class="settings-row" style="margin-bottom: 12px; border-bottom: none;">
          <label style="color:var(--gray)">Birthday</label>
          <span id="miBirthday"><?= $user['birthday'] ? date('F j, Y', strtotime($user['birthday'])) : 'Not set' ?></span>
        </div>
        <div class="settings-row" style="margin-bottom: 12px; border-bottom: none;">
          <label style="color:var(--gray)">Gender</label>
          <span id="miGender"><?= e($user['gender']) ?: 'Not set' ?></span>
        </div>
      </div>
      <div class="modal-actions" style="display:flex; gap:12px; margin-top:24px;">
        <button class="ripple" id="deleteAccountTriggerBtn" style="flex:1; background:transparent; border:1px solid rgba(255,49,49,0.3); color:var(--red); padding:10px 14px; font-weight:700; font-size:0.95rem; border-radius:10px; white-space:nowrap; display:flex; justify-content:center; align-items:center; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,49,49,0.05)'" onmouseout="this.style.background='transparent'">Delete Account</button>
        <button class="primary-btn ripple" id="editProfileBtn" style="flex:1; background:var(--blue); display:flex; justify-content:center; align-items:center; gap:6px; padding:10px 14px; margin-top:0; white-space:nowrap; font-size:0.95rem;">
          <span class="material-icons" style="font-size:16px;">edit</span> Edit Profile
        </button>
      </div>
    </div>
  </div>

  <!-- Edit Profile Modal -->
  <div class="modal-overlay" id="editProfileModal" style="display:none; z-index: 9999;">
    <div class="modal-card" style="max-width: 480px; padding: 32px; max-height: 90vh; overflow-y: auto;">
      <h3 style="margin: 0 0 24px 0; font-size: 1.5rem; font-weight: 700; color: var(--black); text-align: left;">Edit Profile</h3>
      
      <div style="text-align: left; margin-bottom: 28px;">
        <!-- Personal Information Section -->
        <h4 style="font-size: 1.05rem; font-weight: 600; color: var(--black); margin-bottom: 16px; border-bottom: 1px solid rgba(0,0,0,0.08); padding-bottom: 8px;">Personal Information</h4>
        
        <div style="margin-bottom: 16px;">
          <label style="display:block; margin-bottom:6px; font-size:0.85rem; font-weight:600; color:#444;">Username</label>
          <input type="text" id="editUsername" style="width:100%; padding:12px 14px; background:rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.1); border-radius:8px; color:var(--black); font-size:0.9rem; transition:border-color 0.2s, box-shadow 0.2s;" onfocus="this.style.borderColor='var(--blue)'; this.style.boxShadow='0 0 0 3px rgba(32,88,220,0.15)';" onblur="this.style.borderColor='rgba(0,0,0,0.1)'; this.style.boxShadow='none';">
        </div>

        <div style="margin-bottom: 16px;">
          <label style="display:block; margin-bottom:6px; font-size:0.85rem; font-weight:600; color:#444;">Birthday</label>
          <input type="date" id="editBirthday" style="width:100%; padding:12px 14px; background:rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.1); border-radius:8px; color:var(--black); font-size:0.9rem; transition:border-color 0.2s, box-shadow 0.2s;" onfocus="this.style.borderColor='var(--blue)'; this.style.boxShadow='0 0 0 3px rgba(32,88,220,0.15)';" onblur="this.style.borderColor='rgba(0,0,0,0.1)'; this.style.boxShadow='none';">
        </div>

        <div style="margin-bottom: 24px;">
          <label style="display:block; margin-bottom:6px; font-size:0.85rem; font-weight:600; color:#444;">Gender</label>
          <select id="editGender" style="width:100%; padding:12px 14px; background:rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.1); border-radius:8px; color:var(--black); font-size:0.9rem; transition:border-color 0.2s, box-shadow 0.2s; cursor:pointer; appearance:none; -webkit-appearance:none; background-image:url('data:image/svg+xml;utf8,<svg fill=%22%23737373%22 height=%2224%22 viewBox=%220 0 24 24%22 width=%2224%22 xmlns=%22http://www.w3.org/2000/svg%22><path d=%22M7 10l5 5 5-5z%22/></svg>'); background-repeat:no-repeat; background-position-x:calc(100% - 12px); background-position-y:50%;" onfocus="this.style.borderColor='var(--blue)'; this.style.boxShadow='0 0 0 3px rgba(32,88,220,0.15)';" onblur="this.style.borderColor='rgba(0,0,0,0.1)'; this.style.boxShadow='none';">
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Prefer Not To Say">Prefer Not To Say</option>
          </select>
        </div>
        
        <!-- Security Section -->
        <h4 style="font-size: 1.05rem; font-weight: 600; color: var(--black); margin-bottom: 6px; border-bottom: 1px solid rgba(0,0,0,0.08); padding-bottom: 8px;">Security</h4>
        <p style="font-size:0.8rem; color:var(--gray); margin-bottom:16px;"><em>Leave password fields empty if you do not wish to change your password.</em></p>

        <div style="margin-bottom: 16px;">
          <label style="display:block; margin-bottom:6px; font-size:0.85rem; font-weight:600; color:#444;">Current Password</label>
          <input type="password" id="editCurrentPassword" style="width:100%; padding:12px 14px; background:rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.1); border-radius:8px; color:var(--black); font-size:0.9rem; transition:border-color 0.2s, box-shadow 0.2s;" onfocus="this.style.borderColor='var(--blue)'; this.style.boxShadow='0 0 0 3px rgba(32,88,220,0.15)';" onblur="this.style.borderColor='rgba(0,0,0,0.1)'; this.style.boxShadow='none';" placeholder="••••••••">
        </div>

        <div style="margin-bottom: 16px;">
          <label style="display:block; margin-bottom:6px; font-size:0.85rem; font-weight:600; color:#444;">New Password</label>
          <input type="password" id="editNewPassword" style="width:100%; padding:12px 14px; background:rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.1); border-radius:8px; color:var(--black); font-size:0.9rem; transition:border-color 0.2s, box-shadow 0.2s;" onfocus="this.style.borderColor='var(--blue)'; this.style.boxShadow='0 0 0 3px rgba(32,88,220,0.15)';" onblur="this.style.borderColor='rgba(0,0,0,0.1)'; this.style.boxShadow='none';" placeholder="••••••••">
        </div>
        
        <div style="margin-bottom: 8px;">
          <label style="display:block; margin-bottom:6px; font-size:0.85rem; font-weight:600; color:#444;">Confirm New Password</label>
          <input type="password" id="editConfirmPassword" style="width:100%; padding:12px 14px; background:rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.1); border-radius:8px; color:var(--black); font-size:0.9rem; transition:border-color 0.2s, box-shadow 0.2s;" onfocus="this.style.borderColor='var(--blue)'; this.style.boxShadow='0 0 0 3px rgba(32,88,220,0.15)';" onblur="this.style.borderColor='rgba(0,0,0,0.1)'; this.style.boxShadow='none';" placeholder="••••••••">
        </div>
      </div>
      
      <div class="modal-actions" style="display:flex; gap:12px; margin-top:0;">
        <button class="btn-cancel ripple" id="cancelEditProfile" style="flex:1; padding:12px; font-weight:600; border-radius:8px;">Cancel</button>
        <button class="primary-btn ripple" id="saveEditProfile" style="flex:1; background:var(--blue); padding:12px; font-weight:600; border-radius:8px; display:flex; justify-content:center; align-items:center;">Save Changes</button>
      </div>
    </div>
  </div>

  <!-- Delete Account Confirm Modal -->
  <div class="modal-overlay" id="deleteAccountModal" style="display:none; z-index: 9999;">
    <div class="modal-card" style="max-width: 460px; padding: 32px;">
      
      <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
        <div style="background:rgba(255,49,49,0.1); width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center;">
          <span class="material-icons" style="color:var(--red); font-size:22px;">warning_amber</span>
        </div>
        <h3 style="margin:0; font-size:1.4rem; font-weight:700;">Delete Account</h3>
      </div>
      
      <div style="text-align:left; font-size:0.9rem; color:var(--gray); margin-bottom:24px; line-height:1.6;">
        <p style="margin-bottom:16px;">This action is permanent and cannot be undone. Please be certain.</p>
        
        <div style="background:rgba(255,49,49,0.06); border:1px solid rgba(255,49,49,0.15); border-radius:8px; padding:16px; margin-bottom:16px;">
          <p style="color:var(--red); font-weight:600; margin-bottom:10px; font-size:0.85rem;">The following will be permanently deleted:</p>
          <ul style="margin:0; padding-left:20px; color:#444; font-size:0.85rem; line-height:1.8;">
            <li>Account data and settings</li>
            <li>Chats and conversations</li>
            <li>Archives and pinned chats</li>
            <li>Profile information</li>
          </ul>
        </div>

        <p style="color:var(--black); font-weight:600; margin-bottom:12px;">Are you sure you want to continue?</p>
      </div>
      
      <div style="text-align: left; margin-bottom: 28px;">
        <label style="display:block; margin-bottom:8px; font-size:0.85rem; font-weight:600; color:var(--black);">Confirm your password</label>
        <input type="password" id="deleteAccountPassword" style="width:100%; padding:12px 14px; background:rgba(0,0,0,0.04); border:1px solid rgba(0,0,0,0.1); border-radius:8px; color:var(--black); transition:border-color 0.2s, box-shadow 0.2s;" onfocus="this.style.borderColor='var(--blue)'; this.style.boxShadow='0 0 0 2px rgba(32,88,220,0.2)';" onblur="this.style.borderColor='rgba(0,0,0,0.1)'; this.style.boxShadow='none';" placeholder="Enter your password">
      </div>

      <div class="modal-actions" style="display:flex; gap:12px; margin-top:0;">
        <button class="btn-cancel ripple" id="cancelDeleteAccount" style="flex:1; padding:12px; font-weight:600; border-radius:8px;">Cancel</button>
        <button class="btn-danger ripple" id="confirmDeleteAccount" style="flex:1; padding:12px; font-weight:600; border-radius:8px; display:flex; justify-content:center; align-items:center; gap:6px;">Delete Account</button>
      </div>
    </div>
  </div>

  <script>
    window.STUDY_USER = <?= json_encode([
      'name' => $user['username'], 
      'id' => $user['id'],
      'email' => $user['email'],
      'birthday' => $user['birthday'],
      'gender' => $user['gender']
    ]) ?>;
    window.APP_CSRF   = <?= json_encode(csrf_token()) ?>;
  </script>
  <script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>
</html>