/* ==========================================================================
   js/pwa.js — Green Valley SRMS "app" behaviour (mobile + offline)
   --------------------------------------------------------------------------
   Three jobs, all client-side and data-free:
     1. Register the service worker (service-worker.js) so the site installs
        to a phone home screen and survives going offline.
     2. Drive the mobile navigation drawer (the hamburger button in the top bar
        opens/closes the sidebar on small screens).
     3. On sign-out, tell the service worker to wipe cached pages so student
        records don't linger on a shared device. Also offer an "Install app"
        button when the browser says the app is installable.

   No database access, no credentials — this only talks to the browser.
   ========================================================================== */
(function () {
  'use strict';

  /* ---------------------------------------------------------------------- *
   * 1. Service worker registration
   * ---------------------------------------------------------------------- */
  if ('serviceWorker' in navigator && location.protocol !== 'file:') {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('service-worker.js').catch(function (err) {
        // Not fatal — the app still works online without offline support.
        console.warn('[PWA] Service worker registration failed:', err);
      });
    });
  }

  /* ---------------------------------------------------------------------- *
   * 2. Mobile navigation drawer
   * ---------------------------------------------------------------------- */
  function initDrawer() {
    var sidebar = document.getElementById('sidebar');
    var toggle = document.querySelector('.nav-toggle');
    if (!sidebar || !toggle) return;

    // Backdrop sits behind the drawer and closes it when tapped.
    var backdrop = document.createElement('div');
    backdrop.className = 'nav-backdrop';
    document.body.appendChild(backdrop);

    function openNav() {
      sidebar.classList.add('open');
      backdrop.classList.add('show');
      document.body.classList.add('nav-open');
      toggle.setAttribute('aria-expanded', 'true');
    }
    function closeNav() {
      sidebar.classList.remove('open');
      backdrop.classList.remove('show');
      document.body.classList.remove('nav-open');
      toggle.setAttribute('aria-expanded', 'false');
    }
    function isOpen() {
      return sidebar.classList.contains('open');
    }

    toggle.addEventListener('click', function () {
      isOpen() ? closeNav() : openNav();
    });
    backdrop.addEventListener('click', closeNav);

    // Tapping any nav link closes the drawer as the new page loads.
    sidebar.addEventListener('click', function (e) {
      if (e.target.closest('a')) closeNav();
    });

    // Escape closes it; keeps keyboard users unstuck.
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen()) closeNav();
    });

    // If the window grows back to desktop width, reset the drawer state.
    window.addEventListener('resize', function () {
      if (window.innerWidth > 860 && isOpen()) closeNav();
    });
  }

  /* ---------------------------------------------------------------------- *
   * 3a. Clear cached pages on sign-out (shared-device privacy)
   * ---------------------------------------------------------------------- */
  function initLogoutPurge() {
    document.addEventListener('click', function (e) {
      var a = e.target.closest('a[href]');
      if (!a) return;
      var href = a.getAttribute('href') || '';
      if (/logout\.php(?:$|[?#])/.test(href)) {
        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
          navigator.serviceWorker.controller.postMessage({ type: 'CLEAR_PAGES' });
        }
      }
    });
  }

  /* ---------------------------------------------------------------------- *
   * 3b. "Install app" button (appears only when the browser allows it)
   * ---------------------------------------------------------------------- */
  function initInstallButton() {
    var deferredPrompt = null;
    var btn = null;

    function makeButton() {
      if (btn) return btn;
      btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pwa-install';
      btn.setAttribute('hidden', '');
      btn.innerHTML =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" ' +
        'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>' +
        '<span>Install app</span>';
      btn.addEventListener('click', function () {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        deferredPrompt.userChoice.finally(function () {
          deferredPrompt = null;
          hide();
        });
      });
      document.body.appendChild(btn);
      return btn;
    }
    function show() { makeButton().removeAttribute('hidden'); }
    function hide() { if (btn) btn.setAttribute('hidden', ''); }

    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();      // stop Chrome's default mini-infobar
      deferredPrompt = e;      // stash it to trigger from our own button
      show();
    });
    window.addEventListener('appinstalled', function () {
      deferredPrompt = null;
      hide();
    });
  }

  /* ---------------------------------------------------------------------- */
  function init() {
    initDrawer();
    initLogoutPurge();
    initInstallButton();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
