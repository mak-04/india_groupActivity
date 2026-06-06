/* auth.js — sign-up/login client-side logic */
(function () {
  'use strict';

  /* ── Ripple ──────────────────────────────────────── */
  document.querySelectorAll('.ripple').forEach(el => {
    el.addEventListener('click', e => {
      const r = document.createElement('span');
      r.className = 'ripple-circle';
      const rect = el.getBoundingClientRect();
      r.style.left = (e.clientX - rect.left - 5) + 'px';
      r.style.top = (e.clientY - rect.top - 5) + 'px';
      el.appendChild(r);
      r.addEventListener('animationend', () => r.remove());
    });
  });

  /* ── Show/Hide password ──────────────────────────── */
  document.querySelectorAll('.pw-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = btn.closest('.input-icon').querySelector('input');
      const icon = btn.querySelector('.material-icons');
      if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
      } else {
        input.type = 'password';
        icon.textContent = 'visibility';
      }
    });
  });

  /* ── Password rules (sign-up only) ──────────────── */
  const pwInput = document.getElementById('password');
  const pwRules = document.getElementById('pwRules');
  const confirmPw = document.getElementById('confirmPassword');
  const matchText = document.getElementById('matchText');

  if (!pwInput || !pwRules) return; // login page — nothing more to do

  const rules = {
    length: { el: document.querySelector('[data-rule="length"]'), test: v => v.length >= 8 && v.length <= 30 },
    upper: { el: document.querySelector('[data-rule="upper"]'), test: v => /[A-Z]/.test(v) },
    lower: { el: document.querySelector('[data-rule="lower"]'), test: v => /[a-z]/.test(v) },
    number: { el: document.querySelector('[data-rule="number"]'), test: v => /\d/.test(v) },
    special: { el: document.querySelector('[data-rule="special"]'), test: v => /[^A-Za-z0-9]/.test(v) },
  };

  function runRules(v) {
    for (const key in rules) {
      const r = rules[key];
      if (!r.el) continue;
      if (r.test(v)) {
        r.el.classList.add('ok');
      } else {
        r.el.classList.remove('ok');
      }
    }
  }

  // Show rules popup when password input is focused
  pwInput.addEventListener('focus', () => {
    pwRules.classList.add('visible');
  });

  pwInput.addEventListener('input', () => {
    runRules(pwInput.value);
    checkMatch();
  });

  // Hide rules when user clicks away (focus moves elsewhere)
  // We listen on the whole document for focusin, hide if target is not pwInput
  document.addEventListener('focusin', e => {
    if (e.target !== pwInput) {
      pwRules.classList.remove('visible');
    }
  });

  /* ── Confirm password match ──────────────────────── */
  function checkMatch() {
    if (!confirmPw || !matchText) return;
    if (confirmPw.value === '') {
      matchText.textContent = 'Passwords must match.';
      matchText.className = 'helper-text';
      return;
    }
    if (pwInput.value === confirmPw.value) {
      matchText.innerHTML = '<span class="material-icons" style="font-size:16px;vertical-align:middle;color:var(--green)">check_circle</span> Passwords match.';
      matchText.className = 'helper-text match-ok';
    } else {
      matchText.innerHTML = '<span class="material-icons" style="font-size:16px;vertical-align:middle;color:var(--red)">cancel</span> Passwords do not match.';
      matchText.className = 'helper-text match-err';
    }
  }

  if (confirmPw) {
    confirmPw.addEventListener('input', checkMatch);
  }

  /* ── Auto-hide alerts ────────────────────────────── */
  document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.5s ease';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 500);
    }, 4000);
  });
})();