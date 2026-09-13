/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — MOBILE JS BRIDGE (Phase 2 · Separation)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Wraps the IDE.api calls with mobile-specific defaults.
 *  This file loads AFTER api.js and BEFORE mobile-shell.js.
 *
 *  What it does:
 *    • Extends default timeout to 30s (mobile networks are slower)
 *    • Adds touch-friendly retry logic
 *    • Marks all requests with a mobile platform header
 *    • Provides a mobile-specific toast duration
 *    • Reports accurate hardware specs (via Android bridge when available)
 *
 *  EXPOSES: window.IDE.mobileBridge
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
  'use strict';

  var U = window.IDE && window.IDE.utils;
  if (!U) return;

  /* ═══════════════════════════════════════════════════════════════
     MOBILE DEFAULTS
  ═══════════════════════════════════════════════════════════════ */
  var MOBILE_DEFAULTS = {
    timeoutMs: 30000,
    retryCount: 2,
    retryDelayMs: 1000,
    toastDuration: 2500,
  };

  /* ═══════════════════════════════════════════════════════════════
     WRAP THE API LAYER
  ═══════════════════════════════════════════════════════════════ */
  var originalApi = window.IDE.api;
  if (originalApi) {
    window.IDE._originalApi = originalApi;
  }

  /* ═══════════════════════════════════════════════════════════════
     MOBILE TOAST WRAPPER
  ═══════════════════════════════════════════════════════════════ */
  var originalToast = U.toast;
  if (originalToast) {
    U.toast = function (message, type, duration) {
      var mobileDuration = duration || MOBILE_DEFAULTS.toastDuration;
      originalToast.call(U, message, type, mobileDuration);
    };
  }

  /* ═══════════════════════════════════════════════════════════════
     NETWORK STATUS HELPER
  ═══════════════════════════════════════════════════════════════ */
  function isOnline() {
    return navigator.onLine !== false;
  }

  /* ═══════════════════════════════════════════════════════════════
     FETCH WITH MOBILE DEFAULTS
  ═══════════════════════════════════════════════════════════════ */
  function mobileFetch(url, options) {
    options = options || {};
    if (!options.timeout) {
      options.timeout = MOBILE_DEFAULTS.timeoutMs;
    }
    var retries = options.retries || MOBILE_DEFAULTS.retryCount;
    var attempt = 0;

    function doFetch() {
      attempt++;
      var controller = new AbortController();
      var timeoutId = setTimeout(function () {
        controller.abort();
      }, options.timeout);

      var fetchOptions = {
        method: options.method || 'GET',
        headers: Object.assign({}, options.headers, {
          'X-Quirky-Platform': 'mobile',
        }),
        signal: controller.signal,
      };
      if (options.body) {
        fetchOptions.body = options.body;
      }
      return fetch(url, fetchOptions)
        .then(function (response) {
          clearTimeout(timeoutId);
          return response;
        })
        .catch(function (err) {
          clearTimeout(timeoutId);
          if (attempt <= retries && err.name !== 'AbortError') {
            return new Promise(function (resolve) {
              setTimeout(function () {
                resolve(doFetch());
              }, MOBILE_DEFAULTS.retryDelayMs * attempt);
            });
          }
          throw err;
        });
    }
    return doFetch();
  }

  /* ═══════════════════════════════════════════════════════════════
     HARDWARE TIER REPORT (dynamic file limit)
     ═══════════════════════════════════════════════════════════════
     ★ UPDATED: Uses the Android HardwareBridge when available.
     navigator.deviceMemory caps at 8 GB in WebView/Chrome, so a
     16 GB phone would incorrectly report 8 GB. The Android bridge
     reads ActivityManager.MemoryInfo.totalMem which gives the real
     value. Falls back to browser APIs when running in a normal
     browser (desktop Laragon, etc.).
  ═══════════════════════════════════════════════════════════════ */
  function reportDeviceTier() {
    try {
      // ── Path A: Running inside the Android APK ──────────────
      // window.AndroidHardware is injected by MainActivity.java
      // via webView.addJavascriptInterface(...)
      if (typeof window.AndroidHardware !== 'undefined' &&
        typeof window.AndroidHardware.getDeviceSpecs === 'function') {

        var specsJson = window.AndroidHardware.getDeviceSpecs();
        var specs;
        try {
          specs = JSON.parse(specsJson);
        } catch (parseErr) {
          specs = null;
        }

        if (specs && !specs.error && specs.ramMb) {
          var ramGb = specs.ramMb / 1024;
          var cores = specs.cores || 0;
          var storageMb = specs.storageMb || 0;
          var tier = specs.tier || 'medium';

          // Store full hardware profile for the Settings page
          window.IDE_CONFIG = window.IDE_CONFIG || {};
          window.IDE_CONFIG.hardware = {
            ramGb: ramGb,
            ramMb: specs.ramMb,
            cores: cores,
            storageMb: storageMb,
            tier: tier,
            source: 'android-bridge'
          };
          window.IDE_CONFIG.maxFileSize = window.IDE_CONFIG.maxFileSize || 524288;

          // Send to server so it can compute the file limit
          sendDeviceTier(ramGb, cores, storageMb * 1024 * 1024, tier);
          return;
        }
      }

      // ── Path B: Browser fallback (desktop / old WebView) ────
      var ram = (typeof navigator.deviceMemory === 'number') ? navigator.deviceMemory : 0;
      var browserCores = navigator.hardwareConcurrency || 0;

      window.IDE_CONFIG = window.IDE_CONFIG || {};
      window.IDE_CONFIG.hardware = {
        ramGb: ram,
        ramMb: ram > 0 ? Math.round(ram * 1024) : 0,
        cores: browserCores,
        storageMb: 0,
        tier: 'unknown',
        source: 'browser-api'
      };

      if (navigator.storage && navigator.storage.estimate) {
        navigator.storage.estimate().then(
          function (est) {
            var quota = (est && est.quota) ? Math.round(est.quota) : 0;
            sendDeviceTier(ram, browserCores, quota, '');
          },
          function () { sendDeviceTier(ram, browserCores, 0, ''); }
        );
      } else {
        sendDeviceTier(ram, browserCores, 0, '');
      }
    } catch (e) { }
  }

  /**
   * POST hardware data to the server and apply the returned limit.
   */
  function sendDeviceTier(ramGb, cores, quotaBytes, tier) {
    var oldTier = (window.IDE_CONFIG && window.IDE_CONFIG.deviceTier)
      ? window.IDE_CONFIG.deviceTier
      : 'high';

    fetch('index.php?api=mobile.device-tier', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({
        deviceMemory: ramGb,
        cores: cores,
        storageQuota: quotaBytes,
        tier: tier
      })
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.ok && j.data) {
          if (window.IDE_CONFIG) {
            if (j.data.limit) {
              window.IDE_CONFIG.maxFileSize = j.data.limit;
            }

            if (j.data.deviceTier) {
              window.IDE_CONFIG.deviceTier = j.data.deviceTier;
            }

            if (j.data.policy) {
              window.IDE_CONFIG.devicePolicy = j.data.policy;
            }

            // Merge any hardware info the server echoes back
            if (j.data.hardware) {
              window.IDE_CONFIG.hardware = window.IDE_CONFIG.hardware || {};
              for (var k in j.data.hardware) {
                window.IDE_CONFIG.hardware[k] = j.data.hardware[k];
              }
            }

            // If the server computed a different tier than the one
            // the page booted with, reload once so the whole IDE
            // re-renders with the correct restrictions.
            if (j.data.deviceTier && oldTier !== j.data.deviceTier) {
              try {
                var reloadKey = 'quirky.ide.mobile.deviceTierReload';

                if (window.sessionStorage && window.sessionStorage.getItem(reloadKey) !== j.data.deviceTier) {
                  window.sessionStorage.setItem(reloadKey, j.data.deviceTier);
                  setTimeout(function () {
                    window.location.reload();
                  }, 200);
                }
              } catch (e) { }
            } else {
              try {
                if (window.sessionStorage) {
                  window.sessionStorage.removeItem('quirky.ide.mobile.deviceTierReload');
                }
              } catch (e) { }
            }
          }
        }
      })
      .catch(function () { });
  }

  reportDeviceTier();

  /* ═══════════════════════════════════════════════════════════════
     EXPOSE
  ═══════════════════════════════════════════════════════════════ */
  window.IDE = window.IDE || {};
  window.IDE.mobileBridge = {
    defaults: MOBILE_DEFAULTS,
    isOnline: isOnline,
    fetch: mobileFetch,
    originalToast: originalToast,
    reportDeviceTier: reportDeviceTier
  };
})();