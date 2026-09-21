package com.ovanie.ovanie_app

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.location.Address
import android.location.Geocoder
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.CancellationSignal
import android.os.Handler
import android.os.Looper
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import androidx.core.app.NotificationCompat
import java.util.Locale

class MainActivity : FlutterActivity() {
    private val externalUrlChannelName = "ovanie/external_url"
    private val storageChannelName = "ovanie/device_storage"
    private val locationChannelName = "ovanie/location"
    private val deepLinkChannelName = "ovanie/deep_link"
    private val notificationsChannelName = "ovanie/notifications"
    private val preferencesName = "ovanie_mobile_storage"
    private var deepLinkChannel: MethodChannel? = null
    private var pendingDeepLink: String? = null

    private val locationPermissionRequestCode = 7041
    private var pendingLocationResult: MethodChannel.Result? = null
    private var currentLocationListener: LocationListener? = null
    private val locationHandler = Handler(Looper.getMainLooper())
    private var locationTimeoutRunnable: Runnable? = null
    private val currentLocationCancellationSignals = mutableListOf<CancellationSignal>()
    private var bestPendingLocation: Location? = null

    // V66 : la haute précision du vrai téléphone est gérée par le plugin
    // Geolocator côté Flutter (FusedLocationProviderClient par défaut).

    override fun onCreate(savedInstanceState: Bundle?) {
        pendingDeepLink = intent?.dataString
        super.onCreate(savedInstanceState)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            val channel = NotificationChannel(
                "ovanie_client_updates",
                "Mises à jour OVANIE",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Commandes, paiements, livraisons et support OVANIE"
            }
            manager.createNotificationChannel(channel)
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        val link = intent.dataString?.trim().orEmpty()
        if (link.isBlank()) return

        pendingDeepLink = link
        deepLinkChannel?.invokeMethod("onDeepLink", link)
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        deepLinkChannel = MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            deepLinkChannelName
        ).also { channel ->
            channel.setMethodCallHandler { call, result ->
                when (call.method) {
                    "getInitialLink" -> {
                        val link = pendingDeepLink ?: intent?.dataString
                        pendingDeepLink = null
                        result.success(link)
                    }
                    else -> result.notImplemented()
                }
            }
        }

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            externalUrlChannelName
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "openUrl" -> {
                    val url = call.argument<String>("url")?.trim().orEmpty()
                    if (url.isBlank()) {
                        result.success(false)
                        return@setMethodCallHandler
                    }

                    try {
                        val uri = Uri.parse(url)
                        val scheme = uri.scheme?.lowercase()
                        val allowedSchemes = setOf(
                            "http",
                            "https",
                            "tel",
                            "intent",
                            "market",
                            "wave",
                            "waveci"
                        )
                        if (scheme !in allowedSchemes) {
                            result.success(false)
                            return@setMethodCallHandler
                        }

                        val targetIntent = when (scheme) {
                            "tel" -> Intent(Intent.ACTION_DIAL, uri)
                            "intent" -> Intent.parseUri(url, Intent.URI_INTENT_SCHEME).apply {
                                addCategory(Intent.CATEGORY_BROWSABLE)
                                component = null
                                selector = null
                            }
                            else -> Intent(Intent.ACTION_VIEW, uri).apply {
                                addCategory(Intent.CATEGORY_BROWSABLE)
                            }
                        }

                        try {
                            startActivity(targetIntent)
                        } catch (_: ActivityNotFoundException) {
                            if (scheme != "intent") throw ActivityNotFoundException()

                            val fallbackUrl = targetIntent.getStringExtra(
                                "browser_fallback_url"
                            )?.trim().orEmpty()
                            if (fallbackUrl.isBlank()) throw ActivityNotFoundException()

                            startActivity(
                                Intent(Intent.ACTION_VIEW, Uri.parse(fallbackUrl)).apply {
                                    addCategory(Intent.CATEGORY_BROWSABLE)
                                }
                            )
                        }
                        result.success(true)
                    } catch (_: ActivityNotFoundException) {
                        result.success(false)
                    } catch (_: Exception) {
                        result.success(false)
                    }
                }

                else -> result.notImplemented()
            }
        }

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            notificationsChannelName
        ).setMethodCallHandler { call, result ->
            if (call.method != "show") {
                result.notImplemented()
                return@setMethodCallHandler
            }

            val title = call.argument<String>("title")?.trim().orEmpty()
                .ifBlank { "OVANIE" }
            val body = call.argument<String>("body")?.trim().orEmpty()
            showForegroundNotification(title, body)
            result.success(true)
        }

        val preferences = getSharedPreferences(
            preferencesName,
            Context.MODE_PRIVATE
        )

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            storageChannelName
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "readString" -> {
                    val key = call.argument<String>("key")?.trim().orEmpty()
                    if (key.isBlank()) {
                        result.success(null)
                    } else {
                        result.success(preferences.getString(key, null))
                    }
                }

                "writeString" -> {
                    val key = call.argument<String>("key")?.trim().orEmpty()
                    val value = call.argument<String>("value")
                    if (key.isBlank() || value == null) {
                        result.success(false)
                    } else {
                        // commit() garantit que panier/session/mutations sont
                        // réellement écrits avant de répondre à Flutter.
                        val saved = preferences.edit().putString(key, value).commit()
                        result.success(saved)
                    }
                }

                "remove" -> {
                    val key = call.argument<String>("key")?.trim().orEmpty()
                    val removed = if (key.isNotBlank()) {
                        preferences.edit().remove(key).commit()
                    } else {
                        true
                    }
                    result.success(removed)
                }

                else -> result.notImplemented()
            }
        }

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            locationChannelName
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "getCurrentPosition" -> requestCurrentPosition(result)
                "getGpsSnapshot" -> requestGpsSnapshot(result)
                "isEmulator" -> result.success(isProbablyEmulator())
                "hasFineLocationPermission" -> result.success(hasFineLocationPermission())
                "reverseGeocode" -> requestReverseGeocode(call.arguments, result)
                else -> result.notImplemented()
            }
        }
    }

    private fun showForegroundNotification(title: String, body: String) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) !=
                PackageManager.PERMISSION_GRANTED
        ) return

        val launchIntent = packageManager.getLaunchIntentForPackage(packageName)
            ?.apply { flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP }
        val pendingIntent = launchIntent?.let {
            PendingIntent.getActivity(
                this,
                0,
                it,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
        }

        val notification = NotificationCompat.Builder(this, "ovanie_client_updates")
            .setSmallIcon(applicationInfo.icon)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .build()

        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        manager.notify((System.currentTimeMillis() % Int.MAX_VALUE).toInt(), notification)
    }

    private fun requestReverseGeocode(arguments: Any?, result: MethodChannel.Result) {
        val args = arguments as? Map<*, *>
        val latitude = (args?.get("latitude") as? Number)?.toDouble()
        val longitude = (args?.get("longitude") as? Number)?.toDouble()

        if (latitude == null || longitude == null ||
            latitude !in -90.0..90.0 || longitude !in -180.0..180.0
        ) {
            result.error(
                "invalid_coordinates",
                "Les coordonnées GPS sont invalides.",
                null
            )
            return
        }

        if (!Geocoder.isPresent()) {
            result.error(
                "geocoder_unavailable",
                "Le service d'adresse Android n'est pas disponible.",
                null
            )
            return
        }

        val geocoder = Geocoder(this, Locale.FRENCH)

        // Exécuté hors du thread UI : le géocodage peut utiliser le réseau.
        Thread {
            try {
                @Suppress("DEPRECATION")
                val addresses = geocoder.getFromLocation(latitude, longitude, 1)
                val address = addresses?.firstOrNull()

                runOnUiThread {
                    if (address == null) {
                        result.success(null)
                    } else {
                        result.success(addressPayload(address, latitude, longitude))
                    }
                }
            } catch (error: Exception) {
                runOnUiThread {
                    result.error(
                        "reverse_geocode_failed",
                        error.message ?: "Impossible d'identifier l'adresse.",
                        null
                    )
                }
            }
        }.start()
    }

    private fun addressPayload(
        address: Address,
        latitude: Double,
        longitude: Double
    ): Map<String, Any> {
        val lines = mutableListOf<String>()
        val maxLine = address.maxAddressLineIndex
        if (maxLine >= 0) {
            for (index in 0..maxLine) {
                val value = address.getAddressLine(index)?.trim().orEmpty()
                if (value.isNotBlank() && !lines.contains(value)) {
                    lines.add(value)
                }
            }
        }

        val fallbackParts = listOf(
            address.featureName,
            address.thoroughfare,
            address.subThoroughfare,
            address.subLocality,
            address.locality,
            address.subAdminArea,
            address.adminArea,
            address.countryName
        ).mapNotNull { it?.trim()?.takeIf(String::isNotBlank) }
            .distinct()

        val displayName = when {
            lines.isNotEmpty() -> lines.joinToString(", ")
            fallbackParts.isNotEmpty() -> fallbackParts.joinToString(", ")
            else -> "Position actuelle détectée"
        }

        return mapOf(
            "displayName" to displayName,
            "latitude" to latitude,
            "longitude" to longitude,
            "featureName" to address.featureName.orEmpty(),
            "thoroughfare" to address.thoroughfare.orEmpty(),
            "subThoroughfare" to address.subThoroughfare.orEmpty(),
            "subLocality" to address.subLocality.orEmpty(),
            "locality" to address.locality.orEmpty(),
            "subAdminArea" to address.subAdminArea.orEmpty(),
            "adminArea" to address.adminArea.orEmpty(),
            "countryName" to address.countryName.orEmpty(),
            "postalCode" to address.postalCode.orEmpty()
        )
    }

    private fun requestCurrentPosition(result: MethodChannel.Result) {
        if (pendingLocationResult != null) {
            result.error(
                "location_busy",
                "Une recherche de position est déjà en cours.",
                null
            )
            return
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M && !hasLocationPermission()) {
            pendingLocationResult = result
            requestPermissions(
                arrayOf(
                    Manifest.permission.ACCESS_FINE_LOCATION,
                    Manifest.permission.ACCESS_COARSE_LOCATION
                ),
                locationPermissionRequestCode
            )
            return
        }

        // Un AVD n'a pas de GPS physique. Android Studio injecte une position
        // simulée via Extended Controls > Location et Android expose ce point via
        // getLastKnownLocation("gps"). Le timestamp peut rester ancien si le
        // point ne bouge pas ; sur émulateur on accepte donc le snapshot courant.
        // Cette règle ne s'applique JAMAIS à un vrai téléphone.
        if (isProbablyEmulator()) {
            val snapshot = currentGpsSnapshot()
            if (snapshot != null) {
                result.success(locationPayload(snapshot))
                return
            }

            // Aucun point n'a encore été injecté : attendre un nouveau callback
            // permet à l'utilisateur de définir Set Location sans redémarrer OVANIE.
            startFreshLocationRequest(result)
            return
        }

        startFreshLocationRequest(result)
    }

    private fun requestGpsSnapshot(result: MethodChannel.Result) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M && !hasLocationPermission()) {
            result.error(
                "permission_denied",
                "La permission de localisation a été refusée.",
                null
            )
            return
        }

        val snapshot = currentGpsSnapshot()
        if (snapshot == null) {
            result.success(null)
        } else {
            result.success(locationPayload(snapshot))
        }
    }

    private fun currentGpsSnapshot(): Location? {
        val manager = getSystemService(Context.LOCATION_SERVICE) as LocationManager
        return try {
            // Sur l'émulateur, Set Location met à jour GPS_PROVIDER. Certains
            // AVD signalent pourtant brièvement le provider comme désactivé ;
            // lire le snapshot reste alors la meilleure représentation du point
            // simulé courant. Sur téléphone réel cette méthode n'est qu'un secours.
            manager.getLastKnownLocation(LocationManager.GPS_PROVIDER)
        } catch (_: SecurityException) {
            null
        } catch (_: Exception) {
            null
        }
    }


    private fun isProbablyEmulator(): Boolean {
        return Build.FINGERPRINT.startsWith("generic") ||
            Build.FINGERPRINT.lowercase().contains("emulator") ||
            Build.FINGERPRINT.lowercase().contains("vbox") ||
            Build.MODEL.contains("google_sdk", ignoreCase = true) ||
            Build.MODEL.contains("Emulator", ignoreCase = true) ||
            Build.MODEL.contains("Android SDK built for", ignoreCase = true) ||
            Build.MANUFACTURER.contains("Genymotion", ignoreCase = true) ||
            Build.PRODUCT.contains("sdk_gphone", ignoreCase = true) ||
            Build.PRODUCT.contains("emulator", ignoreCase = true) ||
            Build.HARDWARE.contains("goldfish", ignoreCase = true) ||
            Build.HARDWARE.contains("ranchu", ignoreCase = true)
    }

    private fun hasFineLocationPermission(): Boolean {
        return Build.VERSION.SDK_INT < Build.VERSION_CODES.M ||
            checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION) ==
                PackageManager.PERMISSION_GRANTED
    }

    private fun hasLocationPermission(): Boolean {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return true
        return hasFineLocationPermission() ||
            checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION) ==
                PackageManager.PERMISSION_GRANTED
    }

    private fun startFreshLocationRequest(result: MethodChannel.Result) {
        val manager = getSystemService(Context.LOCATION_SERVICE) as LocationManager

        try {
            val providers = mutableListOf<String>()

            // V52 : sur un vrai téléphone on ne bloque plus uniquement sur GPS.
            // Le fournisseur fusionné/réseau peut donner rapidement une position
            // précise à l'intérieur, pendant que le GNSS continue à converger.
            val available = try {
                manager.allProviders.toSet()
            } catch (_: Exception) {
                emptySet()
            }

            fun enabled(provider: String): Boolean = try {
                provider in available && manager.isProviderEnabled(provider)
            } catch (_: Exception) {
                false
            }

            // V62 : demander plusieurs fournisseurs en parallèle, tout en gardant
            // fused/network comme accélérateurs de cold start. Le meilleur
            // point reste choisi par sa précision réelle.
            if (enabled(LocationManager.GPS_PROVIDER)) {
                providers.add(LocationManager.GPS_PROVIDER)
            }
            if (enabled("fused")) providers.add("fused")
            if (enabled(LocationManager.NETWORK_PROVIDER)) {
                providers.add(LocationManager.NETWORK_PROVIDER)
            }

            if (providers.isEmpty()) {
                result.error(
                    "location_disabled",
                    "La localisation Android est désactivée.",
                    null
                )
                return
            }

            pendingLocationResult = result
            bestPendingLocation = null

            val listener = object : LocationListener {
                override fun onLocationChanged(location: Location) {
                    val ageMs = kotlin.math.abs(System.currentTimeMillis() - location.time)
                    if (location.time > 0L && ageMs > 60_000L) {
                        return
                    }

                    val currentBest = bestPendingLocation
                    if (currentBest == null || isBetterLocation(location, currentBest)) {
                        bestPendingLocation = location
                    }

                    // V60 : une position <= 5 m peut être retournée immédiatement.
                    // Les callbacks réseau plus grossiers ne gagnent pas contre un
                    // fix GNSS plus précis attendu pendant la fenêtre d'acquisition.
                    if (location.hasAccuracy() && location.accuracy <= 5f) {
                        completeLocationSuccess(location)
                    }
                }

                override fun onProviderEnabled(provider: String) {}
                override fun onProviderDisabled(provider: String) {}
                @Deprecated("Deprecated in Android")
                override fun onStatusChanged(
                    provider: String?,
                    status: Int,
                    extras: Bundle?
                ) {}
            }
            currentLocationListener = listener

            for (provider in providers.distinct()) {
                try {
                    // API 30+ : demander aussi explicitement une position courante.
                    // Android recommande getCurrentLocation pour un fix ponctuel ;
                    // requestLocationUpdates reste actif quelques secondes afin de
                    // laisser GPS/Fused améliorer la précision.
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                        val signal = CancellationSignal()
                        currentLocationCancellationSignals.add(signal)
                        manager.getCurrentLocation(
                            provider,
                            signal,
                            mainExecutor
                        ) { current ->
                            if (current != null) listener.onLocationChanged(current)
                        }
                    }

                    manager.requestLocationUpdates(
                        provider,
                        0L,
                        0f,
                        listener,
                        Looper.getMainLooper()
                    )
                } catch (_: SecurityException) {
                    completeLocationError(
                        "permission_denied",
                        "La permission de localisation a été refusée."
                    )
                    return
                } catch (_: IllegalArgumentException) {
                    // Un fournisseur optionnel peut ne pas être disponible sur
                    // certains constructeurs. Les autres restent actifs.
                }
            }

            val timeout = Runnable {
                val best = bestPendingLocation
                if (best != null) {
                    completeLocationSuccess(best)
                } else {
                    completeLocationError(
                        "location_timeout",
                        "Aucune nouvelle position n'a été reçue."
                    )
                }
            }
            locationTimeoutRunnable = timeout
            locationHandler.postDelayed(timeout, 12_000L)
        } catch (_: SecurityException) {
            result.error(
                "permission_denied",
                "La permission de localisation a été refusée.",
                null
            )
        } catch (error: Exception) {
            result.error(
                "location_unavailable",
                error.message ?: "Impossible d'obtenir la position.",
                null
            )
        }
    }

    private fun isBetterLocation(candidate: Location, current: Location): Boolean {
        val candidateAccuracy = if (candidate.hasAccuracy()) candidate.accuracy else Float.MAX_VALUE
        val currentAccuracy = if (current.hasAccuracy()) current.accuracy else Float.MAX_VALUE

        if (candidateAccuracy + 5f < currentAccuracy) return true
        if (currentAccuracy + 5f < candidateAccuracy) return false

        return candidate.time >= current.time
    }

    private fun completeLocationSuccess(location: Location) {
        val result = pendingLocationResult ?: return
        cleanupLocationRequest()
        pendingLocationResult = null
        bestPendingLocation = null
        result.success(locationPayload(location))
    }

    private fun completeLocationError(code: String, message: String) {
        val result = pendingLocationResult ?: return
        cleanupLocationRequest()
        pendingLocationResult = null
        bestPendingLocation = null
        result.error(code, message, null)
    }

    private fun cleanupLocationRequest() {
        locationTimeoutRunnable?.let { locationHandler.removeCallbacks(it) }
        locationTimeoutRunnable = null

        for (signal in currentLocationCancellationSignals.toList()) {
            try {
                signal.cancel()
            } catch (_: Exception) {
                // Rien à faire.
            }
        }
        currentLocationCancellationSignals.clear()

        val listener = currentLocationListener
        if (listener != null) {
            try {
                val manager = getSystemService(Context.LOCATION_SERVICE) as LocationManager
                manager.removeUpdates(listener)
            } catch (_: Exception) {
                // Rien à faire.
            }
        }
        currentLocationListener = null
        bestPendingLocation = null
    }

    private fun locationPayload(location: Location): Map<String, Any> {
        val mocked = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            location.isMock
        } else {
            @Suppress("DEPRECATION")
            location.isFromMockProvider
        }

        return mapOf(
            "latitude" to location.latitude,
            "longitude" to location.longitude,
            "accuracy" to location.accuracy.toDouble(),
            "provider" to (location.provider ?: "android"),
            "timestampMillis" to location.time,
            "isMocked" to mocked,
            "isEmulator" to isProbablyEmulator()
        )
    }

    override fun onRequestPermissionsResult(
        requestCode: Int,
        permissions: Array<out String>,
        grantResults: IntArray
    ) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)

        if (requestCode != locationPermissionRequestCode) return
        val result = pendingLocationResult ?: return
        pendingLocationResult = null

        val granted = grantResults.any { it == PackageManager.PERMISSION_GRANTED }
        if (!granted) {
            result.error(
                "permission_denied",
                "La permission de localisation a été refusée.",
                null
            )
            return
        }

        requestCurrentPosition(result)
    }

    override fun onDestroy() {
        cleanupLocationRequest()
        pendingLocationResult?.error(
            "location_unavailable",
            "La recherche de position a été interrompue.",
            null
        )
        pendingLocationResult = null
        super.onDestroy()
    }
}
