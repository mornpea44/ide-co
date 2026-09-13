package com.quirky.ide;

import android.content.Context;
import android.content.SharedPreferences;
import android.media.AudioFormat;
import android.media.AudioRecord;
import android.media.AudioTrack;
import android.media.MediaRecorder;
import android.os.Handler;
import android.os.Looper;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — v14 DYNAMIC SPEECH ENGINE
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Execution layer for OFFLINE speech providers. Models/runtimes are never
 *  bundled in the APK — they are downloaded by services/Speech.php into
 *  files/speech/ and this class merely RUNS them through versioned CLI
 *  contracts:
 *
 *    piper-v1           text on stdin  → raw 16-bit PCM on stdout (mono)
 *    sherpa-onnx-tts-v1 text as arg    → WAV file → parsed to PCM
 *    whisper-cli-v1     16 kHz WAV in  → plain text on stdout
 *
 *  The WebView keeps using the SAME events it already knows from v11:
 *    quirky-tts-state  {state: speaking|done|stopped|error, reason?}
 *    quirky-stt-state  {state: listening|hearing|processing|done|idle|error}
 *    quirky-stt-final  {text}
 *
 *  "system" engines stay 100% on the existing v11 paths in MainActivity —
 *  this class is only touched when the user actively selects an offline
 *  engine in Settings → Voice → Engines & Models.
 * ═══════════════════════════════════════════════════════════════════════════
 */
public class SpeechManager {

    /** MainActivity supplies this — it is literally dispatchWebEvent(). */
    public interface Emitter { void emit(String eventName, String jsDetailLiteral); }

    private final Context context;
    private final File speechDir;          // files/speech
    private final Emitter emitter;
    private final Handler main = new Handler(Looper.getMainLooper());

    /* ── active TTS (piper/sherpa) state ── */
    private Process ttsProcess;
    private AudioTrack ttsTrack;
    private Thread ttsThread;

    /* ── active STT (whisper) state ── */
    private AudioRecord recorder;
    private Thread recordThread;
    private Thread transcribeThread;
    private final AtomicBoolean recording = new AtomicBoolean(false);
    private File pendingWav;

    private static final int WHISPER_RATE = 16000;
    private static final long MAX_RECORD_MS = 5 * 60 * 1000; // hard cap 5 min

    public SpeechManager(Context context, File speechDir, Emitter emitter) {
        this.context = context;
        this.speechDir = speechDir;
        this.emitter = emitter;
    }

    /* ═══════════════════════════════════════════════════════════
       REGISTRY + CATALOG (registry.json is written by Speech.php)
       ═══════════════════════════════════════════════════════════ */

    private JSONObject loadRegistry() {
        try {
            File f = new File(speechDir, "registry.json");
            if (!f.isFile()) return new JSONObject();
            byte[] b = new byte[(int) f.length()];
            try (FileInputStream fis = new FileInputStream(f)) {
                int off = 0, n;
                while (off < b.length && (n = fis.read(b, off, b.length - off)) > 0) off += n;
            }
            return new JSONObject(new String(b, java.nio.charset.StandardCharsets.UTF_8));
        } catch (Exception e) {
            return new JSONObject();
        }
    }

    private SharedPreferences prefs() {
        return context.getSharedPreferences("quirky_prefs", Context.MODE_PRIVATE);
    }

    public String getActiveTts() { return prefs().getString("speech_tts_engine", "system"); }
    public String getActiveStt() { return prefs().getString("speech_stt_engine", "system"); }

    public void setActiveTts(String id) {
        prefs().edit().putString("speech_tts_engine", safeEngineId(id, "tts")).apply();
    }
    public void setActiveStt(String id) {
        prefs().edit().putString("speech_stt_engine", safeEngineId(id, "stt")).apply();
    }

    /** Only accept "system" or an engine that is actually installed. */
    private String safeEngineId(String id, String kind) {
        if (id == null || id.isEmpty() || "system".equals(id)) return "system";
        try {
            JSONObject engines = buildEngineList();
            JSONArray list = engines.getJSONArray(kind);
            for (int i = 0; i < list.length(); i++) {
                if (id.equals(list.getJSONObject(i).getString("id"))) return id;
            }
        } catch (Exception ignored) { }
        return "system";
    }

    /**
     * Full engine inventory for the web UI:
     *   { tts:[{id,label,lang,sizeBytes,builtin}], stt:[...],
     *     active:{tts,stt}, runtimes:{id:true} }
     * Engine id format for offline engines:  "<runtime>|<modelId>"
     */
    public String getCatalogJson() {
        try {
            JSONObject out = new JSONObject();
            out.put("tts", buildEngineList().getJSONArray("tts"));
            out.put("stt", buildEngineList().getJSONArray("stt"));
            JSONObject active = new JSONObject();
            active.put("tts", getActiveTts());
            active.put("stt", getActiveStt());
            out.put("active", active);
            JSONObject runtimes = new JSONObject();
            JSONObject reg = loadRegistry();
            if (reg.has("runtimes")) {
                JSONObject r = reg.getJSONObject("runtimes");
                java.util.Iterator<String> it = r.keys();
                while (it.hasNext()) {
                    String k = it.next();
                    runtimes.put(k, r.getJSONObject(k).optBoolean("installed", false));
                }
            }
            out.put("runtimes", runtimes);
            return out.toString();
        } catch (Exception e) {
            return "{\"tts\":[],\"stt\":[],\"error\":\"" + e.getMessage() + "\"}";
        }
    }

    private JSONObject buildEngineList() throws Exception {
        JSONObject out = new JSONObject();
        JSONArray tts = new JSONArray();
        JSONArray stt = new JSONArray();

        tts.put(new JSONObject()
                .put("id", "system").put("label", "Android / Google TTS")
                .put("builtin", true).put("installed", true));
        stt.put(new JSONObject()
                .put("id", "system").put("label", "Google Speech Recognition")
                .put("builtin", true).put("installed", true));

        JSONObject reg = loadRegistry();
        JSONObject models = reg.optJSONObject("models");
        if (models != null) {
            java.util.Iterator<String> it = models.keys();
            while (it.hasNext()) {
                String modelId = it.next();
                JSONObject m = models.getJSONObject(modelId);
                boolean installed = m.optBoolean("installed", false);
                if (!installed) continue;
                String kind = m.optString("kind", "");
                JSONObject e = new JSONObject()
                        .put("id", m.optString("runtime") + "|" + modelId)
                        .put("label", m.optString("name", modelId))
                        .put("lang", m.optString("lang", ""))
                        .put("sizeBytes", m.optLong("bytes", 0))
                        .put("builtin", false)
                        .put("installed", true);
                if ("tts".equals(kind)) tts.put(e); else stt.put(e);
            }
        }
        out.put("tts", tts);
        out.put("stt", stt);
        return out;
    }

    /**
     * Resolve an engine ID to its binary, model entry, and contract.
     * Returns Object[] { File binary, File entry, String contract } or null.
     */
    private Object[] resolveEngine(String engineId) {
        try {
            int bar = engineId.indexOf('|');
            if (bar <= 0) return null;
            String runtimeId = engineId.substring(0, bar);
            String modelId = engineId.substring(bar + 1);
            JSONObject reg = loadRegistry();
            JSONObject rt = reg.optJSONObject("runtimes");
            JSONObject models = reg.optJSONObject("models");
            if (rt == null || models == null) return null;
            JSONObject r = rt.optJSONObject(runtimeId);
            JSONObject m = models.optJSONObject(modelId);
            if (r == null || m == null) return null;
            File binary = new File(speechDir, r.optString("binary", ""));
            File entry = new File(speechDir, m.optString("entry", ""));
            if (!binary.isFile() || !entry.isFile()) return null;
            // ★ v15.4: a model may declare its own contract (one runtime
            // serves TTS and STT); fall back to the runtime's contract.
            String contract = m.optString("contract", r.optString("contract", ""));
            return new Object[]{ binary, entry, contract, r, m };
        } catch (Exception e) {
            return null;
        }
    }

    /* ═══════════════════════════════════════════════════════════
       TTS — PIPER & SHERPA-ONNX
       ═══════════════════════════════════════════════════════════ */

    public boolean isNativeTtsActive() { return !"system".equals(getActiveTts()); }

    public void speakNative(final String text, final float rate) {
        stopNativeTts(true);
        final Object[] eng = resolveEngine(getActiveTts());
        if (eng == null) {
            emitTts("error", "engine-missing");
            return;
        }

        final File binary = (File) eng[0];
        final File entry = (File) eng[1];
        final String contract = (String) eng[2];

        // Dispatch to the correct contract handler
        if ("sherpa-onnx-tts-v1".equals(contract)) {
            speakSherpaOnnx(binary, entry, text, rate);
            return;
        }

        // Default: piper-v1 contract
        ttsThread = new Thread(() -> {
            try {
                int sampleRate = piperSampleRateOf(entry);
                float lengthScale = (rate <= 0.05f) ? 1f : Math.max(0.5f, Math.min(2f, 1f / rate));
                ProcessBuilder pb = new ProcessBuilder(
                        binary.getAbsolutePath(),
                        "--model", entry.getAbsolutePath(),
                        "--output_raw",
                        "--length_scale", String.format(java.util.Locale.US, "%.2f", lengthScale));
                pb.redirectErrorStream(false);
                ttsProcess = pb.start();

                /* feed the text, then close stdin so piper starts */
                try (OutputStream os = ttsProcess.getOutputStream()) {
                    os.write((text == null ? "" : text).getBytes(java.nio.charset.StandardCharsets.UTF_8));
                }

                int minBuf = AudioTrack.getMinBufferSize(sampleRate,
                        AudioFormat.CHANNEL_OUT_MONO, AudioFormat.ENCODING_PCM_16BIT);
                final AudioTrack track = new AudioTrack(
                        android.media.AudioAttributes.USAGE_MEDIA,
                        sampleRate,
                        AudioFormat.CHANNEL_OUT_MONO,
                        AudioFormat.ENCODING_PCM_16BIT,
                        Math.max(minBuf * 2, 8192),
                        AudioTrack.MODE_STREAM);
                ttsTrack = track;
                track.play();

                InputStream is = ttsProcess.getInputStream();
                byte[] buf = new byte[4096];
                int n;
                boolean first = true;
                while ((n = is.read(buf)) > 0) {
                    if (first) { emitTts("speaking", null); first = false; }
                    /* PCM16 → short samples */
                    int shorts = n / 2;
                    short[] pcm = new short[shorts];
                    for (int i = 0; i < shorts; i++) {
                        pcm[i] = (short) ((buf[i * 2] & 0xff) | (buf[i * 2 + 1] << 8));
                    }
                    track.write(pcm, 0, shorts);
                }
                int exit = ttsProcess.waitFor();
                drainAndClose(track);
                emitTts(exit == 0 ? "done" : "error", exit == 0 ? null : "exit-" + exit);
            } catch (Exception e) {
                emitTts("error", "exception");
            } finally {
                ttsProcess = null;
            }
        }, "quirky-piper");
        ttsThread.start();
    }

    /**
     * sherpa-onnx TTS execution.
     *
     * ★ FIX (v16): three bugs caused the "exit-1" instant failure:
     *
     *   1. MISSING MODE ARGUMENT — When Speech.php detects jniLibs it
     *      builds "sherpa-bridge", a tiny C driver whose argv[1] MUST
     *      be "tts" or "asr". Java was passing "--vits-model" as
     *      argv[1], so the bridge printed usage and exited.
     *
     *   2. LD_LIBRARY_PATH OVERWRITTEN — pb.environment().put()
     *      REPLACED the env var, destroying the paths the bridge
     *      wrapper script sets (jniLibs, linklib, applibs, prefix).
     *      Now we PREPEND so both the wrapper's paths and any extra
     *      paths are available.
     *
     *   3. STDERR SWALLOWED — errors were drained into /dev/null.
     *      Now stderr is captured and included in the error event
     *      so the Voice Preview Logs show the REAL reason.
     */
    private void speakSherpaOnnx(final File binary, final File modelFile,
                                 final String text, final float rate) {
        ttsThread = new Thread(() -> {
            try {
                final File modelDir = modelFile.getParentFile();
                final File tokens = new File(modelDir, "tokens.txt");
                final File dataDir = new File(modelDir, "espeak-ng-data");
                final File outputWav = new File(context.getCacheDir(), "sherpa-tts-out.wav");

                if (outputWav.isFile()) outputWav.delete();

                float lengthScale = (rate <= 0.05f) ? 1.0f
                        : Math.max(0.5f, Math.min(2.0f, 1.0f / rate));

                // ── Build command ──
                java.util.List<String> cmd = new java.util.ArrayList<>();
                cmd.add(binary.getAbsolutePath());

                /* ★ FIX: the sherpa-bridge C driver requires argv[1]
                   to be the mode ("tts" or "asr"). Without it the
                   bridge prints "usage" and exits. Detect the bridge
                   by file name; a direct sherpa-onnx-offline-tts
                   binary (non-bridge) does NOT need this token. */
                boolean isBridge = binary.getName().contains("sherpa-bridge");
                if (isBridge) {
                    cmd.add("tts");
                }

                cmd.add("--vits-model");
                cmd.add(modelFile.getAbsolutePath());
                if (tokens.isFile()) {
                    cmd.add("--vits-tokens");
                    cmd.add(tokens.getAbsolutePath());
                }
                if (dataDir.isDirectory()) {
                    cmd.add("--vits-data-dir");
                    cmd.add(dataDir.getAbsolutePath());
                }
                cmd.add("--output-filename");
                cmd.add(outputWav.getAbsolutePath());
                cmd.add("--length-scale");
                cmd.add(String.format(java.util.Locale.US, "%.2f", lengthScale));
                cmd.add("--num-threads");
                cmd.add(String.valueOf(
                        Math.min(4, Runtime.getRuntime().availableProcessors())));
                cmd.add(text != null ? text : "");

                ProcessBuilder pb = new ProcessBuilder(cmd);
                pb.redirectErrorStream(false);

                /* ★ FIX 2: PREPEND extra lib paths instead of replacing.
                   The bridge wrapper script already exports the correct
                   LD_LIBRARY_PATH (jniLibs + linklib + applibs + prefix),
                   so we only need to add the bin/../lib hint for the
                   direct-binary case. */
                String extraPaths = binary.getParentFile().getAbsolutePath()
                        + ":"
                        + new File(binary.getParentFile(), "../lib").getCanonicalPath();
                String existing = pb.environment().containsKey("LD_LIBRARY_PATH")
                        ? pb.environment().get("LD_LIBRARY_PATH") : "";
                if (!existing.isEmpty()) {
                    pb.environment().put("LD_LIBRARY_PATH",
                            extraPaths + ":" + existing);
                } else {
                    pb.environment().put("LD_LIBRARY_PATH", extraPaths);
                }

                ttsProcess = pb.start();

                /* ★ FIX 3: capture stderr so we can report the real
                   error in the Voice Preview Logs panel. */
                final StringBuilder errBuf = new StringBuilder();
                Thread stderrDrainer = new Thread(() -> {
                    try {
                        byte[] buf = new byte[4096];
                        java.io.InputStream err = ttsProcess.getErrorStream();
                        int n;
                        while ((n = err.read(buf)) > 0) {
                            synchronized (errBuf) {
                                if (errBuf.length() < 2000) {
                                    errBuf.append(new String(buf, 0, n,
                                            java.nio.charset.StandardCharsets.UTF_8));
                                }
                            }
                        }
                    } catch (Exception ignored) {}
                });
                stderrDrainer.start();

                int exit = ttsProcess.waitFor();
                stderrDrainer.join(3000);

                if (exit != 0) {
                    String detail;
                    synchronized (errBuf) { detail = errBuf.toString().trim(); }
                    String reason = "exit-" + exit;
                    if (!detail.isEmpty()) {
                        // Truncate to keep the JS event small
                        if (detail.length() > 300) detail = detail.substring(detail.length() - 300);
                        reason += " · " + detail.replace("'", " ").replace("\n", " ");
                    }
                    emitTts("error", reason);
                    return;
                }

                if (!outputWav.isFile() || outputWav.length() <= 44) {
                    emitTts("error", "no-output");
                    return;
                }

                byte[] wavBytes = readFileBytes(outputWav);
                if (wavBytes == null || wavBytes.length <= 44) {
                    emitTts("error", "empty-wav");
                    return;
                }

                int sampleRate = (wavBytes[24] & 0xff)
                        | ((wavBytes[25] & 0xff) << 8)
                        | ((wavBytes[26] & 0xff) << 16)
                        | ((wavBytes[27] & 0xff) << 24);
                if (sampleRate <= 0 || sampleRate > 96000) sampleRate = 22050;

                int numSamples = (wavBytes.length - 44) / 2;
                short[] pcm = new short[numSamples];
                for (int i = 0; i < numSamples; i++) {
                    pcm[i] = (short) ((wavBytes[44 + i * 2] & 0xff)
                            | (wavBytes[44 + i * 2 + 1] << 8));
                }

                int minBuf = AudioTrack.getMinBufferSize(sampleRate,
                        AudioFormat.CHANNEL_OUT_MONO, AudioFormat.ENCODING_PCM_16BIT);
                final AudioTrack track = new AudioTrack(
                        new android.media.AudioAttributes.Builder()
                                .setUsage(android.media.AudioAttributes.USAGE_MEDIA)
                                .setContentType(android.media.AudioAttributes.CONTENT_TYPE_SPEECH)
                                .build(),
                        new android.media.AudioFormat.Builder()
                                .setSampleRate(sampleRate)
                                .setChannelMask(AudioFormat.CHANNEL_OUT_MONO)
                                .setEncoding(AudioFormat.ENCODING_PCM_16BIT)
                                .build(),
                        Math.max(minBuf * 2, 8192),
                        AudioTrack.MODE_STREAM,
                        android.media.AudioManager.AUDIO_SESSION_ID_GENERATE);
                ttsTrack = track;
                track.play();
                emitTts("speaking", null);

                int chunkSize = 4096;
                int offset = 0;
                while (offset < numSamples) {
                    int remaining = numSamples - offset;
                    track.write(pcm, offset, Math.min(chunkSize, remaining));
                    offset += Math.min(chunkSize, remaining);
                }

                drainAndClose(track);
                emitTts("done", null);

            } catch (Exception e) {
                emitTts("error", "exception: " + e.getMessage());
            } finally {
                ttsProcess = null;
                File outWav = new File(context.getCacheDir(), "sherpa-tts-out.wav");
                if (outWav.isFile()) outWav.delete();
            }
        }, "quirky-sherpa-tts");
        ttsThread.start();
    }

    /**
     * Read an entire file into a byte array.
     */
    private byte[] readFileBytes(File file) {
        try {
            int len = (int) file.length();
            byte[] buf = new byte[len];
            try (java.io.FileInputStream fis = new java.io.FileInputStream(file)) {
                int off = 0, n;
                while (off < len && (n = fis.read(buf, off, len - off)) > 0) {
                    off += n;
                }
            }
            return buf;
        } catch (Exception e) {
            return null;
        }
    }

    /** Piper voice config lives next to the model: <entry>.json → audio.sample_rate */
    private int piperSampleRateOf(File modelFile) {
        try {
            File cfg = new File(modelFile.getAbsolutePath() + ".json");
            if (!cfg.isFile()) cfg = new File(modelFile.getParentFile(), "model.onnx.json");
            if (cfg.isFile()) {
                byte[] b = new byte[(int) cfg.length()];
                try (FileInputStream fis = new FileInputStream(cfg)) {
                    int off = 0, n;
                    while (off < b.length && (n = fis.read(b, off, b.length - off)) > 0) off += n;
                }
                JSONObject j = new JSONObject(new String(b, java.nio.charset.StandardCharsets.UTF_8));
                JSONObject audio = j.optJSONObject("audio");
                if (audio != null && audio.has("sample_rate")) return audio.getInt("sample_rate");
            }
        } catch (Exception ignored) { }
        return 22050;
    }

    private void drainAndClose(AudioTrack track) {
        try {
            if (track != null) {
                track.stop();
                track.release();
            }
        } catch (Exception ignored) { }
        if (ttsTrack == track) ttsTrack = null;
    }

    public void stopNativeTts(boolean emitStopped) {
        try { if (ttsProcess != null) ttsProcess.destroyForcibly(); } catch (Exception ignored) { }
        try { if (ttsTrack != null) { ttsTrack.stop(); ttsTrack.release(); ttsTrack = null; } }
        catch (Exception ignored) { }
        ttsProcess = null;
        if (emitStopped) emitTts("stopped", null);
    }

    private void emitTts(final String state, final String reason) {
        main.post(() -> emitter.emit("quirky-tts-state",
                reason == null ? "{ state: '" + state + "' }"
                        : "{ state: '" + state + "', reason: '" + reason + "' }"));
    }

    /* ═══════════════════════════════════════════════════════════
       STT — WHISPER (contract whisper-cli-v1)
       AudioRecord 16 kHz mono → WAV file → whisper-cli → text
       ═══════════════════════════════════════════════════════════ */

    public boolean isNativeSttActive() { return !"system".equals(getActiveStt()); }

    public void startNativeStt(String languageTag) {
        // ✅ FIXED: Cast to Object[] and extract variables properly
        final Object[] eng = resolveEngine(getActiveStt());
        if (eng == null) { emitStt("error", "engine-missing"); return; }

        final File binary = (File) eng[0];
        final File entry = (File) eng[1];
        final String contract = (String) eng[2];
        final JSONObject runtimeJson = (JSONObject) eng[3];
        final JSONObject modelJson = (JSONObject) eng[4];
        final String lang = whisperLangOf(languageTag);
        final File wav = new File(context.getCacheDir(), "speech-rec.wav");
        pendingWav = wav;

        int minBuf = AudioRecord.getMinBufferSize(WHISPER_RATE,
                AudioFormat.CHANNEL_IN_MONO, AudioFormat.ENCODING_PCM_16BIT);
        try {
            recorder = new AudioRecord(MediaRecorder.AudioSource.MIC,
                    WHISPER_RATE, AudioFormat.CHANNEL_IN_MONO,
                    AudioFormat.ENCODING_PCM_16BIT, Math.max(minBuf * 2, 8192));
        } catch (Exception e) {
            emitStt("error", "audio");
            return;
        }
        if (recorder.getState() != AudioRecord.STATE_INITIALIZED) {
            recorder.release(); recorder = null;
            emitStt("error", "audio");
            return;
        }

        recording.set(true);
        emitStt("listening", null);
        recorder.startRecording();

        recordThread = new Thread(() -> {
            final long startAt = System.currentTimeMillis();
            final ByteArrayOutputStream pcm = new ByteArrayOutputStream();
            final byte[] buf = new byte[3200]; // 100 ms frames
            long frames = 0;
            try {
                while (recording.get()
                        && (System.currentTimeMillis() - startAt) < MAX_RECORD_MS) {
                    int n = recorder.read(buf, 0, buf.length);
                    if (n > 0) {
                        pcm.write(buf, 0, n);
                        frames++;
                        /* crude voice-activity → "hearing" state every ~1.5 s */
                        if (frames % 15 == 0 && rmsAboveNoise(buf, n)) {
                            emitStt("hearing", null);
                        }
                    }
                }
            } catch (Exception ignored) { }
            try { recorder.stop(); } catch (Exception ignored) { }
            try { recorder.release(); } catch (Exception ignored) { }
            recorder = null;
            if (!recording.getAndSet(false)) return; // cancelled — discard

            try {
                writeWav(wav, pcm.toByteArray());
                emitStt("processing", null);
                if ("sherpa-onnx-asr-v1".equals(contract)) {
                    runSherpaAsr(runtimeJson, modelJson, wav, lang);
                } else {
                    runWhisper(binary, entry, wav, lang);
                }
            } catch (Exception e) {
                emitStt("error", "audio");
            }
        }, "quirky-whisper-rec");
        recordThread.start();
    }

    /** deliver=true → finish + transcribe · deliver=false → cancel silently */
    public void stopNativeStt(boolean deliver) {
        if (!deliver) {
            recording.set(false);   // record loop exits and discards
            try { if (recorder != null) recorder.stop(); } catch (Exception ignored) { }
            emitStt("idle", null);
            return;
        }
        /* loop exits on its own → transcribe starts automatically */
        recording.set(false);
    }

    private void runWhisper(File binary, File model, File wav, String lang) {
        transcribeThread = new Thread(() -> {
            Process p = null;
            try {
                int cores = Math.min(8, Runtime.getRuntime().availableProcessors());
                ProcessBuilder pb = new ProcessBuilder(
                        binary.getAbsolutePath(),
                        "-m", model.getAbsolutePath(),
                        "-f", wav.getAbsolutePath(),
                        "-nt",                       // no timestamps
                        "-l", lang,
                        "-t", String.valueOf(cores));
                pb.redirectErrorStream(true);
                p = pb.start();
                InputStream is = p.getInputStream();
                byte[] buf = new byte[4096];
                StringBuilder sb = new StringBuilder();
                int n;
                while ((n = is.read(buf)) > 0) {
                    sb.append(new String(buf, 0, n, java.nio.charset.StandardCharsets.UTF_8));
                    if (sb.length() > 200000) break; // safety cap
                }
                boolean finished = p.waitFor(240, java.util.concurrent.TimeUnit.SECONDS);
                if (!finished) { p.destroyForcibly(); emitStt("error", "timeout"); return; }
                String text = cleanWhisperOutput(sb.toString());
                final String fText = text;
                main.post(() -> emitter.emit("quirky-stt-final",
                        "{ text: '" + jsEscape(fText) + "' }"));
                emitStt("done", null);
            } catch (Exception e) {
                emitStt("error", "engine");
            } finally {
                if (wav.isFile()) wav.delete();
            }
        }, "quirky-whisper-run");
        transcribeThread.start();
    }

    /* ═══ v15.4: sherpa-onnx ASR (contract sherpa-onnx-asr-v1) ═══
   Runs the sherpa-onnx-offline binary with a pre-converted
   Whisper model (encoder + decoder + tokens) on the recorded WAV. */
    private void runSherpaAsr(final JSONObject runtime, final JSONObject model, final File wav, final String lang) {
        transcribeThread = new Thread(() -> {
            Process p = null;
            try {
                File asrBin = null;
                String rel = runtime.optString("asrBinary", "");
                if (!rel.isEmpty()) {
                    File f = new File(speechDir, rel);
                    if (f.isFile()) asrBin = f;
                }
                if (asrBin == null) {
                    File ttsBin = new File(speechDir, runtime.optString("binary", ""));
                    File sib = new File(ttsBin.getParentFile(), "sherpa-onnx-offline");
                    if (sib.isFile()) asrBin = sib;
                }
                if (asrBin == null) { emitStt("error", "engine"); return; }

                File enc = new File(speechDir, model.optString("encoder", ""));
                File dec = new File(speechDir, model.optString("decoder", ""));
                File tok = new File(speechDir, model.optString("tokens", ""));
                if (!enc.isFile() || !dec.isFile() || !tok.isFile()) {
                    emitStt("error", "engine");
                    return;
                }
                int cores = Math.min(8, Runtime.getRuntime().availableProcessors());
                String language = (lang == null || lang.isEmpty() || "auto".equals(lang)) ? "en" : lang;

                ProcessBuilder pb = new ProcessBuilder(
                        asrBin.getAbsolutePath(),
                        "--tokens=" + tok.getAbsolutePath(),
                        "--whisper-encoder=" + enc.getAbsolutePath(),
                        "--whisper-decoder=" + dec.getAbsolutePath(),
                        "--whisper-language=" + language,
                        "--num-threads=" + cores,
                        wav.getAbsolutePath());
                pb.redirectErrorStream(true);
                String binDir = asrBin.getParentFile().getAbsolutePath();
                pb.environment().put("LD_LIBRARY_PATH",
                        binDir + ":" + new File(asrBin.getParentFile(), "../lib").getAbsolutePath());
                p = pb.start();
                InputStream is = p.getInputStream();
                byte[] buf = new byte[4096];
                StringBuilder sb = new StringBuilder();
                int n;
                while ((n = is.read(buf)) > 0) {
                    sb.append(new String(buf, 0, n, java.nio.charset.StandardCharsets.UTF_8));
                    if (sb.length() > 200000) break;
                }
                boolean finished = p.waitFor(240, java.util.concurrent.TimeUnit.SECONDS);
                if (!finished) { p.destroyForcibly(); emitStt("error", "timeout"); return; }
                String text = cleanSherpaAsrOutput(sb.toString());
                final String fText = text;
                main.post(() -> emitter.emit("quirky-stt-final",
                        "{ text: '" + jsEscape(fText) + "' }"));
                emitStt("done", null);
            } catch (Exception e) {
                emitStt("error", "engine");
            } finally {
                if (wav.isFile()) wav.delete();
            }
        }, "quirky-sherpa-asr");
        transcribeThread.start();
    }

    /** Tolerant parser for sherpa-onnx-offline stdout. */
    private String cleanSherpaAsrOutput(String raw) {
        StringBuilder out = new StringBuilder();
        for (String line : raw.split("\\r?\n")) {
            String t = line.trim();
            if (t.isEmpty()) continue;
            if (t.endsWith(".wav") || t.contains("----")) continue;
            if (t.startsWith("Wave") || t.startsWith("sherpa-onnx") || t.startsWith("num_threads")) continue;
            int idx = t.indexOf("Text:");
            if (idx >= 0) t = t.substring(idx + 5).trim();
            out.append(t).append(' ');
        }
        return out.toString().trim();
    }

    private String cleanWhisperOutput(String raw) {
        StringBuilder out = new StringBuilder();
        for (String line : raw.split("\\r?\\n")) {
            String t = line.trim();
            if (t.isEmpty()) continue;
            if (t.startsWith("whisper_") || t.startsWith("system_info")
                    || t.startsWith("main:") || t.contains("[00:00:00.000 -->")) continue;
            out.append(t).append(' ');
        }
        return out.toString().trim();
    }

    private boolean rmsAboveNoise(byte[] buf, int n) {
        long sum = 0;
        for (int i = 0; i + 1 < n; i += 2) {
            short s = (short) ((buf[i] & 0xff) | (buf[i + 1] << 8));
            sum += s * s;
        }
        double rms = Math.sqrt(sum / (double) (n / 2));
        return rms > 350; // rough speech threshold
    }

    private String whisperLangOf(String tag) {
        if (tag == null || tag.isEmpty() || "system".equals(tag)) return "auto";
        String base = tag.split("[-_]")[0].toLowerCase(java.util.Locale.US);
        if ("fil".equals(base)) return "tl"; // Whisper's token for Filipino/Tagalog
        return base.isEmpty() ? "auto" : base;
    }

    private void writeWav(File out, byte[] pcm) throws Exception {
        File parent = out.getParentFile();
        if (parent != null && !parent.isDirectory()) parent.mkdirs();
        try (FileOutputStream fos = new FileOutputStream(out)) {
            int byteRate = WHISPER_RATE * 2;
            int dataSize = pcm.length;
            java.nio.ByteBuffer h = java.nio.ByteBuffer.allocate(44).order(java.nio.ByteOrder.LITTLE_ENDIAN);
            h.put("RIFF".getBytes()); h.putInt(36 + dataSize); h.put("WAVE".getBytes());
            h.put("fmt ".getBytes()); h.putInt(16); h.putShort((short) 1); h.putShort((short) 1);
            h.putInt(WHISPER_RATE); h.putInt(byteRate); h.putShort((short) 2); h.putShort((short) 16);
            h.put("data".getBytes()); h.putInt(dataSize);
            fos.write(h.array());
            fos.write(pcm);
        }
    }

    private void emitStt(final String state, final String reason) {
        main.post(() -> emitter.emit("quirky-stt-state",
                reason == null ? "{ state: '" + state + "' }"
                        : "{ state: '" + state + "', reason: '" + reason + "' }"));
    }

    private String jsEscape(String s) {
        if (s == null) return "";
        return s.replace("\\", "\\\\").replace("'", "\\'")
                .replace("\n", "\\n").replace("\r", "");
    }

    /** Called from MainActivity.onDestroy(). */
    public void release() {
        stopNativeTts(false);
        recording.set(false);
        try { if (recorder != null) recorder.release(); } catch (Exception ignored) { }
        recorder = null;
    }
}