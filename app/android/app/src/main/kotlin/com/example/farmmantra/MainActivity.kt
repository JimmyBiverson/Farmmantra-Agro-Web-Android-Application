package com.example.farmmantra

import android.Manifest
import android.content.ContentValues
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.Build
import android.os.Bundle
import android.os.Environment
import android.os.Handler
import android.os.Looper
import android.provider.MediaStore
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.io.File

class MainActivity : FlutterActivity() {
    private var pendingLocation: MethodChannel.Result? = null
    private var listener: LocationListener? = null
    private val handler = Handler(Looper.getMainLooper())
    override fun configureFlutterEngine(engine: FlutterEngine) {
        super.configureFlutterEngine(engine)
        MethodChannel(engine.dartExecutor.binaryMessenger, "farmmantra/operations").setMethodCallHandler { call, result ->
            when (call.method) {
                "location" -> {
                    if (pendingLocation != null) { result.error("BUSY", "A location request is already running.", null) }
                    else {
                        pendingLocation = result
                        if (checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
                            requestPermissions(arrayOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION), 701)
                        } else { captureLocation() }
                    }
                }
                "saveWorkbook", "saveDocument" -> {
                    try {
                        val bytes = call.argument<ByteArray>("bytes") ?: throw IllegalArgumentException("No workbook received")
                        val name = (call.argument<String>("name") ?: "farmmantra-report.xlsx").replace(Regex("[^A-Za-z0-9._-]"), "_")
                        if (Build.VERSION.SDK_INT >= 29) {
                            val values = ContentValues().apply {
                                put(MediaStore.Downloads.DISPLAY_NAME, name)
                                put(MediaStore.Downloads.MIME_TYPE, call.argument<String>("mime") ?: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")
                                put(MediaStore.Downloads.RELATIVE_PATH, Environment.DIRECTORY_DOWNLOADS + "/Farmmantra")
                            }
                            val uri = contentResolver.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values) ?: throw IllegalStateException("Cannot create file")
                            contentResolver.openOutputStream(uri)?.use { it.write(bytes) } ?: throw IllegalStateException("Cannot write file")
                            result.success("Downloads/Farmmantra/$name")
                        } else {
                            val file = File(getExternalFilesDir(Environment.DIRECTORY_DOCUMENTS), name)
                            file.writeBytes(bytes)
                            result.success(file.absolutePath)
                        }
                    } catch (error: Exception) { result.error("SAVE_FAILED", error.message, null) }
                }
                else -> result.notImplemented()
            }
        }
    }
    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == 701) {
            if (grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED) { captureLocation() }
            else { finishLocation(null, "Precise location permission is needed for this check-in.") }
        }
    }
    @Suppress("DEPRECATION")
    private fun captureLocation() {
        val manager = getSystemService(LOCATION_SERVICE) as LocationManager
        val provider = if (manager.isProviderEnabled(LocationManager.GPS_PROVIDER)) LocationManager.GPS_PROVIDER else if (manager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)) LocationManager.NETWORK_PROVIDER else null
        if (provider == null) { finishLocation(null, "Enable location services and try again."); return }
        listener = object : LocationListener {
            override fun onLocationChanged(location: Location) {
                if (location.isFromMockProvider) { finishLocation(null, "Mock locations cannot be used for a field check-in."); return }
                if (System.currentTimeMillis() - location.time > 120000 || location.accuracy > 100) { return }
                finishLocation(mapOf("latitude" to location.latitude, "longitude" to location.longitude, "accuracy" to location.accuracy.toDouble(), "timestamp" to location.time), null)
            }
            override fun onProviderDisabled(provider: String) {}
            override fun onProviderEnabled(provider: String) {}
            override fun onStatusChanged(provider: String?, status: Int, extras: Bundle?) {}
        }
        try {
            manager.requestLocationUpdates(provider, 1000L, 0f, listener!!, Looper.getMainLooper())
            handler.postDelayed({ if (pendingLocation != null) finishLocation(null, "A precise location fix could not be obtained. Try outdoors.") }, 25000)
        } catch (error: SecurityException) { finishLocation(null, "Location access is unavailable.") }
    }
    private fun finishLocation(value: Map<String, Any>?, error: String?) {
        listener?.let { (getSystemService(LOCATION_SERVICE) as LocationManager).removeUpdates(it) }
        listener = null
        val result = pendingLocation
        pendingLocation = null
        handler.removeCallbacksAndMessages(null)
        if (error == null) result?.success(value) else result?.error("LOCATION", error, null)
    }
    override fun onDestroy() { finishLocation(null, "Location request cancelled."); super.onDestroy() }
}
