package com.quirky.ide;

import android.Manifest;
import android.app.Activity;
import android.app.AlertDialog;
import android.content.ContentValues;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.drawable.GradientDrawable;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.os.Message;
import android.os.SystemClock;
import android.os.Handler;
import android.os.Looper;
import android.view.MotionEvent;
import android.provider.MediaStore;
import android.provider.Settings;
import android.database.Cursor;
import android.speech.RecognitionListener;
import android.speech.RecognizerIntent;
import android.speech.SpeechRecognizer;
import android.speech.tts.TextToSpeech;
import android.speech.tts.UtteranceProgressListener;
import org.json.JSONArray;
import org.json.JSONObject;
import android.util.Log;
import android.view.Gravity;
import android.view.HapticFeedbackConstants;
import android.view.KeyEvent;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.ConsoleMessage;
import android.webkit.JavascriptInterface;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.FrameLayout;
import android.widget.ImageButton;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;

import java.io.File;
import java.io.FileOutputStream;
import java.util.ArrayList;
import java.util.List;
import java.io.IOException;
import java.io.InputStream;
import java.net.InetSocketAddress;
import java.net.Socket;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.TimeUnit;
import java.util.zip.ZipEntry;
import java.util.zip.ZipInputStream;

public class MainActivity extends Activity {
    private WebView webView;
    private ProgressBar progressBar;
    private TextView statusText;
    private Process phpProcess;
    private final String PHP_HOST = "127.0.0.1";
    private final int PHP_PORT = 8080;
    private final int PHP_STREAM_PORT = 8081;
    private Process streamProcess;
    private final StringBuilder serverOutput = new StringBuilder();

    // ══ v10: REAL pop-out windows (preview "open in tab", target=_blank links)
    // Before this, window.open navigated the SAME WebView (no multi-window
    // support, no WebChromeClient) and hardware BACK reloaded the whole IDE,
    // destroying every open tab/unsaved buffer. Now _blank opens a CHILD
    // WebView in an overlay above the untouched IDE, with a floating ⌂ HOME
    // chip; BACK closes the child first. Zero permissions needed — this all
    // lives inside the activity's own view hierarchy.
    private WebView popoutView;
    private FrameLayout popoutOverlay;
    private View popoutHomeChip;
    private Handler popoutHandler;
    private Runnable chipDimmer;

    // ══ v10: fullscreen <video> support (WebChromeClient custom view)
    private View customVideoView;
    private WebChromeClient.CustomViewCallback customVideoCallback;
    private FrameLayout fullscreenVideoHolder;

    // ══ v10: <input type=file> chooser plumbing
    private ValueCallback<Uri[]> pendingFilePathCallback;
    private static final int FILE_CHOOSER_REQ = 4243; // ≠ STORAGE_REQ_CODE

    // ★ Explorer import / dynamic-workspace picker request codes
    private static final int IMPORT_FILE_REQ    = 4244;
    private static final int IMPORT_TREE_REQ    = 4245;
    private static final int WORKSPACE_TREE_REQ = 4246;
    private static final int EXPORT_TREE_REQ    = 4247;

    // IME height push throttle (see setupImeInsetBridge)
    private long lastImePushMs = 0;

    // The Termux-style "prefix" — our private toolbox folder.
    // Downloaded packages (git, python, ...) get installed here.
    private File prefixDir;

    // New field alongside prefixDir
    private File appLibDir;

    // ── Storage permission (Termux-style setup-storage) ─────────────
    // Request code that identifies OUR permission popup result.
    private static final int STORAGE_REQ_CODE = 4242;
    // True while we wait for the user to come back from the Android
    // "All files access" settings page (Android 11+).
    private boolean storageRequestPending = false;

    // ── Voice (v11): Text-To-Speech + Speech-To-Text bridges ─────────
    private TextToSpeech tts;
    private volatile boolean ttsReady = false;
    private SpeechRecognizer speechRecognizer;
    private volatile boolean sttListening = false;
    private static final int MIC_REQ_CODE = 4248; // next free code after 4242–4247

    // ── Phase C: bundled toolbox assets ──────────────────────────────
    // These files ship INSIDE the app itself (app/src/main/assets/toolbox/)
    // and get copied into the prefix every time the app starts. Pkg.php no
    // longer tries to download any of these from the internet:
    //   - busybox: the multi-tool binary used to extract .deb packages
    //   - cacert.pem: the CA certificate bundle, used so cURL/PHP can
    //     verify HTTPS connections to the Termux package repository
    //   - ptyrun: pty helper for the terminal
    //   - zstd: static zstd decompressor binary. Needed because busybox
    //     has no real zstd support, and Termux's own 'zstd' package is
    //     ITSELF zstd-compressed — so it can never be installed via
    //     `pkg install zstd` on a device that doesn't already have zstd
    //     (a chicken-and-egg problem). Packages like git and curl are
    //     shipped as .tar.zst and are UNINSTALLABLE without this file.
    //     See Pkg.php's extractDeb() for the PHP side of this fix.
    private static final String ASSET_BUSYBOX = "toolbox/busybox";
    private static final String ASSET_CACERT  = "toolbox/cacert.pem";
    private static final String ASSET_PTYRUN  = "toolbox/ptyrun";
    private static final String ASSET_ZSTD    = "toolbox/zstd";
    // ★ NEW: static xz decompressor binary. Needed because busybox's
    // xz applet is a dummy that outputs 0 bytes. Packages like git,
    // less, ncurses, and pcre2 are shipped as .tar.xz and are
    // UNINSTALLABLE without this file.
    private static final String ASSET_XZ      = "toolbox/xz";

    // ── HOTFIX D-2 (v8): exact keyboard height bridge ────────────────
    // The web page cannot me asure the virtual keyboard precisely on every
    // device (the browser's "visual viewport" is a few pixels off on some
    // phones, which made the helper strip sink behind the keyboard).
    // Android KNOWS the exact keyboard height (the "IME inset"), so we
    // measure it here, in physical pixels, and let the JavaScript side
    // read it any time through window.QuirkyIme.getImeHeight().
    private volatile int lastImePx = 0;

    // ══ v14: dynamic speech engine (offline TTS/STT, downloaded models) ══
    private SpeechManager speechManager;
    private File speechDir;
    private static final String ASSET_SPEECH_CATALOG = "toolbox/speech-catalog.json";
    private static final int SPEECH_PKG_REQ = 4249;   // next free code after 4248

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);
        webView = findViewById(R.id.webView);
        progressBar = findViewById(R.id.progressBar);
        statusText = findViewById(R.id.statusText);
        hideSystemBars();
        setupWebView();
        setupImeInsetBridge();
        setupStorageBridge();
        setupHapticBridge();
        setupFilesBridge();
        setupTtsBridge();
        setupSttBridge();

        // ★ v14: offline speech layer. Files live in files/speech/ — written by
        // Speech.php (PHP side), executed by SpeechManager (this side).
        speechDir = new File(getFilesDir(), "speech");
        speechDir.mkdirs();
        ensureSpeechCatalog();
        speechManager = new SpeechManager(this, speechDir, this::dispatchWebEvent);
        setupSpeechBridge();
        // Ask for phone-storage access ONCE, on the very first launch
        // after installation (like Termux's termux-setup-storage).
        maybePromptStoragePermissionOnFirstLaunch();

        new Thread(this::startIde).start();
    }

    // ── HOTFIX D-2: measure the keyboard and expose it to JavaScript ──
    private void setupImeInsetBridge() {
        // 1) The JS side can pull the current keyboard height (physical px).
        webView.addJavascriptInterface(new Object() {
            @JavascriptInterface
            public int getImeHeight() {
                return lastImePx;
            }
        }, "QuirkyIme");

        // 2) Android tells us whenever the keyboard (IME) inset changes.
        ViewCompat.setOnApplyWindowInsetsListener(webView, (v, insets) -> {
            lastImePx = insets.getInsets(WindowInsetsCompat.Type.ime()).bottom;
            pushImeToWeb(lastImePx);
            return insets;
        });

        // 3) Safety net: also re-read the inset on every layout pass, so we
        //    never miss a keyboard show/hide on any Android version.
        webView.getViewTreeObserver().addOnGlobalLayoutListener(() -> {
            WindowInsetsCompat insets = ViewCompat.getRootWindowInsets(webView);
            if (insets != null) {
                lastImePx = insets.getInsets(WindowInsetsCompat.Type.ime()).bottom;
                pushImeToWeb(lastImePx);
            }
        });
    }

    /**
     * ★ v10: PUSH keyboard height to the page instead of pull-only.
     * getImeHeight() polling stays for compatibility; this throttled push
     * ('quirky-ime' CustomEvent, physical px — JS divides by devicePixelRatio)
     * lets panels react instantly without a polling stabilizer loop.
     */
    private void pushImeToWeb(int heightPx) {
        long now = SystemClock.elapsedRealtime();
        if (now - lastImePushMs < 50) return; // throttle to ~20 Hz
        lastImePushMs = now;
        dispatchWebEvent("quirky-ime", "{ heightPx: " + heightPx + " }");
    }

    // ═══════════════════════════════════════════════════════════════
    // STORAGE PERMISSION — Termux-style "setup-storage"
    // ═══════════════════════════════════════════════════════════════

    /**
     * Exposes storage status/request to the web IDE as
     * window.QuirkyStorage — the same pattern as the QuirkyIme bridge.
     */
    private void setupStorageBridge() {
        webView.addJavascriptInterface(new Object() {
            @JavascriptInterface
            public boolean hasAccess() {
                return hasStorageAccess();
            }

            @JavascriptInterface
            public void requestAccess() {
                runOnUiThread(() -> requestStorageAccess());
            }

            @JavascriptInterface
            public String getSharedPath() {
                // The same folder Termux mounts as ~/storage/shared
                return hasStorageAccess()
                        ? Environment.getExternalStorageDirectory().getAbsolutePath()
                        : "";
            }
        }, "QuirkyStorage");
    }

    /** True when the app can currently read/write the shared storage. */
    private boolean hasStorageAccess() {
        if (Build.VERSION.SDK_INT >= 30) {
            // Android 11+ : the special "All files access" flag.
            try {
                return Environment.isExternalStorageManager();
            } catch (Exception e) {
                return false;
            }
        }
        // Android 6–10 : the classic runtime permission.
        return checkSelfPermission(Manifest.permission.READ_EXTERNAL_STORAGE)
                == PackageManager.PERMISSION_GRANTED;
    }

    /**
     * ★ v10: haptic feedback bridge — window.QuirkyHaptic.tick(kind).
     * kind: 'longpress' | 'key' | 'reject'. Feature-detect on the JS side
     * (window.QuirkyHaptic may be absent in plain browsers).
     */
    private void setupHapticBridge() {
        webView.addJavascriptInterface(new Object() {
            @JavascriptInterface
            public void tick(String kind) {
                int id;
                if ("key".equals(kind)) {
                    id = HapticFeedbackConstants.VIRTUAL_KEY;
                } else if ("reject".equals(kind)) {
                    id = HapticFeedbackConstants.CONTEXT_CLICK;
                } else {
                    id = HapticFeedbackConstants.LONG_PRESS;
                }
                runOnUiThread(() -> {
                    if (webView != null) webView.performHapticFeedback(id);
                });
            }
        }, "QuirkyHaptic");
    }

    /**
     * First-launch prompt. A SharedPreferences flag remembers that we
     * asked, so this dialog appears only ONCE — right after install.
     * (The user can still grant later via window.QuirkyStorage.)
     */
    private void maybePromptStoragePermissionOnFirstLaunch() {
        if (hasStorageAccess()) return; // already granted somehow
        android.content.SharedPreferences prefs =
                getSharedPreferences("quirky_prefs", MODE_PRIVATE);
        if (prefs.getBoolean("storage_prompt_done", false)) return;

        runOnUiThread(() -> new AlertDialog.Builder(this)
                .setTitle("Access your phone files?")
                .setMessage("Quirky IDE can open and save files in your "
                        + "phone's shared storage (Downloads, Documents, "
                        + "DCIM, ...)\n\n"
                        + "Nothing is touched without your approval.")
                .setPositiveButton("Allow", (d, w) -> {
                    markStoragePromptDone();
                    requestStorageAccess();
                })
                .setNegativeButton("Not now", (d, w) -> {
                    markStoragePromptDone();
                    d.dismiss();
                })
                .setCancelable(false)
                .show());
    }

    private void markStoragePromptDone() {
        getSharedPreferences("quirky_prefs", MODE_PRIVATE)
                .edit()
                .putBoolean("storage_prompt_done", true)
                .apply();
    }

    /** Opens the correct permission screen for this Android version. */
    private void requestStorageAccess() {
        if (Build.VERSION.SDK_INT >= 30) {
            // Android 11+ : the user must flip a switch in Settings.
            storageRequestPending = true;
            try {
                startActivity(new Intent(
                        Settings.ACTION_MANAGE_APP_ALL_FILES_ACCESS_PERMISSION,
                        Uri.parse("package:" + getPackageName())));
            } catch (Exception e) {
                // A few rare devices lack the per-app page — use the general one.
                startActivity(new Intent(
                        Settings.ACTION_MANAGE_ALL_FILES_ACCESS_PERMISSION));
            }
        } else {
            // Android 6–10 : the normal popup.
            storageRequestPending = true;
            requestPermissions(new String[]{
                    Manifest.permission.READ_EXTERNAL_STORAGE,
                    Manifest.permission.WRITE_EXTERNAL_STORAGE
            }, STORAGE_REQ_CODE);
        }
    }

    /** Result of the Android 6–10 popup. */
    @Override
    public void onRequestPermissionsResult(int requestCode,
                                           String[] permissions,
                                           int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == STORAGE_REQ_CODE) {
            storageRequestPending = false;
            boolean granted = grantResults.length > 0
                    && grantResults[0] == PackageManager.PERMISSION_GRANTED;
            Toast.makeText(this,
                    granted ? "Storage access granted"
                            : "Storage access denied",
                    Toast.LENGTH_SHORT).show();
            notifyWebStorageState(granted);
        }
        if (requestCode == MIC_REQ_CODE) {
            boolean micGranted = grantResults.length > 0
                    && grantResults[0] == PackageManager.PERMISSION_GRANTED;
            Toast.makeText(this,
                    micGranted ? "Microphone access granted"
                            : "Microphone access denied",
                    Toast.LENGTH_SHORT).show();
            dispatchWebEvent("quirky-mic-changed", "{ granted: " + micGranted + " }");
        }
    }

    /** Tells the web IDE that the permission state changed. */
    private void notifyWebStorageState(boolean granted) {
        if (webView == null) return;
        webView.post(() -> webView.evaluateJavascript(
                "(function(){ try { window.dispatchEvent(new CustomEvent("
                        + "'quirky-storage-changed',"
                        + "{ detail: { granted: " + granted + " } }));"
                        + " } catch(e) {} })();", null));
    }

    private void hideSystemBars() {
        WindowCompat.setDecorFitsSystemWindows(getWindow(), false);
        WindowInsetsControllerCompat controller =
                WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
        if (controller != null) {
            controller.setSystemBarsBehavior(
                    WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE);
            controller.hide(WindowInsetsCompat.Type.systemBars());
        }
    }

    @Override
    public void onWindowFocusChanged(boolean hasFocus) {
        super.onWindowFocusChanged(hasFocus);
        if (hasFocus) {
            hideSystemBars();
        }
    }

    @Override
    protected void onResume() {
        super.onResume();
        // Explicitly resume the WebView so it re-activates its rendering
        // engine and fires the JS visibilitychange event properly.
        if (webView != null) {
            webView.onResume();
        }
        // Came back from the "All files access" settings page (Android 11+)?
        // Re-check whether the user flipped the switch on.
        if (storageRequestPending) {
            storageRequestPending = false;
            boolean granted = hasStorageAccess();
            Toast.makeText(this,
                    granted ? "Storage access granted"
                            : "Storage access not granted",
                    Toast.LENGTH_SHORT).show();
            notifyWebStorageState(granted);
        }
    }

    @Override
    protected void onPause() {
        // Pause the WebView when the app goes to background so it
        // properly suspends rendering instead of silently killing iframes.
        if (webView != null) {
            webView.onPause();
        }
        super.onPause();
    }

    private void setupWebView() {
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setAllowFileAccess(true);
        settings.setAllowContentAccess(true);
        settings.setDatabaseEnabled(true);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);

        // ★ v10: PIN text zoom. Without this the WebView follows the SYSTEM
        // font scale, so users with enlarged system fonts got broken/clipped
        // IDE layouts (pixel-tuned panels don't scale with text).
        settings.setTextZoom(100);

        // ★ DYNAMIC LOW-END STABILIZATION: Force software rendering on low-end devices
        // to prevent GPU OOM crashes (SIGSEGV in libwebviewchromium.so)
        HardwareBridge bridge = new HardwareBridge(this);
        String tier = bridge.getTierOnly();
        if ("low".equals(tier)) {
            webView.setLayerType(View.LAYER_TYPE_SOFTWARE, null);
            //settings.setDomStorageEnabled(false); // Saves RAM
        } else {
            webView.setLayerType(View.LAYER_TYPE_HARDWARE, null);
        }

        webView.addJavascriptInterface(bridge, "AndroidHardware");

        webView.setWebViewClient(new WebViewClient());

        // ══ v10: REAL pop-out windows + file chooser + fullscreen video + console.
        // setSupportMultipleWindows makes window.open(...,'_blank') reach
        // onCreateWindow instead of silently navigating THIS WebView away
        // (which is what made BACK reload and destroy the whole IDE).
        settings.setSupportMultipleWindows(true);
        settings.setJavaScriptCanOpenWindowsAutomatically(true);
        webView.setWebChromeClient(buildChromeClient());

        // ★ v10: workspace exports / generated files now actually download
        // (before: every download silently did nothing).
        webView.setDownloadListener((url, userAgent, contentDisposition, mimetype, contentLength) ->
                handleDownload(url, userAgent, contentDisposition, mimetype));

        webView.setVisibility(View.INVISIBLE);
    }

    // ═══════════════════════════════════════════════════════════════
    // v10 — WEB CHROME CLIENT (pop-outs, chooser, video, console)
    // ═══════════════════════════════════════════════════════════════

    private WebChromeClient buildChromeClient() {
        return new WebChromeClient() {

            @Override
            public boolean onCreateWindow(WebView view, boolean isDialog,
                                          boolean isUserGesture, Message resultMsg) {
                WebView child = new WebView(MainActivity.this);
                WebSettings s = child.getSettings();
                s.setJavaScriptEnabled(true);
                s.setDomStorageEnabled(true);
                s.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
                s.setTextZoom(100);
                child.setWebViewClient(new WebViewClient());
                child.setWebChromeClient(new WebChromeClient());
                child.setBackgroundColor(Color.parseColor("#0b0f17"));

                // Hand the child to the WebView BEFORE returning — required.
                WebView.WebViewTransport transport = (WebView.WebViewTransport) resultMsg.obj;
                transport.setWebView(child);
                resultMsg.sendToTarget();

                runOnUiThread(() -> showPopout(child));
                return true;
            }

            @Override
            public void onCloseWindow(WebView window) {
                dismissPopout();
            }

            @Override
            public boolean onShowFileChooser(WebView view,
                                             ValueCallback<Uri[]> callback,
                                             FileChooserParams params) {
                if (pendingFilePathCallback != null) {
                    pendingFilePathCallback.onReceiveValue(null);
                }
                pendingFilePathCallback = callback;
                try {
                    Intent intent = params.createIntent();
                    startActivityForResult(intent, FILE_CHOOSER_REQ);
                } catch (Exception e) {
                    pendingFilePathCallback = null;
                    Toast.makeText(MainActivity.this,
                            "File picker unavailable", Toast.LENGTH_SHORT).show();
                    return false;
                }
                return true;
            }

            @Override
            public void onShowCustomView(View view, CustomViewCallback callback) {
                if (customVideoView != null) {
                    callback.onCustomViewHidden();
                    return;
                }
                customVideoView = view;
                customVideoCallback = callback;
                fullscreenVideoHolder = new FrameLayout(MainActivity.this);
                fullscreenVideoHolder.setBackgroundColor(Color.BLACK);
                fullscreenVideoHolder.addView(view, new FrameLayout.LayoutParams(
                        ViewGroup.LayoutParams.MATCH_PARENT,
                        ViewGroup.LayoutParams.MATCH_PARENT));
                contentViewGroup().addView(fullscreenVideoHolder, new FrameLayout.LayoutParams(
                        ViewGroup.LayoutParams.MATCH_PARENT,
                        ViewGroup.LayoutParams.MATCH_PARENT));
                webView.setVisibility(View.GONE);
            }

            @Override
            public void onHideCustomView() {
                exitFullscreenVideo();
            }

            @Override
            public boolean onConsoleMessage(ConsoleMessage m) {
                Log.d("QuirkyJS", "[" + m.messageLevel() + "] " + m.message()
                        + " @" + (m.sourceId() != null ? m.sourceId() : "") + ":"
                        + m.lineNumber());
                return true;
            }

            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                // Only drive the loader during post-boot navigations.
                if (webView.getVisibility() == View.VISIBLE && newProgress < 100) {
                    progressBar.setVisibility(View.VISIBLE);
                    progressBar.setProgress(newProgress);
                } else {
                    progressBar.setVisibility(View.GONE);
                }
            }
        };
    }

    /** The activity's content frame — parent for our overlays. */
    private ViewGroup contentViewGroup() {
        return (ViewGroup) findViewById(android.R.id.content);
    }

    // ── Pop-out overlay ─────────────────────────────────────────────

    private void showPopout(WebView child) {
        if (popoutOverlay == null) {
            popoutOverlay = new FrameLayout(this) {
                @Override
                public boolean onInterceptTouchEvent(MotionEvent ev) {
                    wakeHomeChip(); // any interaction inside the pop-out wakes the HOME chip
                    return false;   // never steal the touch itself
                }
            };
            popoutOverlay.setBackgroundColor(Color.parseColor("#0b0f17"));
        }
        popoutOverlay.removeAllViews();

        popoutOverlay.addView(child, new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT));
        popoutView = child;

        popoutHomeChip = buildHomeChip();
        FrameLayout.LayoutParams chipLp = new FrameLayout.LayoutParams(dp(52), dp(52));
        chipLp.gravity = Gravity.BOTTOM | Gravity.END;
        chipLp.rightMargin = dp(18);
        chipLp.bottomMargin = dp(24);
        popoutOverlay.addView(popoutHomeChip, chipLp);
        popoutHomeChip.setOnClickListener(v -> dismissPopout());
        wakeHomeChip();

        View content = findViewById(R.id.webView);
        contentViewGroup().addView(popoutOverlay, new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT));
        if (content != null) content.setVisibility(View.GONE);
    }

    private View buildHomeChip() {
        ImageButton chip = new ImageButton(this);

        GradientDrawable bg = new GradientDrawable();
        bg.setShape(GradientDrawable.OVAL);
        bg.setColor(Color.parseColor("#f5a524"));

        chip.setBackground(bg);
        chip.setImageResource(android.R.drawable.ic_menu_myplaces);
        chip.setColorFilter(Color.parseColor("#0b0f17"));
        chip.setContentDescription("Back to IDE");
        chip.setElevation(dp(12));

        int pad = dp(14);
        chip.setPadding(pad, pad, pad, pad);

        return chip;
    }

    /** ★ HOME chip fades to 25% after 3 s idle; any touch (or re-open) wakes it. */
    private void wakeHomeChip() {
        if (popoutHomeChip == null) return;
        if (popoutHandler == null) popoutHandler = new Handler(Looper.getMainLooper());
        if (chipDimmer == null) chipDimmer = () -> {
            if (popoutHomeChip != null) {
                popoutHomeChip.animate().alpha(0.25f).setDuration(400).start();
            }
        };
        popoutHomeChip.animate().cancel();
        popoutHomeChip.setAlpha(1f);
        popoutHandler.removeCallbacks(chipDimmer);
        popoutHandler.postDelayed(chipDimmer, 3000);
    }

    private int dp(int value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }

    private void dismissPopout() {
        if (popoutOverlay != null && popoutOverlay.getParent() instanceof ViewGroup) {
            ((ViewGroup) popoutOverlay.getParent()).removeView(popoutOverlay);
        }
        if (popoutView != null) {
            popoutView.stopLoading();
            popoutView.destroy();
            popoutView = null;
        }
        if (popoutHandler != null && chipDimmer != null) {
            popoutHandler.removeCallbacks(chipDimmer);
        }
        popoutOverlay = null;
        popoutHomeChip = null;
        findViewById(R.id.webView).setVisibility(View.VISIBLE);
    }

    private void exitFullscreenVideo() {
        if (fullscreenVideoHolder != null && fullscreenVideoHolder.getParent() instanceof ViewGroup) {
            ((ViewGroup) fullscreenVideoHolder.getParent()).removeView(fullscreenVideoHolder);
        }
        if (customVideoView != null && customVideoView.getParent() instanceof ViewGroup) {
            ((ViewGroup) customVideoView.getParent()).removeView(customVideoView);
        }
        if (customVideoCallback != null) customVideoCallback.onCustomViewHidden();
        customVideoView = null;
        customVideoCallback = null;
        fullscreenVideoHolder = null;
        webView.setVisibility(View.VISIBLE);
    }

    // ── Downloads (workspace exports etc.) ──────────────────────────

    private void handleDownload(String url, String userAgent,
                                String contentDisposition, String mimetype) {
        final String name = URLUtil.guessFileName(url, contentDisposition, mimetype);
        /* ★ FIX (v11): the DownloadListener callback runs on the UI thread, and
           Android throws NetworkOnMainThreadException when a network request is
           made there (targetSdk 28). Everything now runs on a background thread;
           toasts go back to the UI thread via runOnUiThread(). */
        new Thread(() -> {
            try {
                byte[] payload;
                if (url.startsWith("data:")) {
                    int comma = url.indexOf(',');
                    payload = comma >= 0
                            ? android.util.Base64.decode(url.substring(comma + 1), android.util.Base64.DEFAULT)
                            : new byte[0];
                } else {
                    java.net.HttpURLConnection conn =
                            (java.net.HttpURLConnection) new java.net.URL(url).openConnection();
                    if (userAgent != null) conn.setRequestProperty("User-Agent", userAgent);
                    conn.setConnectTimeout(15000);
                    conn.setReadTimeout(30000);
                    try (java.io.InputStream is = conn.getInputStream()) {
                        java.io.ByteArrayOutputStream buf = new java.io.ByteArrayOutputStream();
                        byte[] chunk = new byte[16384];
                        int n;
                        while ((n = is.read(chunk)) > 0) buf.write(chunk, 0, n);
                        payload = buf.toByteArray();
                    }
                }
                String savedTo = saveDownloadPayload(name, payload, mimetype);
                runOnUiThread(() ->
                        Toast.makeText(this, "Saved: " + savedTo, Toast.LENGTH_LONG).show());
                dispatchWebEvent("quirky-download-finished",
                        "{ name: '" + name.replace("'", "\\'") + "' }");
            } catch (Exception e) {
                runOnUiThread(() ->
                        Toast.makeText(this, "Download failed: " + e.getMessage(),
                                Toast.LENGTH_LONG).show());
            }
        }).start();
    }

    /** ★ v11: save into the user's export folder when one is configured
     *  (Settings → Explorer), otherwise fall back to Downloads exactly like before. */
    private String saveDownloadPayload(String name, byte[] payload, String mimetype)
            throws IOException {
        String exportDir = getExportDirPref();
        if (!exportDir.isEmpty() && hasStorageAccess()) {
            File dir = new File(exportDir);
            if (dir.isDirectory() || dir.mkdirs()) {
                File out = uniqueDestination(dir, name);
                try (FileOutputStream fos = new FileOutputStream(out)) {
                    fos.write(payload);
                }
                return out.getAbsolutePath();
            }
        }
        if (Build.VERSION.SDK_INT >= 29) {
            ContentValues cv = new ContentValues();
            cv.put(MediaStore.Downloads.DISPLAY_NAME, name);
            cv.put(MediaStore.Downloads.MIME_TYPE,
                    mimetype != null ? mimetype : "application/octet-stream");
            Uri uri = getContentResolver().insert(
                    MediaStore.Downloads.EXTERNAL_CONTENT_URI, cv);
            try (java.io.OutputStream os = getContentResolver().openOutputStream(uri)) {
                os.write(payload);
            }
            return "Downloads/" + name;
        }
        File dir = getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS);
        if (dir == null) dir = getFilesDir();
        File out = new File(dir, name);
        try (FileOutputStream fos = new FileOutputStream(out)) {
            fos.write(payload);
        }
        return out.getAbsolutePath();
    }

    /* ── ★ v11: export folder preference (read/written by the web Settings) ── */
    private String getExportDirPref() {
        return getSharedPreferences("quirky_prefs", MODE_PRIVATE)
                .getString("export_dir", "");
    }

    private boolean setExportDirPref(String path) {
        android.content.SharedPreferences prefs =
                getSharedPreferences("quirky_prefs", MODE_PRIVATE);
        if (path == null || path.trim().isEmpty()) {
            prefs.edit().putString("export_dir", "").apply();
            return true;
        }
        String p = path.trim();
        if (!p.startsWith("/storage/")) return false;   // shared storage only
        if (!hasStorageAccess()) return false;          // permission not granted
        File dir = new File(p);
        if (!dir.isDirectory() && !dir.mkdirs()) return false;
        File probe = new File(dir, ".quirky_export_test");
        try (FileOutputStream fos = new FileOutputStream(probe)) {
            fos.write(1);
        } catch (Exception e) {
            return false;                               // not writable
        }
        probe.delete();
        prefs.edit().putString("export_dir", p).apply();
        return true;
    }

    /** Result of the export-folder picker (v11). */
    private void handleExportTreeResult(int resultCode, Intent data) {
        if (resultCode != RESULT_OK || data == null || data.getData() == null) {
            return; // cancelled
        }
        String raw = resolveTreeUriToPath(data.getData());
        if (raw == null) {
            dispatchPickedEvent("export-pick", new ArrayList<String>(), null,
                    "Pick a folder on device storage — cloud folders cannot be used");
            return;
        }
        List<String> paths = new ArrayList<>();
        paths.add(raw);
        dispatchPickedEvent("export-pick", paths, null, null);
    }

    /** Pushes a CustomEvent into the page (same idiom as notifyWebStorageState). */
    private void dispatchWebEvent(String eventName, String detailJsLiteral) {
        if (webView == null) return;
        webView.post(() -> webView.evaluateJavascript(
                "(function(){ try { window.dispatchEvent(new CustomEvent('"
                        + eventName + "', { detail: " + detailJsLiteral + " })); } catch(e) {} })();",
                null));
    }

    private void startIde() {
        try {
            updateStatus("Preparing workspace...");
            File filesDir = getFilesDir();
            File ideDir = new File(filesDir, "ide");
            // ★ v10: version-aware extract/refresh (preserves files/ide/workspace).
            ensureIdeAssets(ideDir);

            prefixDir = new File(filesDir, "usr");
            new File(prefixDir, "bin").mkdirs();
            new File(prefixDir, "lib").mkdirs();
            new File(prefixDir, "tmp").mkdirs();
            new File(prefixDir, "var/lib/pkg").mkdirs();
            new File(prefixDir, "etc/tls").mkdirs();

            // Separate from prefixDir/lib on purpose — see copyNativeLibsToAppLibDir().
            appLibDir = new File(filesDir, "applibs");
            appLibDir.mkdirs();

            updateStatus("Setting up toolbox (busybox, CA certificates)...");
            ensureBundledToolbox(prefixDir);

            // ★ ADD THIS LINE:
            cleanupPrefixSpace(prefixDir);

            updateStatus("Installing shared libraries...");
            copyNativeLibsToAppLibDir(appLibDir);

            String nativeLibDir = getApplicationInfo().nativeLibraryDir;
            File phpBin = new File(nativeLibDir, "libphp.so");
            if (!phpBin.exists()) {
                showError("PHP binary not found at:\n" + phpBin.getAbsolutePath()
                        + "\nMake sure libphp.so (and its dependency .so files) are in "
                        + "app/src/main/jniLibs/arm64-v8a/");
                return;
            }

            File tmpDir = new File(getCacheDir(), "phptmp");
            tmpDir.mkdirs();
            File phpIni = ensurePhpIni(filesDir, nativeLibDir, tmpDir);

            updateStatus("Testing PHP binary...");
            String diagResult = runDiagnostic(phpBin, nativeLibDir, phpIni, tmpDir);
            if (diagResult != null) {
                showError("PHP failed to start:\n" + diagResult);
                return;
            }

            // ★ Calculate tier BEFORE starting servers to pass to PHP environment
            // Inside startIde(), right before starting the servers:
            HardwareBridge bridge = new HardwareBridge(this);
            String deviceTier = bridge.getTierOnly();

            updateStatus("Starting main server on port " + PHP_PORT + "...");
            startPhpServer(ideDir, phpBin, nativeLibDir, phpIni, tmpDir, deviceTier); // ★ PASS TIER

            updateStatus("Starting stream server on port " + PHP_STREAM_PORT + "...");
            startStreamServer(ideDir, phpBin, nativeLibDir, phpIni, tmpDir, deviceTier); // ★ PASS TIER

            updateStatus("Waiting for servers to respond...");
            waitForServer();

            runOnUiThread(() -> {
                progressBar.setVisibility(View.GONE);
                statusText.setVisibility(View.GONE);
                webView.setVisibility(View.VISIBLE);
                // ★ Pass the tier to the PHP backend via URL parameter
                webView.loadUrl("http://" + PHP_HOST + ":" + PHP_PORT + "/?quirky_app=1&device_tier=" + deviceTier);
            });
        } catch (Exception e) {
            showError("Startup error:\n" + e.getMessage());
        }
    }

    /**
     * ★ NEW (v10): version-aware IDE asset refresh.
     *
     * Old behavior extracted ide.zip ONLY when files/ide was missing, so
     * shipping newer web code inside the APK never reached devices that
     * already had an older extraction (users had to "clear app data",
     * which also nuked their workspace). The toolbox already solved this
     * with a .toolbox_version marker — this does the same for the IDE:
     *
     *   • files/ide/.bundle_version stores the VERSION_CODE that produced
     *     the current extraction.
     *   • On mismatch (or missing marker), we re-extract — but FIRST we
     *     move files/ide/workspace aside and restore it afterwards,
     *     because config.php keeps the user's projects INSIDE the docroot
     *     (files/ide/workspace on device).
     */
    private static final String BUNDLE_MARKER = ".bundle_version";

    private void ensureIdeAssets(File ideDir) throws IOException {
        int currentVersion = BuildConfig.VERSION_CODE;
        File marker = new File(ideDir, BUNDLE_MARKER);
        int installed = readVersionMarker(marker);

        boolean needsExtract;
        if (!ideDir.exists() || !marker.isFile()) {
            // First install OR legacy extraction from before markers existed:
            // only treat as stale when the dir exists without any marker AND
            // the app has been updated at least once past the shipping one.
            needsExtract = !ideDir.exists()
                    || installed != currentVersion;
        } else {
            needsExtract = installed != currentVersion;
        }

        if (!needsExtract) return;

        // 1) Relocate the user's workspace out of the blast radius.
        File ws = new File(ideDir, "workspace");
        File wsBackup = null;
        boolean movedWorkspace = false;
        if (ws.exists()) {
            wsBackup = new File(getCacheDir(), "ide-ws-migrate");
            deleteRecursive(wsBackup);
            if (ws.renameTo(wsBackup)) {
                movedWorkspace = true;
            } else {
                // Rename can fail across mount boundaries — fall back to a copy.
                copyRecursive(ws, wsBackup);
                movedWorkspace = wsBackup.isDirectory();
            }
        }

        // 2) Wipe + re-extract.
        updateStatus("Updating IDE files...");
        deleteRecursive(ideDir);
        unzipAsset("ide.zip", ideDir);

        // 3) Restore workspace.
        if (movedWorkspace && wsBackup != null) {
            File restored = new File(ideDir, "workspace");
            if (!wsBackup.renameTo(restored)) {
                copyRecursive(wsBackup, restored);
                deleteRecursive(wsBackup);
            }
        }

        writeVersionMarker(marker, currentVersion);
    }

    /** Recursive copy used by ensureIdeAssets when rename isn't possible. */
    private void copyRecursive(File src, File dest) throws IOException {
        if (src.isDirectory()) {
            dest.mkdirs();
            File[] children = src.listFiles();
            if (children != null) {
                for (File c : children) copyRecursive(c, new File(dest, c.getName()));
            }
        } else {
            File parent = dest.getParentFile();
            if (parent != null && !parent.exists()) parent.mkdirs();
            copyFile(src, dest);
        }
    }
    /**
     * Copies the bundled busybox binary, CA certificate bundle, ptyrun
     * helper, and static zstd binary from the app's assets into the
     * prefix (files/usr/...), and makes the executables runnable.
     * Runs on every launch, but skips the actual copy if the
     * destination file already matches the current app version — so it's
     * cheap after the first run, and automatically re-copies if you ship
     * an updated busybox/cacert.pem/zstd in a future app update.
     */
    private void ensureBundledToolbox(File prefixDir) {
        int currentVersion = BuildConfig.VERSION_CODE;
        File versionMarker = new File(prefixDir, ".toolbox_version");
        int installedVersion = readVersionMarker(versionMarker);
        boolean needsRefresh = (installedVersion != currentVersion);

        File busybox = new File(prefixDir, "bin/busybox");
        File cacert  = new File(prefixDir, "etc/tls/cacert.pem");
        File ptyrun  = new File(prefixDir, "bin/ptyrun");
        File zstdStatic = new File(prefixDir, "bin/zstd-static");
        // ★ NEW: static xz binary. Named "xz-static" for the same reason.
        File xzStatic = new File(prefixDir, "bin/xz-static");

        try {
            // ★ HOTFIX (busybox-clobber self-heal): busybox is the cornerstone
            // binary — every shortcut symlink (tar, xz, curl, less, ...) points
            // at it. An old Pkg.php bug allowed a package install (curl, less,
            // wget, vim...) to overwrite busybox through one of those shortcuts.
            // Re-copying busybox on EVERY launch is a cheap ~1 MB copy that keeps
            // the toolbox pristine and instantly repairs already-damaged devices.
            copyAsset(ASSET_BUSYBOX, busybox);
            busybox.setExecutable(true, false);
            busybox.setReadable(true, false);
            if (needsRefresh || !cacert.isFile()) {
                copyAsset(ASSET_CACERT, cacert);
            }
            if (needsRefresh || !ptyrun.isFile()) {
                copyAsset(ASSET_PTYRUN, ptyrun);
                ptyrun.setExecutable(true, false);
                ptyrun.setReadable(true, false);
            }
            // ★ NEW: copy the static zstd binary the same way, but don't
            // treat it as fatal if the asset is missing — packages that
            // aren't zstd-compressed (most of them) work fine without it.
            try {
                if (needsRefresh || !zstdStatic.isFile()) {
                    copyAsset(ASSET_ZSTD, zstdStatic);
                    zstdStatic.setExecutable(true, false);
                    zstdStatic.setReadable(true, false);
                }
            } catch (IOException zstdEx) {
                updateStatus("Note: no bundled zstd binary found (curl install will be unavailable until app/src/main/assets/toolbox/zstd is added)");
            }

            // ★ NEW: copy the static xz binary. This is the exact fix for
            // the "git", "less", "ncurses", and "pcre2" extraction failures
            // you saw in your terminal log!
            try {
                if (needsRefresh || !xzStatic.isFile()) {
                    copyAsset(ASSET_XZ, xzStatic);
                    xzStatic.setExecutable(true, false);
                    xzStatic.setReadable(true, false);
                }
            } catch (IOException xzEx) {
                updateStatus("Note: no bundled xz binary found (git/less/ncurses install will be unavailable until app/src/main/assets/toolbox/xz is added)");
            }

            writeVersionMarker(versionMarker, currentVersion);
        } catch (IOException e) {
            updateStatus("Warning: could not set up bundled toolbox (" + e.getMessage() + ")");
        }
    }

    /**
     * Copies ALL native .so libraries from the APK's jniLibs directory into
     * the Termux-style prefix lib folder (files/usr/lib/) with the correct
     * SONAME filenames that Termux packages expect.
     * Without this, "pkg install python" (or any package) fails with:
     *   "libbusybox.so.1.38.0 not found (needed by main executable)"
     * Termux packages record the SONAME (e.g. "libbusybox.so.1.38.0") in
     * their ELF headers. The dynamic linker looks for a FILE with that exact
     * name in LD_LIBRARY_PATH. Our jniLibs only has "libbusybox.so", so we
     * must also create the versioned filename.
     *
     * Called once per app version (guarded by the .toolbox_version marker).
     */
    private void copyNativeLibsToAppLibDir(File prefixDir) {
        if (!appLibDir.exists()) appLibDir.mkdirs();

        String nativeLibDir = getApplicationInfo().nativeLibraryDir;
        File nativeDir = new File(nativeLibDir);
        if (!nativeDir.exists() || !nativeDir.isDirectory()) return;

        // Map of base filename → SONAME that Termux packages expect.
        // These come from Termux's actual package SONAMEs (readelf -d).
        // If a library isn't in this map, it gets copied as-is (no version suffix).
        java.util.Map<String, String[]> sonameMap = new java.util.LinkedHashMap<>();
        sonameMap.put("libuuid.so", new String[]{"libuuid.so.1"});
        sonameMap.put("libbusybox.so",           new String[]{"libbusybox.so.1.38.0"});
        sonameMap.put("libz.so",                 new String[]{"libz.so.1"});
        sonameMap.put("libbz2.so",              new String[]{"libbz2.so.1.0"});
        sonameMap.put("liblzma.so",             new String[]{"liblzma.so.5"});
        sonameMap.put("libzstd.so",             new String[]{"libzstd.so.1"});
        sonameMap.put("libssl.so",              new String[]{"libssl.so.3"});
        sonameMap.put("libcrypto.so",           new String[]{"libcrypto.so.3"});
        sonameMap.put("libcurl.so",             new String[]{"libcurl.so.4"});
        sonameMap.put("libsqlite3.so",          new String[]{"libsqlite3.so.0"});
        sonameMap.put("libxml2.so",             new String[]{"libxml2.so.2"});
        sonameMap.put("libxslt.so",             new String[]{"libxslt.so.1"});
        sonameMap.put("libexslt.so",            new String[]{"libexslt.so.0"});
        sonameMap.put("libffi.so",              new String[]{"libffi.so.8"});
        sonameMap.put("libgmp.so",              new String[]{"libgmp.so.10"});
        sonameMap.put("libiconv.so",            new String[]{"libiconv.so.2"});
        sonameMap.put("libicudata.so",          new String[]{"libicudata.so.76"});
        sonameMap.put("libicuuc.so",            new String[]{"libicuuc.so.76"});
        sonameMap.put("libicui18n.so",          new String[]{"libicui18n.so.76"});
        sonameMap.put("libicuio.so",            new String[]{"libicuio.so.76"});
        sonameMap.put("libncursesw.so",         new String[]{"libncursesw.so.6"});
        sonameMap.put("libreadline.so",         new String[]{"libreadline.so.8"});
        sonameMap.put("libedit.so",             new String[]{"libedit.so.0"});
        sonameMap.put("libpcre2-8.so",          new String[]{"libpcre2-8.so.0"});
        sonameMap.put("libonig.so",             new String[]{"libonig.so.5"});
        sonameMap.put("libnghttp2.so",          new String[]{"libnghttp2.so.14"});
        sonameMap.put("libnghttp3.so",          new String[]{"libnghttp3.so.9"});
        sonameMap.put("libngtcp2.so",           new String[]{"libngtcp2.so.14"});
        sonameMap.put("libngtcp2_crypto_ossl.so", new String[]{"libngtcp2_crypto_ossl.so"});
        sonameMap.put("libssh2.so",             new String[]{"libssh2.so.1"});
        sonameMap.put("libtidy.so",             new String[]{"libtidy.so.58"});
        sonameMap.put("libzip.so",              new String[]{"libzip.so.5"});
        sonameMap.put("libcapstone.so",         new String[]{"libcapstone.so.5"});
        sonameMap.put("libandroid-support.so",  new String[]{"libandroid-support.so"});
        sonameMap.put("libc++_shared.so",       new String[]{"libc++_shared.so"});
        sonameMap.put("libresolv_wrapper.so",   new String[]{"libresolv_wrapper.so"});
        sonameMap.put("libdn2.so",              new String[]{"libidn2.so"});
        sonameMap.put("libandroid-posix-semaphore.so", new String[]{"libandroid-posix-semaphore.so"});

        File[] soFiles = nativeDir.listFiles((dir, name) -> name.endsWith(".so"));
        if (soFiles == null) return;

        for (File soFile : soFiles) {
            try {
                String name = soFile.getName();

                // 1) Always copy the base .so into prefix/lib/
                File dest = new File(appLibDir, name);
                if (!dest.exists() || dest.length() != soFile.length()) {
                    copyFile(soFile, dest);
                }

                // 2) Create versioned SONAME copies if we know them
                String[] sonames = sonameMap.get(name);
                if (sonames != null) {
                    for (String soname : sonames) {
                        if (soname.equals(name)) continue; // skip if same
                        File versionedDest = new File(appLibDir, soname);
                        if (!versionedDest.exists() || versionedDest.length() != soFile.length()) {
                            copyFile(soFile, versionedDest);
                        }
                    }
                }
            } catch (IOException e) {
                // Non-fatal: log and continue with next library
                updateStatus("Warning: could not copy " + soFile.getName());
            }
        }
    }

    /**
     * Simple file copy helper (InputStream → OutputStream).
     */
    private void copyFile(File src, File dest) throws IOException {
        File parent = dest.getParentFile();
        if (parent != null && !parent.exists()) parent.mkdirs();
        try (InputStream is = new java.io.FileInputStream(src);
             FileOutputStream fos = new FileOutputStream(dest)) {
            byte[] buffer = new byte[8192];
            int len;
            while ((len = is.read(buffer)) != -1) {
                fos.write(buffer, 0, len);
            }
        }
    }

    private int readVersionMarker(File marker) {
        if (!marker.isFile()) return -1;
        try (InputStream is = new java.io.FileInputStream(marker)) {
            byte[] buf = new byte[32];
            int len = is.read(buf);
            if (len <= 0) return -1;
            return Integer.parseInt(new String(buf, 0, len, StandardCharsets.UTF_8).trim());
        } catch (Exception e) {
            return -1;
        }
    }

    private void writeVersionMarker(File marker, int version) {
        try (FileOutputStream fos = new FileOutputStream(marker)) {
            fos.write(String.valueOf(version).getBytes(StandardCharsets.UTF_8));
        } catch (IOException ignored) {
        }
    }

    private void copyAsset(String assetPath, File dest) throws IOException {
        File parent = dest.getParentFile();
        if (parent != null) parent.mkdirs();
        try (InputStream is = getAssets().open(assetPath);
             FileOutputStream fos = new FileOutputStream(dest)) {
            byte[] buffer = new byte[8192];
            int len;
            while ((len = is.read(buffer)) != -1) {
                fos.write(buffer, 0, len);
            }
        }
    }

    private File ensurePhpIni(File filesDir, String nativeLibDir, File tmpDir) throws IOException {
        File phpIni = new File(filesDir, "php.ini");
        String contents =
                "sys_temp_dir = \"" + tmpDir.getAbsolutePath() + "\"\n" +
                        "upload_tmp_dir = \"" + tmpDir.getAbsolutePath() + "\"\n" +
                        "session.save_path = \"" + tmpDir.getAbsolutePath() + "\"\n" +
                        "session.auto_start = 0\n" +
                        "session.use_strict_mode = 0\n" +
                        "display_errors = On\n" +
                        "error_reporting = E_ALL\n" +
                        "opcache.enable = 0\n" +
                        "opcache.enable_cli = 0\n";
        try (FileOutputStream fos = new FileOutputStream(phpIni)) {
            fos.write(contents.getBytes(StandardCharsets.UTF_8));
        }
        return phpIni;
    }

    private String runDiagnostic(File phpBin, String libDir, File phpIni, File tmpDir) {
        try {
            ProcessBuilder pb = new ProcessBuilder(
                    phpBin.getAbsolutePath(), "-c", phpIni.getAbsolutePath(), "-v"
            );
            String ldPath = appLibDir.getAbsolutePath() + ":" + libDir;
            pb.environment().put("QUIRKY_APPLIB", appLibDir.getAbsolutePath());
            pb.environment().put("LD_LIBRARY_PATH", ldPath);
            pb.environment().put("TMPDIR", tmpDir.getAbsolutePath());
            pb.environment().put("HOME", tmpDir.getAbsolutePath());
            // Phase B: tell PHP where the package toolbox lives.
            if (prefixDir != null) {
                pb.environment().put("QUIRKY_PREFIX", prefixDir.getAbsolutePath());
            }
            pb.redirectErrorStream(true);
            Process p = pb.start();
            InputStream is = p.getInputStream();
            byte[] buf = new byte[4096];
            StringBuilder sb = new StringBuilder();
            int len;
            while ((len = is.read(buf)) != -1) {
                sb.append(new String(buf, 0, len, StandardCharsets.UTF_8));
            }
            boolean finished = p.waitFor(10, TimeUnit.SECONDS);
            if (!finished) {
                p.destroyForcibly();
                return "php -v timed out (10s). Output so far:\n" + sb;
            }
            int exit = p.exitValue();
            if (exit != 0) {
                return "php -v exited with code " + exit + ":\n" + sb;
            }
            updateStatus("PHP OK: " + sb.toString().split("\n")[0]);
            Thread.sleep(500);
            return null;
        } catch (Exception e) {
            return "Exception running php -v: " + e.getMessage();
        }
    }

    private void startPhpServer(File ideDir, File phpBin, String libDir, File phpIni, File tmpDir, String deviceTier) throws IOException {
        ProcessBuilder pb = new ProcessBuilder(
                phpBin.getAbsolutePath(),
                "-c", phpIni.getAbsolutePath(),
                "-S", PHP_HOST + ":" + PHP_PORT,
                "-t", ideDir.getAbsolutePath(),
                "index.php"
        );
        pb.directory(ideDir);
        String ldPath = appLibDir.getAbsolutePath() + ":" + libDir;
        pb.environment().put("QUIRKY_APPLIB", appLibDir.getAbsolutePath());
        pb.environment().put("LD_LIBRARY_PATH", ldPath);
        pb.environment().put("TMPDIR", tmpDir.getAbsolutePath());
        pb.environment().put("HOME", tmpDir.getAbsolutePath());
        // ★ 8 workers = 8 simultaneous requests (fixes the "can't open files" bug)
        pb.environment().put("PHP_CLI_SERVER_WORKERS", "8");
        pb.environment().put("QUIRKY_DEVICE_TIER", deviceTier); // ★ Pass tier to PHP
        if (prefixDir != null) {
            pb.environment().put("QUIRKY_PREFIX", prefixDir.getAbsolutePath());
        }
        pb.environment().put("QUIRKY_SPEECH", speechDir.getAbsolutePath());
        // ★ INJECT TIER INTO PHP ENVIRONMENT
        pb.environment().put("QUIRKY_DEVICE_TIER", deviceTier);
        pb.redirectErrorStream(true);
        phpProcess = pb.start();

        new Thread(() -> {
            try {
                InputStream is = phpProcess.getInputStream();
                byte[] buffer = new byte[1024];
                int len;
                while ((len = is.read(buffer)) != -1) {
                    synchronized (serverOutput) {
                        serverOutput.append(new String(buffer, 0, len, StandardCharsets.UTF_8));
                        if (serverOutput.length() > 8000) {
                            serverOutput.delete(0, serverOutput.length() - 8000);
                        }
                    }
                }
            } catch (IOException ignored) {
            }
        }).start();
    }

    /**
     * Stream server: NO workers. This is the server that handles
     * terminal-stream, ai-stream, and other SSE endpoints.
     * Without workers, PHP's built-in server sends output in real time
     * (one line at a time, like Termux). With workers, it buffers
     * the entire response until the command finishes.
     */
    private void startStreamServer(File ideDir, File phpBin, String libDir, File phpIni, File tmpDir, String deviceTier) throws IOException {
        ProcessBuilder pb = new ProcessBuilder(
                phpBin.getAbsolutePath(),
                "-c", phpIni.getAbsolutePath(),
                "-S", PHP_HOST + ":" + PHP_STREAM_PORT,
                "-t", ideDir.getAbsolutePath(),
                "index.php"
        );
        pb.directory(ideDir);
        String ldPath = appLibDir.getAbsolutePath() + ":" + libDir;
        pb.environment().put("QUIRKY_APPLIB", appLibDir.getAbsolutePath());
        pb.environment().put("LD_LIBRARY_PATH", ldPath);
        pb.environment().put("TMPDIR", tmpDir.getAbsolutePath());
        pb.environment().put("HOME", tmpDir.getAbsolutePath());
        // ★ NO PHP_CLI_SERVER_WORKERS here — that's the whole point.
        // Without workers, flush() sends data to the browser immediately.
        if (prefixDir != null) {
            pb.environment().put("QUIRKY_PREFIX", prefixDir.getAbsolutePath());
        }
        pb.environment().put("QUIRKY_SPEECH", speechDir.getAbsolutePath());
        // ★ INJECT TIER INTO PHP ENVIRONMENT
        pb.environment().put("QUIRKY_DEVICE_TIER", deviceTier);
        pb.redirectErrorStream(true);
        streamProcess = pb.start();

        new Thread(() -> {
            try {
                InputStream is = streamProcess.getInputStream();
                byte[] buffer = new byte[1024];
                int len;
                while ((len = is.read(buffer)) != -1) {
                    // Drain output so the process doesn't block
                }
            } catch (IOException ignored) {
            }
        }).start();
    }

    private void waitForServer() throws Exception {
        int maxRetries = 60;
        for (int i = 0; i < maxRetries; i++) {
            if (phpProcess != null) {
                try {
                    int exit = phpProcess.exitValue();
                    String output;
                    synchronized (serverOutput) {
                        output = serverOutput.toString();
                    }
                    throw new Exception("PHP process exited with code " + exit
                            + ".\nOutput: " + (output.isEmpty() ? "(no output captured)" : output));
                } catch (IllegalThreadStateException e) {
                    // Still running — good
                }
            }
            // Check BOTH servers
            boolean mainUp = false;
            boolean streamUp = false;
            try (Socket socket = new Socket()) {
                socket.connect(new InetSocketAddress(PHP_HOST, PHP_PORT), 200);
                mainUp = true;
            } catch (IOException e) { }
            try (Socket socket = new Socket()) {
                socket.connect(new InetSocketAddress(PHP_HOST, PHP_STREAM_PORT), 200);
                streamUp = true;
            } catch (IOException e) { }
            if (mainUp && streamUp) return;
            Thread.sleep(500);
        }
        throw new Exception("Servers did not start within 30 seconds.");
    }

    private void unzipAsset(String zipName, File destDir) throws IOException {
        if (!destDir.exists()) destDir.mkdirs();
        try (InputStream is = getAssets().open(zipName);
             ZipInputStream zis = new ZipInputStream(is)) {
            ZipEntry ze;
            byte[] buffer = new byte[4096];
            while ((ze = zis.getNextEntry()) != null) {
                File newFile = new File(destDir, ze.getName());
                String destDirPath = destDir.getCanonicalPath();
                String destFilePath = newFile.getCanonicalPath();
                if (!destFilePath.startsWith(destDirPath + File.separator)) {
                    throw new IOException("Zip entry outside target: " + ze.getName());
                }
                if (ze.isDirectory()) {
                    newFile.mkdirs();
                } else {
                    new File(newFile.getParent()).mkdirs();
                    try (FileOutputStream fos = new FileOutputStream(newFile)) {
                        int len;
                        while ((len = zis.read(buffer)) > 0) {
                            fos.write(buffer, 0, len);
                        }
                    }
                }
                zis.closeEntry();
            }
        }
    }

    private void updateStatus(String msg) {
        runOnUiThread(() -> statusText.setText(msg));
    }

    private void showError(String msg) {
        runOnUiThread(() -> {
            progressBar.setVisibility(View.GONE);
            statusText.setText(msg);
            statusText.setTextColor(0xFFF87171);
            Toast.makeText(this, "Startup failed", Toast.LENGTH_LONG).show();
        });
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        if (phpProcess != null) {
            phpProcess.destroy();
        }
        if (streamProcess != null) {
            streamProcess.destroy();
        }
        // v10: tear down any open pop-out child WebView as well.
        if (popoutView != null) {
            popoutView.destroy();
            popoutView = null;
        }
        // v14: release offline speech resources (processes, AudioTrack, AudioRecord).
        if (speechManager != null) speechManager.release();
        // v11: release voice resources (TTS engine + recognizer).
        if (tts != null) {
            try { tts.stop(); } catch (Exception ignored) {}
            try { tts.shutdown(); } catch (Exception ignored) {}
            tts = null;
        }
        sttListening = false;
        if (speechRecognizer != null) {
            try { speechRecognizer.destroy(); } catch (Exception ignored) {}
            speechRecognizer = null;
        }
    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        if (keyCode == KeyEvent.KEYCODE_BACK) {

            // 1) Fullscreen video → exit video first.
            if (customVideoView != null) {
                exitFullscreenVideo();
                return true;
            }

            // 2) Pop-out window open → close ONLY the pop-out. The IDE
            //    underneath was never navigated away from, so nothing reloads
            //    and no state is lost. (This is the fix for "BACK closes the
            //    preview AND reboots the IDE".)
            if (popoutView != null) {
                dismissPopout();
                return true;
            }

            // 3) In-page history: give the page a chance to consume Back
            //    first (closing panels/overlays like a native app). The page
            //    exposes window.QuirkyBack.onBack() → 'handled' or ''.
            if (webView.canGoBack()) {
                final WebView wv = webView;
                wv.evaluateJavascript(
                        "(function(){try{return (window.QuirkyBack&&typeof QuirkyBack.onBack==='function')?(''+QuirkyBack.onBack()):'';}catch(e){return '';}})()",
                        v -> {
                            String r = v == null ? "\"\"" : v.trim();
                            if ("\"\"".equals(r)) {
                                wv.goBack(); // page didn't handle → normal history back
                            }
                            // anything else ('handled') → the page consumed Back
                        });
                return true;
            }

            // 4) History root → confirm before killing the app when there is
            //    unsaved work (page exposes window.QuirkyDirty.isDirty()).
            webView.evaluateJavascript(
                    "(function(){try{return !!(window.QuirkyDirty&&QuirkyDirty.isDirty&&QuirkyDirty.isDirty());}catch(e){return false}})()",
                    v -> {
                        boolean dirty = "true".equals(v == null ? "" : v.replace("\"", ""));
                        if (!dirty) {
                            finish();
                        } else {
                            new AlertDialog.Builder(MainActivity.this)
                                    .setTitle("Unsaved changes")
                                    .setMessage("Exit Quirky IDE anyway?")
                                    .setPositiveButton("Exit", (d, w) -> finish())
                                    .setNegativeButton("Stay", null)
                                    .show();
                        }
                    });
            return true;
        }
        return super.onKeyDown(keyCode, event);
    }

    /** Result of the <input type=file> chooser (v10). */
    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode == FILE_CHOOSER_REQ && pendingFilePathCallback != null) {
            Uri[] results = WebChromeClient.FileChooserParams.parseResult(resultCode, data);
            pendingFilePathCallback.onReceiveValue(results);
            pendingFilePathCallback = null;
        } else if (requestCode == IMPORT_FILE_REQ) {
            handleImportFileResult(resultCode, data);
        } else if (requestCode == IMPORT_TREE_REQ) {
            handleImportTreeResult(resultCode, data);
        } else if (requestCode == WORKSPACE_TREE_REQ) {
            handleWorkspaceTreeResult(resultCode, data);
        } else if (requestCode == EXPORT_TREE_REQ) {
            handleExportTreeResult(resultCode, data);
        } else if (requestCode == SPEECH_PKG_REQ) {
        handleSpeechPackageResult(resultCode, data);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // ★ EXPLORER IMPORT / EXPORT / DYNAMIC WORKSPACE BRIDGE (v10+)
    // ═══════════════════════════════════════════════════════════════
    // The web side calls window.QuirkyFiles to open Android's storage
    // picker (SAF). Results come back as a 'quirky-files-picked'
    // CustomEvent: { mode, paths[], name, error }.
    //
    // Two resolution strategies:
    //  1. FAST PATH — the URI points at real device storage
    //     ("primary:..." documents) → translated to a raw path like
    //     /storage/emulated/0/... and PHP copies it directly.
    //  2. SAFE PATH — cloud/virtual documents (Drive, etc.) → bytes
    //     are copied into our cache via the content resolver and PHP
    //     gets the staging path. Works with zero extra permissions.

    private void setupFilesBridge() {
        webView.addJavascriptInterface(new Object() {
            @JavascriptInterface
            public void pickImportFiles() {
                runOnUiThread(() -> startImportFilePicker());
            }
            @JavascriptInterface
            public void pickImportFolder() {
                runOnUiThread(() -> startTreePicker(IMPORT_TREE_REQ));
            }
            @JavascriptInterface
            public void pickWorkspaceFolder() {
                runOnUiThread(() -> startTreePicker(WORKSPACE_TREE_REQ));
            }

            /* ★ v11: export-folder control for the Settings → Explorer card */
            @JavascriptInterface
            public String getExportDir() {
                return getExportDirPref();
            }

            @JavascriptInterface
            public boolean setExportDir(String path) {
                return setExportDirPref(path);
            }

            @JavascriptInterface
            public void pickExportFolder() {
                runOnUiThread(() -> startTreePicker(EXPORT_TREE_REQ));
            }
        }, "QuirkyFiles");
    }

    private void startImportFilePicker() {
        try {
            Intent intent = new Intent(Intent.ACTION_OPEN_DOCUMENT);
            intent.addCategory(Intent.CATEGORY_OPENABLE);
            intent.setType("*/*");
            intent.putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true);
            startActivityForResult(intent, IMPORT_FILE_REQ);
        } catch (Exception e) {
            try {
                Intent alt = new Intent(Intent.ACTION_GET_CONTENT);
                alt.addCategory(Intent.CATEGORY_OPENABLE);
                alt.setType("*/*");
                alt.putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true);
                startActivityForResult(alt, IMPORT_FILE_REQ);
            } catch (Exception e2) {
                dispatchPickedEvent("import-file", new ArrayList<String>(), null,
                        "This device has no file picker");
            }
        }
    }

    private void startTreePicker(int requestCode) {
        try {
            Intent intent = new Intent(Intent.ACTION_OPEN_DOCUMENT_TREE);
            startActivityForResult(intent, requestCode);
        } catch (Exception e) {
            String mode = (requestCode == WORKSPACE_TREE_REQ) ? "workspace-pick" : "import-folder";
            dispatchPickedEvent(mode, new ArrayList<String>(), null,
                    "This device has no folder picker");
        }
    }

    private void handleImportFileResult(int resultCode, Intent data) {
        if (resultCode != RESULT_OK || data == null) {
            dispatchPickedEvent("import-file", new ArrayList<String>(), null, "cancelled");
            return;
        }
        final List<Uri> uris = new ArrayList<>();
        if (data.getData() != null) {
            uris.add(data.getData());
        } else if (data.getClipData() != null) {
            for (int i = 0; i < data.getClipData().getItemCount(); i++) {
                uris.add(data.getClipData().getItemAt(i).getUri());
            }
        }
        if (uris.isEmpty()) {
            dispatchPickedEvent("import-file", new ArrayList<String>(), null, "cancelled");
            return;
        }
        new Thread(() -> {
            List<String> paths = new ArrayList<>();
            for (Uri uri : uris) {
                if (uri == null) continue;
                String raw = resolveDocumentUriToPath(uri);
                if (raw != null && hasStorageAccess() && new File(raw).exists()) {
                    paths.add(raw);
                } else {
                    File staged = stageDocument(uri);
                    if (staged != null) paths.add(staged.getAbsolutePath());
                }
            }
            dispatchPickedEvent("import-file", paths, null,
                    paths.isEmpty() ? "Could not read the selected file(s)" : null);
        }).start();
    }

    private void handleImportTreeResult(int resultCode, Intent data) {
        if (resultCode != RESULT_OK || data == null || data.getData() == null) {
            dispatchPickedEvent("import-folder", new ArrayList<String>(), null, "cancelled");
            return;
        }
        final Uri treeUri = data.getData();
        new Thread(() -> {
            String raw = resolveTreeUriToPath(treeUri);
            if (raw != null && hasStorageAccess() && new File(raw).isDirectory()) {
                List<String> paths = new ArrayList<>();
                paths.add(raw);
                dispatchPickedEvent("import-folder", paths, new File(raw).getName(), null);
                return;
            }
            // Cloud / virtual tree → stage a copy inside our cache.
            File staged = stageTree(treeUri);
            if (staged == null) {
                dispatchPickedEvent("import-folder", new ArrayList<String>(), null,
                        "Could not read that folder");
                return;
            }
            List<String> paths = new ArrayList<>();
            paths.add(staged.getAbsolutePath());
            dispatchPickedEvent("import-folder", paths, staged.getName(), null);
        }).start();
    }

    private void handleWorkspaceTreeResult(int resultCode, Intent data) {
        if (resultCode != RESULT_OK || data == null || data.getData() == null) {
            dispatchPickedEvent("workspace-pick", new ArrayList<String>(), null, "cancelled");
            return;
        }
        String raw = resolveTreeUriToPath(data.getData());
        if (raw == null) {
            dispatchPickedEvent("workspace-pick", new ArrayList<String>(), null,
                    "Pick a folder on device storage — cloud folders cannot be used as a workspace");
            return;
        }
        List<String> paths = new ArrayList<>();
        paths.add(raw);
        dispatchPickedEvent("workspace-pick", paths, null, null);
    }

    /** content://...externalstorage.documents/document/primary%3ADownload%2Fx → /storage/emulated/0/Download/x */
    private String resolveDocumentUriToPath(Uri uri) {
        try {
            if (uri == null) return null;
            if (!"com.android.externalstorage.documents".equals(uri.getAuthority())) return null;
            String docId = documentIdOf(uri);
            if (docId == null || !docId.startsWith("primary:")) return null;
            String rel = docId.substring("primary:".length());
            if (rel.isEmpty()) return Environment.getExternalStorageDirectory().getAbsolutePath();
            return Environment.getExternalStorageDirectory().getAbsolutePath() + "/" + rel;
        } catch (Exception e) {
            return null;
        }
    }

    /** content://.../tree/primary%3AQuirkyIde → /storage/emulated/0/QuirkyIde */
    private String resolveTreeUriToPath(Uri treeUri) {
        try {
            if (treeUri == null) return null;
            if (!"com.android.externalstorage.documents".equals(treeUri.getAuthority())) return null;
            String docId = treeDocumentId(treeUri);
            if (docId == null || !docId.startsWith("primary:")) return null;
            String rel = docId.substring("primary:".length());
            if (rel.isEmpty()) return Environment.getExternalStorageDirectory().getAbsolutePath();
            return Environment.getExternalStorageDirectory().getAbsolutePath() + "/" + rel;
        } catch (Exception e) {
            return null;
        }
    }

    /** Copy one SAF document into the import staging cache. */
    private File stageDocument(Uri uri) {
        try {
            String name = queryDisplayName(uri, false);
            if (name == null || name.isEmpty()) name = "import-" + System.currentTimeMillis();
            File dir = new File(getCacheDir(), "import-staging");
            dir.mkdirs();
            File out = uniqueDestination(dir, sanitizeFileName(name));
            try (InputStream is = getContentResolver().openInputStream(uri);
                 FileOutputStream fos = new FileOutputStream(out)) {
                if (is == null) return null;
                byte[] buf = new byte[65536];
                int n;
                while ((n = is.read(buf)) > 0) fos.write(buf, 0, n);
            }
            return out;
        } catch (Exception e) {
            return null;
        }
    }

    /** Recursively copy a SAF tree into the import staging cache. */
    private File stageTree(Uri treeUri) {
        try {
            String rootId = treeDocumentId(treeUri);
            if (rootId == null) return null;
            String rootName = queryDisplayName(treeUri, true);
            if (rootName == null || rootName.isEmpty()) rootName = "folder";
            File dir = new File(getCacheDir(), "import-staging");
            dir.mkdirs();
            File dest = uniqueDestination(dir, sanitizeFileName(rootName));
            if (!dest.mkdirs() && !dest.isDirectory()) return null;
            int[] stats = new int[]{0}; // files copied
            stageTreeRecursive(treeUri, rootId, dest, stats);
            return stats[0] > 0 ? dest : null;
        } catch (Exception e) {
            return null;
        }
    }

    private void stageTreeRecursive(Uri treeUri, String docId, File dest, int[] stats) {
        if (stats[0] >= 5000) return; // safety cap
        Uri childrenUri = childrenUriOf(treeUri, docId);
        Cursor cursor = null;
        try {
            cursor = getContentResolver().query(childrenUri, new String[]{
                    SAF_COL_ID, SAF_COL_NAME, SAF_COL_MIME
            }, null, null, null);
            if (cursor == null) return;
            while (cursor.moveToNext()) {
                String childId = cursor.getString(0);
                String name = sanitizeFileName(cursor.getString(1));
                String mime = cursor.getString(2);
                Uri childUri = documentUriOf(treeUri, childId);
                if (SAF_MIME_DIR.equals(mime)) {
                    File sub = new File(dest, name);
                    if (sub.mkdirs() || sub.isDirectory()) {
                        stageTreeRecursive(treeUri, childId, sub, stats);
                    }
                } else {
                    if (stats[0] >= 5000) return;
                    try (InputStream is = getContentResolver().openInputStream(childUri);
                         FileOutputStream fos = new FileOutputStream(new File(dest, name))) {
                        if (is == null) continue;
                        byte[] buf = new byte[65536];
                        int n;
                        while ((n = is.read(buf)) > 0) fos.write(buf, 0, n);
                        stats[0]++;
                    } catch (Exception ignored) { }
                }
            }
        } catch (Exception ignored) {
        } finally {
            if (cursor != null) cursor.close();
        }
    }

    private String queryDisplayName(Uri uri, boolean isTree) {
        Cursor cursor = null;
        try {
            String docId = isTree ? treeDocumentId(uri) : documentIdOf(uri);
            if (docId == null) return null;
            Uri docUri = isTree ? documentUriOf(uri, docId) : uri;
            cursor = getContentResolver().query(docUri, new String[]{SAF_COL_NAME}, null, null, null);
            if (cursor != null && cursor.moveToFirst()) return cursor.getString(0);
        } catch (Exception ignored) {
        } finally {
            if (cursor != null) cursor.close();
        }
        return null;
    }

    /* ── SAF URI compat helpers ───────────────────────────────────────────
       We deliberately do NOT call DocumentsContract.getTreeDocumentId() /
       buildChildDocumentsUriUsingTreeUri() / buildDocumentUriUsingTreeUri():
       some Android Studio setups fail to resolve those API-21 helpers at
       compile time ("cannot find symbol"). The versions below produce
       byte-for-byte identical URIs using plain Uri parsing/building,
       so they compile on every setup.                                    ── */
    private static final String SAF_COL_ID   = "documentId";
    private static final String SAF_COL_NAME = "_display_name";
    private static final String SAF_COL_MIME = "mime_type";
    private static final String SAF_MIME_DIR = "vnd.android.document/directory";

    /** Document id inside a tree URI: content://<authority>/tree/<doc-id> */
    private String treeDocumentId(Uri treeUri) {
        try {
            java.util.List<String> seg = treeUri.getPathSegments();
            if (seg.size() >= 2 && "tree".equals(seg.get(0))) {
                return seg.get(1); // getPathSegments() is already URL-decoded
            }
        } catch (Exception ignored) { }
        return null;
    }

    /** Document id inside a document URI: content://<authority>/document/<doc-id> */
    private String documentIdOf(Uri docUri) {
        try {
            java.util.List<String> seg = docUri.getPathSegments();
            if (seg.size() >= 2 && "document".equals(seg.get(0))) {
                return seg.get(1);
            }
        } catch (Exception ignored) { }
        return null;
    }

    /** Children-query URI of a tree node (same shape the framework uses). */
    private Uri childrenUriOf(Uri treeUri, String parentDocId) {
        return new Uri.Builder()
                .scheme("content")
                .authority(treeUri.getAuthority())
                .appendPath("document")
                .appendPath(parentDocId)
                .appendPath("children")
                .build();
    }

    /** Plain document URI inside the same provider as the tree. */
    private Uri documentUriOf(Uri treeUri, String docId) {
        return new Uri.Builder()
                .scheme("content")
                .authority(treeUri.getAuthority())
                .appendPath("document")
                .appendPath(docId)
                .build();
    }

    private String sanitizeFileName(String name) {
        if (name == null) return "file";
        String clean = name.replaceAll("[\\\\/:*?\"<>|\\u0000-\\u001f]", "_").trim();
        if (clean.isEmpty() || clean.equals(".") || clean.equals("..")) return "file";
        return clean;
    }

    private File uniqueDestination(File dir, String name) {
        File candidate = new File(dir, name);
        if (!candidate.exists()) return candidate;
        String base = name;
        String ext = "";
        int dot = name.lastIndexOf('.');
        if (dot > 0) { base = name.substring(0, dot); ext = name.substring(dot); }
        for (int i = 1; i < 1000; i++) {
            candidate = new File(dir, base + " (" + i + ")" + ext);
            if (!candidate.exists()) return candidate;
        }
        return new File(dir, base + "-" + System.currentTimeMillis() + ext);
    }

    /**
     * Report the picker result to the page. Delayed ~450 ms because
     * onActivityResult can fire BEFORE onResume — a paused WebView may not
     * run JS instantly, and the delay costs nothing.
     */
    private void dispatchPickedEvent(String mode, List<String> paths, String name, String error) {
        StringBuilder sb = new StringBuilder();
        sb.append("{ mode: '").append(jsEscape(mode)).append("', paths: [");
        for (int i = 0; i < paths.size(); i++) {
            if (i > 0) sb.append(", ");
            sb.append("'").append(jsEscape(paths.get(i))).append("'");
        }
        sb.append("], name: ")
                .append(name == null ? "null" : "'" + jsEscape(name) + "'")
                .append(", error: ")
                .append(error == null ? "null" : "'" + jsEscape(error) + "'")
                .append(" }");
        final String detail = sb.toString();
        webView.postDelayed(() -> webView.evaluateJavascript(
                "(function(){ try { window.dispatchEvent(new CustomEvent('quirky-files-picked',"
                        + "{ detail: " + detail + " })); } catch(e) {} })();", null), 450);
    }

    private String jsEscape(String s) {
        if (s == null) return "";
        return s.replace("\\", "\\\\")
                .replace("'", "\\'")
                .replace("\n", "\\n")
                .replace("\r", "");
    }

    // ═══════════════════════════════════════════════════════════════
    // VOICE — Text-To-Speech bridge (window.QuirkyTts)  ·  v11
    // ═══════════════════════════════════════════════════════════════
    private void setupTtsBridge() {
        webView.addJavascriptInterface(new Object() {
            /** True once the TTS engine has finished initialising. */
            @JavascriptInterface
            public boolean isReady() {
                return ttsReady;
            }

            /** Speak `text` aloud. rate & pitch: 0.5–2.0 (1.0 = normal). */
            @JavascriptInterface
            public void speak(String text, double rate, double pitch) {
                runOnUiThread(() -> doSpeak(text, (float) rate, (float) pitch));
            }

            /** Instant off-switch. */
            @JavascriptInterface
            public void stop() {
                runOnUiThread(() -> {
                    if (tts != null) {
                        try { tts.stop(); } catch (Exception ignored) {}
                    }
                    dispatchWebEvent("quirky-tts-state", "{ state: 'stopped' }");
                });
            }

            /** Engine + voice inventory as JSON (see getTtsInfoJson()). */
            @JavascriptInterface
            public String getInfo() {
                return getTtsInfoJson();
            }

            /** Switch the active voice by its unique name. */
            @JavascriptInterface
            public boolean setVoice(String voiceName) {
                if (tts == null || !ttsReady || voiceName == null || voiceName.isEmpty()) {
                    return false;
                }
                try {
                    java.util.Set<android.speech.tts.Voice> voices = tts.getVoices();
                    if (voices == null) return false;
                    for (android.speech.tts.Voice v : voices) {
                        if (voiceName.equals(v.getName())) {
                            return tts.setVoice(v) == TextToSpeech.SUCCESS;
                        }
                    }
                } catch (Exception ignored) {}
                return false;
            }

            /** Opens Android's TTS settings (download voices, switch engine). */
            @JavascriptInterface
            public void openEngineSettings() {
                runOnUiThread(() -> {
                    try {
                        startActivity(new Intent("com.android.settings.TTS_SETTINGS"));
                    } catch (Exception e1) {
                        try {
                            startActivity(new Intent(TextToSpeech.Engine.ACTION_INSTALL_TTS_DATA));
                        } catch (Exception e2) {
                            try {
                                startActivity(new Intent(Settings.ACTION_SETTINGS));
                            } catch (Exception ignored) {}
                        }
                    }
                });
            }
        }, "QuirkyTts");

        initTts();
    }

    /** Copies the seed catalog out of the APK; refreshes on app updates. */
    private void ensureSpeechCatalog() {
        try {
            File marker = new File(speechDir, ".catalog_version");
            int installed = readVersionMarker(marker);
            File dest = new File(speechDir, "catalog.json");
            if (installed == BuildConfig.VERSION_CODE && dest.isFile()) return;
            copyAsset(ASSET_SPEECH_CATALOG, dest);
            writeVersionMarker(marker, BuildConfig.VERSION_CODE);
        } catch (Exception ignored) { }
    }

    /**
     * ★ v14: window.QuirkySpeech — engine inventory + selection + offline
     * execution entry points. Downloads themselves happen in PHP (Speech.php)
     * because that layer already has curl + SSE + hash verification.
     */
    private void setupSpeechBridge() {
        webView.addJavascriptInterface(new Object() {
            @JavascriptInterface
            public String getCatalog() { return speechManager.getCatalogJson(); }

            @JavascriptInterface
            public String getActiveTts() { return speechManager.getActiveTts(); }
            @JavascriptInterface
            public String getActiveStt() { return speechManager.getActiveStt(); }

            @JavascriptInterface
            public void setActiveTts(String id) {
                speechManager.setActiveTts(id);
                dispatchWebEvent("quirky-speech-engines-changed", "{}");
            }
            @JavascriptInterface
            public void setActiveStt(String id) {
                speechManager.setActiveStt(id);
                dispatchWebEvent("quirky-speech-engines-changed", "{}");
            }

            /** Opens the system file picker for an offline .zip speech package
             *  (quirky.speech.json inside). Result → 'quirky-speech-package-picked'. */
            @JavascriptInterface
            public void pickSpeechPackage() {
                runOnUiThread(() -> {
                    try {
                        Intent intent = new Intent(Intent.ACTION_OPEN_DOCUMENT);
                        intent.addCategory(Intent.CATEGORY_OPENABLE);
                        intent.setType("application/zip");
                        startActivityForResult(intent, SPEECH_PKG_REQ);
                    } catch (Exception e) {
                        dispatchWebEvent("quirky-speech-package-picked",
                                "{ error: 'This device has no file picker' }");
                    }
                });
            }
        }, "QuirkySpeech");
    }

    private void handleSpeechPackageResult(int resultCode, Intent data) {
        if (resultCode != RESULT_OK || data == null || data.getData() == null) {
            dispatchWebEvent("quirky-speech-package-picked", "{ error: 'cancelled' }");
            return;
        }
        new Thread(() -> {
            try {
                File staged = stageDocument(data.getData());   // reuses the SAF staging helper
                if (staged == null) {
                    dispatchWebEvent("quirky-speech-package-picked", "{ error: 'Could not read that file' }");
                } else {
                    dispatchWebEvent("quirky-speech-package-picked",
                            "{ path: '" + staged.getAbsolutePath().replace("\\", "\\\\")
                                    .replace("'", "\\'") + "', name: '"
                                    + staged.getName().replace("'", "\\'") + "' }");
                }
            } catch (Exception e) {
                dispatchWebEvent("quirky-speech-package-picked", "{ error: 'read-failed' }");
            }
        }).start();
    }

    private void initTts() {
        try {
            tts = new TextToSpeech(this, status -> {
                ttsReady = (status == TextToSpeech.SUCCESS);
                if (ttsReady) {
                    try {
                        tts.setOnUtteranceProgressListener(new UtteranceProgressListener() {
                            @Override
                            public void onStart(String utteranceId) {
                                dispatchWebEvent("quirky-tts-state", "{ state: 'speaking' }");
                            }
                            @Override
                            public void onDone(String utteranceId) {
                                dispatchWebEvent("quirky-tts-state", "{ state: 'done' }");
                            }
                            @Override
                            public void onError(String utteranceId) {
                                dispatchWebEvent("quirky-tts-state",
                                        "{ state: 'error', reason: 'engine' }");
                            }
                        });
                    } catch (Exception ignored) {}
                }
                dispatchWebEvent("quirky-tts-ready", "{ ready: " + ttsReady + " }");
            });
        } catch (Exception e) {
            ttsReady = false;
        }
    }

    private void doSpeak(String text, float rate, float pitch) {
        // ★ v14: offline engine selected? Hand it to SpeechManager instead.
        if (speechManager != null && speechManager.isNativeTtsActive()) {
            speechManager.speakNative(text, rate);
            return;
        }
        if (tts == null || !ttsReady) {
            dispatchWebEvent("quirky-tts-state",
                    "{ state: 'error', reason: 'engine-not-ready' }");
            return;
        }
        try {
            tts.setSpeechRate(Math.max(0.5f, Math.min(2.0f, rate)));
            tts.setPitch(Math.max(0.5f, Math.min(2.0f, pitch)));
            Bundle params = new Bundle();
            int result = tts.speak(text == null ? "" : text,
                    TextToSpeech.QUEUE_FLUSH, params, "quirky-tts-utterance");
            if (result != TextToSpeech.SUCCESS) {
                dispatchWebEvent("quirky-tts-state",
                        "{ state: 'error', reason: 'speak-failed' }");
            }
            // The 'speaking' state arrives from the progress listener above.
        } catch (Exception e) {
            dispatchWebEvent("quirky-tts-state",
                    "{ state: 'error', reason: 'exception' }");
        }
    }

    /**
     * JSON inventory for the web side:
     * { ready, engine:{pkg,label}, engines:[{pkg,label}],
     *   voices:[{name,locale,label}], defaultLocale }
     * Voices whose data is not downloaded yet are filtered out.
     */
    private String getTtsInfoJson() {
        try {
            JSONObject out = new JSONObject();
            if (tts == null || !ttsReady) {
                out.put("ready", false);
                return out.toString();
            }
            out.put("ready", true);
            String enginePkg = tts.getDefaultEngine();
            String engineLabel = enginePkg == null ? "" : enginePkg;
            JSONArray engines = new JSONArray();
            for (TextToSpeech.EngineInfo ei : tts.getEngines()) {
                JSONObject eo = new JSONObject();
                eo.put("pkg", ei.name);
                eo.put("label", ei.label);
                engines.put(eo);
                if (ei.name != null && ei.name.equals(enginePkg) && ei.label != null) {
                    engineLabel = ei.label;
                }
            }
            out.put("engines", engines);
            JSONObject engine = new JSONObject();
            engine.put("pkg", enginePkg == null ? "" : enginePkg);
            engine.put("label", engineLabel);
            out.put("engine", engine);
            JSONArray voices = new JSONArray();
            java.util.Set<android.speech.tts.Voice> vs = tts.getVoices();
            if (vs != null) {
                for (android.speech.tts.Voice v : vs) {
                    if (v.getFeatures() != null && v.getFeatures()
                            .contains(TextToSpeech.Engine.KEY_FEATURE_NOT_INSTALLED)) {
                        continue; // voice data not downloaded yet
                    }
                    JSONObject vo = new JSONObject();
                    vo.put("name", v.getName());
                    vo.put("locale", v.getLocale().toString());
                    vo.put("label", v.getLocale().getDisplayName());
                    voices.put(vo);
                }
            }
            out.put("voices", voices);
            out.put("defaultLocale", java.util.Locale.getDefault().toString());
            return out.toString();
        } catch (Exception e) {
            return "{\"ready\":false}";
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // VOICE — Speech-To-Text bridge (window.QuirkyStt)  ·  v11
    // ═══════════════════════════════════════════════════════════════
    private void setupSttBridge() {
        webView.addJavascriptInterface(new Object() {
            @JavascriptInterface
            public boolean hasMicPermission() {
                return checkSelfPermission(Manifest.permission.RECORD_AUDIO)
                        == PackageManager.PERMISSION_GRANTED;
            }

            /** Triggers the runtime mic popup. Result → 'quirky-mic-changed'. */
            @JavascriptInterface
            public void requestMicPermission() {
                runOnUiThread(() -> {
                    if (checkSelfPermission(Manifest.permission.RECORD_AUDIO)
                            == PackageManager.PERMISSION_GRANTED) {
                        dispatchWebEvent("quirky-mic-changed", "{ granted: true }");
                        return;
                    }
                    requestPermissions(
                            new String[]{ Manifest.permission.RECORD_AUDIO },
                            MIC_REQ_CODE);
                });
            }

            /** Does this device have any speech recognition service at all? */
            @JavascriptInterface
            public boolean isAvailable() {
                try {
                    return SpeechRecognizer.isRecognitionAvailable(MainActivity.this);
                } catch (Exception e) {
                    return false;
                }
            }

            @JavascriptInterface
            public boolean isListening() {
                return sttListening;
            }

            /** languageTag: '' / 'system' = device language, else e.g. 'en-US'. */
            @JavascriptInterface
            public void startListening(String languageTag) {
                runOnUiThread(() -> doStartListening(languageTag));
            }

            /** Stop and deliver whatever was heard so far. */
            @JavascriptInterface
            public void stopListening() {
                runOnUiThread(() -> doStopListening(true));
            }

            /** Stop and discard (overlay closed mid-listen). */
            @JavascriptInterface
            public void cancelListening() {
                runOnUiThread(() -> doStopListening(false));
            }
        }, "QuirkyStt");
    }

    private void doStartListening(String languageTag) {
        if (checkSelfPermission(Manifest.permission.RECORD_AUDIO)
                != PackageManager.PERMISSION_GRANTED) {
            dispatchWebEvent("quirky-stt-state",
                    "{ state: 'error', reason: 'no-permission' }");
            return;
        }
        // ★ v14: offline STT engine (whisper) replaces SpeechRecognizer.
        if (speechManager != null && speechManager.isNativeSttActive()) {
            speechManager.startNativeStt(languageTag);
            return;
        }
        if (!SpeechRecognizer.isRecognitionAvailable(this)) {
            dispatchWebEvent("quirky-stt-state",
                    "{ state: 'error', reason: 'not-available' }");
            return;
        }
        // Recognizers are single-use — replace any previous instance.
        destroyRecognizerQuietly();
        speechRecognizer = SpeechRecognizer.createSpeechRecognizer(this);
        speechRecognizer.setRecognitionListener(new RecognitionListener() {
            @Override
            public void onReadyForSpeech(Bundle params) {
                sttListening = true;
                dispatchWebEvent("quirky-stt-state", "{ state: 'listening' }");
            }
            @Override
            public void onBeginningOfSpeech() {
                dispatchWebEvent("quirky-stt-state", "{ state: 'hearing' }");
            }
            @Override
            public void onRmsChanged(float rmsdB) { /* level-meter hook (unused) */ }
            @Override
            public void onBufferReceived(byte[] buffer) { /* unused */ }
            @Override
            public void onEndOfSpeech() {
                sttListening = false;
                dispatchWebEvent("quirky-stt-state", "{ state: 'processing' }");
            }
            @Override
            public void onError(int error) {
                sttListening = false;
                destroyRecognizerQuietly();
                dispatchWebEvent("quirky-stt-state",
                        "{ state: 'error', reason: '" + sttErrorName(error) + "' }");
            }
            @Override
            public void onResults(Bundle results) {
                sttListening = false;
                destroyRecognizerQuietly();
                ArrayList<String> matches =
                        results.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION);
                String text = (matches != null && !matches.isEmpty()) ? matches.get(0) : "";
                dispatchWebEvent("quirky-stt-final",
                        "{ text: '" + jsEscape(text) + "' }");
                dispatchWebEvent("quirky-stt-state", "{ state: 'done' }");
            }
            @Override
            public void onPartialResults(Bundle partialResults) {
                ArrayList<String> matches =
                        partialResults.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION);
                String text = (matches != null && !matches.isEmpty()) ? matches.get(0) : "";
                dispatchWebEvent("quirky-stt-partial",
                        "{ text: '" + jsEscape(text) + "' }");
            }
            @Override
            public void onEvent(int eventType, Bundle params) { /* unused */ }
        });
        Intent intent = new Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH);
        intent.putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL,
                RecognizerIntent.LANGUAGE_MODEL_FREE_FORM);
        String lang = (languageTag == null || languageTag.isEmpty()
                || "system".equals(languageTag))
                ? java.util.Locale.getDefault().toString()
                : languageTag;
        intent.putExtra(RecognizerIntent.EXTRA_LANGUAGE, lang);
        intent.putExtra(RecognizerIntent.EXTRA_LANGUAGE_PREFERENCE, lang);
        intent.putExtra(RecognizerIntent.EXTRA_PARTIAL_RESULTS, true);
        intent.putExtra(RecognizerIntent.EXTRA_MAX_RESULTS, 1);
        try {
            speechRecognizer.startListening(intent);
        } catch (Exception e) {
            sttListening = false;
            destroyRecognizerQuietly();
            dispatchWebEvent("quirky-stt-state",
                    "{ state: 'error', reason: 'start-failed' }");
        }
    }

    /**
     * deliver=true  → finalise and deliver what was heard (normal stop).
     * deliver=false → cancel silently (overlay closed mid-listen).
     */
    private void doStopListening(boolean deliver) {
        // ★ v14: offline engine owns the lifecycle while active.
        if (speechManager != null && speechManager.isNativeSttActive()) {
            speechManager.stopNativeStt(deliver);
            return;
        }
        if (speechRecognizer == null) {
            sttListening = false;
            return;
        }
        if (!deliver) {
            sttListening = false;
            try { speechRecognizer.cancel(); } catch (Exception ignored) {}
            destroyRecognizerQuietly();
            dispatchWebEvent("quirky-stt-state", "{ state: 'idle' }");
            return;
        }
        try {
            speechRecognizer.stopListening(); // final text arrives via onResults
        } catch (Exception ignored) {}
        // Safety net: if the speech service never answers, release after 10 s
        // so the overlay can never hang in "Processing…".
        final SpeechRecognizer pending = speechRecognizer;
        new Handler(Looper.getMainLooper()).postDelayed(() -> {
            if (pending == speechRecognizer) {
                sttListening = false;
                destroyRecognizerQuietly();
                dispatchWebEvent("quirky-stt-state", "{ state: 'idle' }");
            }
        }, 10000);
    }

    private void destroyRecognizerQuietly() {
        if (speechRecognizer != null) {
            try { speechRecognizer.destroy(); } catch (Exception ignored) {}
            speechRecognizer = null;
        }
    }

    private String sttErrorName(int error) {
        switch (error) {
            case SpeechRecognizer.ERROR_NETWORK_TIMEOUT:
                return "network-timeout";
            case SpeechRecognizer.ERROR_NETWORK:
                return "network";
            case SpeechRecognizer.ERROR_AUDIO:
                return "audio";
            case SpeechRecognizer.ERROR_CLIENT:
                return "client";
            case SpeechRecognizer.ERROR_SPEECH_TIMEOUT:
                return "speech-timeout";
            case SpeechRecognizer.ERROR_NO_MATCH:
                return "no-match";
            case SpeechRecognizer.ERROR_RECOGNIZER_BUSY:
                return "busy";
            case SpeechRecognizer.ERROR_INSUFFICIENT_PERMISSIONS:
                return "no-permission";
            default:
                return "unknown";
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // SPACE SAVER: Cleans up unused Termux files natively
    // ═══════════════════════════════════════════════════════════════
    private void cleanupPrefixSpace(File prefixDir) {
        updateStatus("Cleaning up unnecessary files to save space...");
        deleteRecursive(new File(prefixDir, "share/man"));
        deleteRecursive(new File(prefixDir, "share/doc"));
        deleteRecursive(new File(prefixDir, "share/info")); // ★ NEW
        deleteRecursive(new File(prefixDir, "share/locale"));
        deleteRecursive(new File(prefixDir, "share/bash-completion")); // ★ NEW
        deleteRecursive(new File(prefixDir, "share/zsh")); // ★ NEW
        deleteRecursive(new File(prefixDir, "var/cache"));
        // NOTE: do NOT delete prefix/include/ here. It holds the C/C++
        // header files (stdio.h, ...) that clang needs to compile anything.
        // Deleting it on every launch made every compilation fail with
        // "fatal error: 'stdio.h' file not found".

        File tmpDir = new File(prefixDir, "tmp");
        if (tmpDir.exists() && tmpDir.isDirectory()) {
            File[] tmpFiles = tmpDir.listFiles();
            if (tmpFiles != null) {
                for (File f : tmpFiles) deleteRecursive(f);
            }
        }
        deleteStaticLibs(prefixDir); // Deletes *.a files

        // ★ NEW: Clean up Python test folders if Python is installed
        File libDir = new File(prefixDir, "lib");
        if (libDir.exists() && libDir.isDirectory()) {
            File[] libDirs = libDir.listFiles();
            if (libDirs != null) {
                for (File dir : libDirs) {
                    if (dir.isDirectory() && dir.getName().startsWith("python")) {
                        deleteRecursive(new File(dir, "test"));
                        deleteRecursive(new File(dir, "tests"));
                    }
                }
            }
        }
    }

    private void deleteRecursive(File fileOrDirectory) {
        if (fileOrDirectory == null || !fileOrDirectory.exists()) return;
        if (fileOrDirectory.isDirectory()) {
            File[] children = fileOrDirectory.listFiles();
            if (children != null) {
                for (File child : children) deleteRecursive(child);
            }
        }
        fileOrDirectory.delete();
    }

    /**
     * Deletes leftover static libraries (*.a) to save space.
     *
     * ★ FIX (v10) — "clang breaks after app restart":
     * The old version tried to protect lib/clang/ with a three-clause guard,
     * but two clauses compared the wrong File levels (new File(usr, "lib")
     * equals usr → ALWAYS false), so the guard could never fire. The walk
     * descended into lib/clang/<ver>/lib/ and deleted the compiler runtime
     * archives (libclang_rt.builtins-*.a etc.) on EVERY launch → first link
     * after a restart failed with "cannot find -lclang_rt.builtins".
     *
     * New policy, safe by construction:
     *   • NOTHING under prefix/lib/ is ever touched. That tree holds every
     *     archive packages link against (-l<name> resolves there); wiping
     *     any of it broke not just clang but any static-linked build.
     *   • *.a elsewhere (share/, doc leftovers…) are still removed.
     * The megabytes saved inside lib/ were never worth broken toolchains.
     */
    private void deleteStaticLibs(File prefixDir) {
        if (prefixDir == null || !prefixDir.exists() || !prefixDir.isDirectory()) return;

        // Canonical path of prefix/lib — the forbidden zone.
        String libPath;
        try {
            libPath = new File(prefixDir, "lib").getCanonicalPath() + File.separator;
        } catch (IOException e) {
            return; // Cannot resolve paths safely — do nothing at all.
        }

        File[] topLevels = prefixDir.listFiles();
        if (topLevels == null) return;
        for (File top : topLevels) {
            try {
                String topPath = top.getCanonicalPath() + File.separator;
                if (topPath.startsWith(libPath)) continue; // skip lib/** entirely
            } catch (IOException e) {
                continue; // unresolvable path — skip rather than risk it
            }
            deleteStaticLibsOutsideLib(top);
        }
    }

    /** Recursively removes *.a files in one subtree (never called for prefix/lib). */
    private void deleteStaticLibsOutsideLib(File dir) {
        if (dir == null || !dir.exists() || !dir.isDirectory()) return;
        File[] files = dir.listFiles();
        if (files == null) return;
        for (File file : files) {
            if (file.isDirectory()) {
                deleteStaticLibsOutsideLib(file);
            } else if (file.getName().endsWith(".a")) {
                file.delete();
            }
        }
    }
}