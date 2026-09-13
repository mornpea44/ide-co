package com.quirky.ide;

import android.app.ActivityManager;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.os.BatteryManager;
import android.os.Environment;
import android.os.StatFs;
import android.webkit.JavascriptInterface;
import org.json.JSONObject;

public class HardwareBridge {
    private final Context context;

    public HardwareBridge(Context context) {
        this.context = context;
    }

    @JavascriptInterface
    public String getDeviceSpecs() {
        try {
            // 1. RAM
            ActivityManager am = (ActivityManager) context.getSystemService(Context.ACTIVITY_SERVICE);
            ActivityManager.MemoryInfo memInfo = new ActivityManager.MemoryInfo();
            am.getMemoryInfo(memInfo);
            long ramMb = memInfo.totalMem / (1024 * 1024);

            // 2. CPU Cores
            int cores = Runtime.getRuntime().availableProcessors();

            // 3. Available Storage
            StatFs stat = new StatFs(Environment.getDataDirectory().getPath());
            long storageMb = (stat.getAvailableBlocksLong() * stat.getBlockSizeLong()) / (1024 * 1024);

            // 4. Determine Tier
            String tier;
            if (ramMb < 3000 || cores <= 4 || storageMb < 2000) {
                tier = "low";
            } else if (ramMb < 6000) {
                tier = "medium";
            } else {
                tier = "high";
            }

            JSONObject json = new JSONObject();
            json.put("ramMb", ramMb);
            json.put("cores", cores);
            json.put("storageMb", storageMb);
            json.put("tier", tier);

            return json.toString();
        } catch (Exception e) {
            return "{\"error\":\"" + e.getMessage() + "\"}";
        }
    }

    // Helper to get just the tier for the URL parameter
    public String getTierOnly() {
        try {
            ActivityManager am = (ActivityManager) context.getSystemService(Context.ACTIVITY_SERVICE);
            ActivityManager.MemoryInfo memInfo = new ActivityManager.MemoryInfo();
            am.getMemoryInfo(memInfo);
            long ramMb = memInfo.totalMem / (1024 * 1024);
            int cores = Runtime.getRuntime().availableProcessors();
            StatFs stat = new StatFs(Environment.getDataDirectory().getPath());
            long storageMb = (stat.getAvailableBlocksLong() * stat.getBlockSizeLong()) / (1024 * 1024);

            if (ramMb < 3000 || cores <= 4 || storageMb < 2000) return "low";
            if (ramMb < 6000) return "medium";
            return "high";
        } catch (Exception e) {
            return "low";
        }
    }

    /**
     * ★ NEW (v10): live power/memory telemetry for the web IDE.
     * window.AndroidHardware.getPowerProfile() → JSON string:
     *   { batteryPct, charging, availMemMb, lowMemory }
     * The page polls this at most once a minute (or on demand) and can
     * degrade heavy features when the phone is hot/low on RAM/battery —
     * same spirit as the existing device-tier policy.
     */
    @JavascriptInterface
    public String getPowerProfile() {
        JSONObject json = new JSONObject();
        try {
            int batteryPct = -1;
            boolean charging = false;
            try {
                Intent batteryIntent = context.registerReceiver(null,
                        new IntentFilter(Intent.ACTION_BATTERY_CHANGED));
                if (batteryIntent != null) {
                    int level = batteryIntent.getIntExtra(BatteryManager.EXTRA_LEVEL, -1);
                    int scale = batteryIntent.getIntExtra(BatteryManager.EXTRA_SCALE, -1);
                    if (level >= 0 && scale > 0) batteryPct = (level * 100) / scale;
                    int status = batteryIntent.getIntExtra(BatteryManager.EXTRA_STATUS, -1);
                    charging = status == BatteryManager.BATTERY_STATUS_CHARGING
                            || status == BatteryManager.BATTERY_STATUS_FULL;
                }
            } catch (Exception ignored) { }

            ActivityManager am = (ActivityManager)
                    context.getSystemService(Context.ACTIVITY_SERVICE);
            ActivityManager.MemoryInfo memInfo = new ActivityManager.MemoryInfo();
            am.getMemoryInfo(memInfo);

            json.put("batteryPct", batteryPct);
            json.put("charging", charging);
            json.put("availMemMb", memInfo.availMem / (1024 * 1024));
            json.put("lowMemory", memInfo.lowMemory);
            return json.toString();
        } catch (Exception e) {
            return "{\"error\":\"" + e.getMessage() + "\"}";
        }
    }
}