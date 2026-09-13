/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — COMMUNITY PACKAGE RUNTIME HOST
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Applies installed Workshop community packages (Manifest v1) to the running
 *  IDE. Backend counterpart: app/services/WorkshopPackage.php, fed through
 *  index.php?api=workshop-catalog / workshop-toggle / workshop-asset.
 *
 *  WHAT RUNS, IN REGISTRY ORDER (enabled packages only):
 *    • cssInject  → <link rel=stylesheet> per entrypoint, appended to <head>,
 *                   tracked so disable() can remove them live.
 *    • jsRun      → classic <script defer> PER ENTRYPOINT, only when the user
 *                   granted consent for this package version (see CONSENT).
 *                   Scripts run with FULL IDE privileges — that is exactly why
 *                   consent exists and why uninstalling cannot un-ring the bell
 *                   (disable() shows a reload banner instead of pretending).
 *    • snippets   → each .json pool fetched through the jailed asset endpoint
 *                   and handed to autocomplete (see AUTOCOORD below).
 *    • storage    → window.IDE.packages.storage(id): a tiny wrapper whose keys
 *                   are forcibly prefixed "quirky.pkg.<id>." in localStorage.
 *                   This is a convention the host enforces on its own API;
 *                   page-level JS can always reach raw localStorage anyway,
 *                   which is precisely why jsRun needs explicit consent.
 *
 *  CONSENT: stored at localStorage["quirky.pkg.consent.<id>"] as
 *    "<version>|<hash-of-capabilities>" written by the Workshop UI's trust
 *  sheet. A jsRun-capable package runs ONLY while that record matches its
 *  current version + capabilities. Updates change the hash → consent resets.
 *
 *  SAFE MODE: localStorage["quirky.workshop.safeMode"] === "1" makes boot
 *  apply NOTHING and exposes safeMode(on/off) here. Server-side hard-off
 *  still needs the "packages_enabled" feature flag (requested from the
 *  integrator; config/shell files are not edited by this feature).
 *
 *  AUTOCOORD (documented assumption): ide-mx currently ships no autocomplete
 *  bundle member, so window.IDE.autocomplete may not exist yet. Snippet pools
 *  are therefore queued and flushed by whichever happens first of:
 *    (a) IDE.autocomplete.registerPool appearing (poll),
 *    (b) an "autocomplete:ready" event on the IDE.utils event bus — the event
 *        name agreed with the intel modules; if that name changes upstream,
 *        only AUTO_READY_EVENT below needs updating,
 *    (c) a single 3 s retry fallback.
 *  registerPool signature relied upon: registerPool(langKey, items[]) where
 *  items are {label, body, detail?} with U+00B7 indent markers left intact.
 *
 *  EXPOSES: window.IDE.packages = {
 *    list(), enable(id), disable(id), storage(id), safeMode(on),
 *    isSafeMode(), refresh()
 *  }
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
  'use strict';

  var CATALOG_URL = 'index.php?api=workshop-catalog';
  var ASSET_BASE = 'index.php?api=workshop-asset&id=';
  var TOGGLE_URL = 'index.php?api=workshop-toggle';
  var SAFE_MODE_KEY = 'quirky.workshop.safeMode';
  var CONSENT_PREFIX = 'quirky.pkg.consent.';
  var STORAGE_PREFIX = 'quirky.pkg.';
  var AUTO_READY_EVENT = 'autocomplete:ready';
  var AUTO_WAIT_MS = 3000;

  /* Applied-node tracking: pkgId → {links:[…], scripts:[…]} */
  var applied = {};
  var snippetQueues = {};   // pkgId → [{langKey, items}]
  var autoFlushTimer = null;
  var booted = false;

  /* ── small helpers ──────────────────────────────────────── */
  function ls(key) {
    try { return window.localStorage.getItem(key); } catch (e) { return null; }
  }
  function lsSet(key, val) {
    try {
      if (val === null) window.localStorage.removeItem(key);
      else window.localStorage.setItem(key, val);
    } catch (e) { /* storage full/blocked — non-fatal */ }
  }
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  /** Deterministic capability hash for consent records (not cryptographic —
   *  just enough to detect version/capability drift). */
  function capHash(pkg) {
    var caps = pkg.capabilities || {};
    var raw = [pkg.version, !!caps.cssInject, !!caps.jsRun, !!caps.storage,
      (caps.network || []).join(',')].join('|');
    var h = 5381;
    for (var i = 0; i < raw.length; i++) h = ((h << 5) + h + raw.charCodeAt(i)) | 0;
    return 'h' + (h >>> 0).toString(36);
  }
  function assetUrl(pkgId, file, version) {
    return ASSET_BASE + encodeURIComponent(pkgId) +
      '&f=' + encodeURIComponent(file) +
      '&v=' + encodeURIComponent(version || '');
  }

  /* ═══════════════════════════════════════════════════════════
      APPLY / UNAPPLY
      ═══════════════════════════════════════════════════════════ */

  function applyPackage(pkg) {
    if (!pkg || !pkg.enabled || !pkg.id) return false;
    if (isSafeMode()) return false;
    unapplyPackage(pkg.id); // idempotent re-apply

    var eps = pkg.entrypoints || {};
    var caps = pkg.capabilities || {};
    var state = { links: [], scripts: [] };
    var didSomething = false;

    /* ── CSS injection (tracked <link>s) ── */
    if (caps.cssInject && Array.isArray(eps.css)) {
      eps.css.forEach(function (file) {
        if (typeof file !== 'string' || file === '') return;
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = assetUrl(pkg.id, file, pkg.version);
        link.setAttribute('data-quirky-pkg', pkg.id);
        document.head.appendChild(link);
        state.links.push(link);
        didSomething = true;
      });
    }

    /* ── JS execution (consent-gated, classic scripts, in order) ── */
    if (caps.jsRun && hasConsent(pkg) && Array.isArray(eps.js)) {
      eps.js.forEach(function (file) {
        if (typeof file !== 'string' || file === '') return;
        var script = document.createElement('script');
        script.src = assetUrl(pkg.id, file, pkg.version);
        script.defer = true;
        script.setAttribute('data-quirky-pkg', pkg.id);
        document.head.appendChild(script);
        state.scripts.push(script);
        didSomething = true;
      });
    }

    /* ── Snippet pools (fetched, queued, flushed at autocomplete) ── */
    if (typeof eps.snippets === 'string' && eps.snippets !== '') {
      queueSnippets(pkg, eps.snippets);
      didSomething = true;
    }

    applied[pkg.id] = state;
    return didSomething;
  }

  /**
   * Remove tracked nodes. CSS unloads cleanly; already-executed JS cannot be
   * unloaded — callers show the reload banner (see bannerReload).
   */
  function unapplyPackage(pkgId) {
    var state = applied[pkgId];
    if (!state) return;
    state.links.forEach(function (link) {
      if (link.parentNode) link.parentNode.removeChild(link);
    });
    state.scripts.forEach(function (script) {
      // Detach so nothing pending evaluates against removed assumptions.
      if (script.parentNode) script.parentNode.removeChild(script);
    });
    delete applied[pkgId];
    if (snippetQueues[pkgId]) delete snippetQueues[pkgId];
  }

  function appliedAnyJs(pkgId) {
    var state = applied[pkgId];
    return !!(state && state.scripts.length > 0);
  }

  /* ═══════════════════════════════════════════════════════════
      SNIPPETS → AUTOCOMPLETE BRIDGE
      ═══════════════════════════════════════════════════════════ */

  function queueSnippets(pkg, spec) {
    // Zip packs point at a DIRECTORY of *.json pools; the jailed asset
    // endpoint serves files, not listings. Pool files must therefore be
    // named explicitly: "<dir>/<name>.json". We accept either a bare .json
    // file or a directory spec accompanied by an optional manifest-declared
    // list — absent a listing we probe nothing and rely on the documented
    // convention: pools live directly under the declared folder.
    var isFile = /\.json$/i.test(spec);
    var candidates = isFile ? [spec] : (declaredPoolFiles(pkg, spec));
    if (!candidates.length) {
      // Directory without an explicit file list: try the conventional
      // "pools.json" single-file layout before giving up silently.
      candidates = [spec.replace(/\/+$/, '') + '/pools.json'];
    }
    var pending = candidates.length;
    if (!pending) return;
    var collected = [];
    candidates.forEach(function (file) {
      fetch(assetUrl(pkg.id, file, pkg.version), { headers: { Accept: 'application/json' } })
        .then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
        .then(function (pool) {
          if (pool && typeof pool === 'object') collected.push(pool);
        })
        .catch(function () { /* one bad pool must not sink the pack */ })
        .then(function () {
          if (--pending === 0) {
            enqueueForAutocomplete(pkg.id, collected);
            scheduleAutoFlush();
          }
        });
    });
  }

  /**
   * Manifest v1 keeps entrypoints.snippets as a string, so a multi-file pool
   * listing can only come from a sibling declaration. Accepted convention:
   * capabilities may carry "snippetFiles":[…]; otherwise fall back to the
   * single pools.json probe handled by the caller.
   */
  function declaredPoolFiles(pkg, dirSpec) {
    var caps = pkg.capabilities || {};
    if (!Array.isArray(caps.snippetFiles)) return [];
    var dir = String(dirSpec || '').replace(/\/+$/, '');
    return caps.snippetFiles.filter(function (f) {
      return typeof f === 'string' && /\.json$/i.test(f);
    }).map(function (f) {
      // Absolute-from-package-root paths win; bare names resolve inside dirSpec.
      return f.indexOf('/') !== -1 ? f : (dir ? dir + '/' + f : f);
    });
  }

  function enqueueForAutocomplete(pkgId, pools) {
    snippetQueues[pkgId] = (snippetQueues[pkgId] || []).concat(pools);
  }

  /** Push every queued pool into the autocomplete registry, once present. */
  function flushToAutocomplete() {
    var reg = window.IDE && window.IDE.autocomplete;
    if (!reg || typeof reg.registerPool !== 'function') return false;
    Object.keys(snippetQueues).forEach(function (pkgId) {
      snippetQueues[pkgId].forEach(function (pool) {
        Object.keys(pool).forEach(function (langKey) {
          try {
            reg.registerPool(langKey, pool[langKey]);
          } catch (e) { /* a hostile pool shape must not break the editor */ }
        });
      });
    });
    snippetQueues = {};
    return true;
  }

  function scheduleAutoFlush() {
    if (autoFlushTimer !== null) return;
    var attempts = 0;
    var tick = function () {
      if (flushToAutocomplete()) { autoFlushTimer = null; return; }
      if (++attempts > 2) { autoFlushTimer = null; return; } // ~3 s total, then drop
      autoFlushTimer = setTimeout(tick, AUTO_WAIT_MS);
    };
    var U = window.IDE && window.IDE.utils;
    if (U && typeof U.on === 'function') {
      U.on(AUTO_READY_EVENT, function () { flushToAutocomplete(); });
    }
    autoFlushTimer = setTimeout(tick, AUTO_WAIT_MS);
  }

  /* ═══════════════════════════════════════════════════════════
      CONSENT
      ═══════════════════════════════════════════════════════════ */

  function hasConsent(pkg) {
    var record = ls(CONSENT_PREFIX + pkg.id);
    if (!record) return false;
    return record === pkg.version + '|' + capHash(pkg);
  }
  /** Called by the Workshop trust sheet. Pass null to revoke. */
  function setConsent(pkg, granted) {
    if (granted) {
      lsSet(CONSENT_PREFIX + pkg.id, pkg.version + '|' + capHash(pkg));
    } else {
      lsSet(CONSENT_PREFIX + pkg.id, null);
    }
  }

  /* ═══════════════════════════════════════════════════════════
      RELOAD BANNER (unsafe-to-unload JS)
      ═══════════════════════════════════════════════════════════ */

  function bannerReload(reason) {
    if (document.getElementById('m-pkg-reload-banner')) return;
    var bar = document.createElement('div');
    bar.id = 'm-pkg-reload-banner';
    bar.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:400;' +
      'display:flex;align-items:center;gap:.75rem;padding:.7rem 1rem;' +
      'padding-bottom:calc(.7rem + env(safe-area-inset-bottom,0px));' +
      'background:var(--surface,#131c2b);border-top:1px solid var(--accent-border,rgba(245,165,36,.32));' +
      'color:var(--text-2,#9db1cc);font-size:.78rem;';
    var msg = document.createElement('span');
    msg.style.cssText = 'flex:1 1 auto;min-width:0;';
    msg.textContent = reason || 'Package changes need a reload to fully apply.';
    var btn = document.createElement('button');
    btn.textContent = '↻ Reload';
    btn.style.cssText = 'flex-shrink:0;padding:.45rem .9rem;border-radius:8px;border:1px solid var(--accent-border,rgba(245,165,36,.32));' +
      'background:var(--accent-dim,rgba(245,165,36,.12));color:var(--accent,#f5a524);font-weight:700;font-size:.72rem;';
    btn.addEventListener('click', function () { window.location.reload(); });
    bar.appendChild(msg);
    bar.appendChild(btn);
    document.body.appendChild(bar);
  }

  /* ═══════════════════════════════════════════════════════════
      BOOT
      ═══════════════════════════════════════════════════════════ */

  function waitForIde(cb, tries) {
    var n = tries || 0;
    if (window.IDE && window.IDE.utils && window.IDE.api) { cb(); return; }
    if (n > 100) return; // ~10 s, then give up quietly
    setTimeout(function () { waitForIde(cb, n + 1); }, 100);
  }

  function boot() {
    if (booted) return;
    booted = true;

    var cfg = window.IDE_CONFIG || {};
    // Feature gate mirrors the server's quirkyMobileCanUseWorkshop(): the
    // backend remains authoritative — this only avoids pointless requests.
    if (cfg.features && cfg.features.workshop === false) return;

    fetchCatalog(function (packages) {
      if (isSafeMode()) return; // stay inert; UI explains why
      packages.forEach(applyPackage);
      scheduleAutoFlush();
    });
  }

  function fetchCatalog(cb) {
    fetch(CATALOG_URL, { headers: { Accept: 'application/json' } })
      .then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
      .then(function (j) {
        if (!j || j.ok === false) throw new Error((j && j.error) || 'catalog failed');
        cb(Array.isArray(j.data && j.data.packages) ? j.data.packages : []);
      })
      .catch(function () { /* offline / disabled workshop — host stays idle */ });
  }

  /* ═══════════════════════════════════════════════════════════
      PUBLIC API
      ═══════════════════════════════════════════════════════════ */

  window.IDE = window.IDE || {};

  function toggleRequest(pkgId, enabled) {
    return fetch(TOGGLE_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ id: pkgId, enabled: enabled })
    })
      .then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
      .then(function (j) {
        if (!j || j.ok === false) throw new Error((j && j.error) || 'toggle failed');
        return j.data;
      });
  }

  window.IDE.packages = {
    /** Live view: catalog rows annotated with runtime application state. */
    list: function () {
      return new Promise(function (resolve) {
        fetchCatalog(function (packages) {
          resolve(packages.map(function (p) {
            return Object.assign({}, p, {
              appliedNow: !!applied[p.id],
              jsConsented: hasConsent(p),
              runningUnsafe: appliedAnyJs(p.id)
            });
          }));
        });
      });
    },
    /** Enable server-side, then apply live where safe. */
    enable: function (pkgId) {
      return toggleRequest(pkgId, true).then(function (data) {
        fetchCatalog(function (packages) {
          var pkg = null;
          packages.forEach(function (p) { if (p.id === pkgId) pkg = p; });
          if (pkg && applyPackage(pkg)) {
            if ((pkg.capabilities || {}).jsRun) {
              // Freshly enabled JS executes on next load; applying now would
              // run it mid-session without the user seeing the tradeoff.
              unapplyPackage(pkgId);
              bannerReload('Enabled "' + (pkg.name || pkgId) + '" — reload to run its code.');
            }
          }
        });
        return data;
      });
    },
    /** Disable server-side, unapply tracked nodes, warn about executed JS. */
    disable: function (pkgId) {
      return toggleRequest(pkgId, false).then(function (data) {
        if (appliedAnyJs(pkgId)) {
          unapplyPackage(pkgId);
          bannerReload('Disabled "' + pkgId + '" — its code already ran, reload to finish removal.');
        } else {
          unapplyPackage(pkgId);
        }
        return data;
      });
    },
    /** Namespaced storage facade for consenting packages. */
    storage: function (pkgId) {
      if (!/^[a-z0-9][a-z0-9._-]{2,63}$/.test(String(pkgId))) {
        throw new Error('Invalid package id for storage.');
      }
      var prefix = STORAGE_PREFIX + pkgId + '.';
      return {
        get: function (key) { return ls(prefix + key); },
        set: function (key, value) { lsSet(prefix + key, String(value)); },
        remove: function (key) { lsSet(prefix + key, null); },
        keys: function () {
          var out = [];
          try {
            for (var i = 0; i < window.localStorage.length; i++) {
              var k = window.localStorage.key(i);
              if (k && k.indexOf(prefix) === 0) out.push(k.slice(prefix.length));
            }
          } catch (e) { /* blocked */ }
          return out;
        }
      };
    },
    /** Trust record management used by the Workshop consent sheet. */
    consent: {
      has: hasConsent,
      grant: setConsent
    },
    /** Global kill switch (client side). Persisted. */
    safeMode: function (on) {
      lsSet(SAFE_MODE_KEY, on ? '1' : '0');
      if (on) {
        Object.keys(applied).forEach(function (pkgId) {
          if (appliedAnyJs(pkgId)) {
            unapplyPackage(pkgId);
            bannerReload('Safe mode is ON — reload to unload package code.');
            return;
          }
          unapplyPackage(pkgId);
        });
        snippetQueues = {};
      }
      return isSafeMode();
    },
    isSafeMode: isSafeMode,
    /** Re-read the catalog and re-apply (used after install/update/remove). */
    refresh: function () {
      Object.keys(applied).forEach(unapplyPackage);
      snippetQueues = {};
      if (isSafeMode()) return Promise.resolve([]);
      return new Promise(function (resolve) {
        fetchCatalog(function (packages) {
          packages.forEach(applyPackage);
          scheduleAutoFlush();
          resolve(packages);
        });
      });
    }
  };

  function isSafeMode() {
    return ls(SAFE_MODE_KEY) === '1';
  }

  /* ═══════════════════════════════════════════════════════════
      WIRING — deferred boot (this file loads right after
      mobile-workshop.js and before mobile-shell.js boots last)
      ═══════════════════════════════════════════════════════════ */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { waitForIde(boot); });
  } else {
    waitForIde(boot);
  }
})();
