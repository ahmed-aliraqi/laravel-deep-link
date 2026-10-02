# Android (Native) Integration

How a native Android app (Kotlin) claims the domain and handles the links served by this package. The examples use the `Acme` demo app: domain `shop.example.com`, scheme `acme`, paths `/products/*` and `/stores/*`.

## Prerequisites

The backend must serve `https://shop.example.com/.well-known/assetlinks.json` with the app's package name and the SHA-256 fingerprints of **every** signing key:

- the Play App Signing key (Play Console → App integrity → App signing key certificate),
- the upload key,
- the debug key for local development.

```bash
# Debug key fingerprint
keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey \
  -storepass android -keypass android | grep SHA256

# Release/upload key fingerprint
keytool -list -v -keystore release.keystore -alias <alias> | grep SHA256
```

> **Warning.** Builds installed from Google Play are signed with Google's Play App Signing key, not your upload key. If only the upload key is registered, links work in local builds but fail for real users.

## Manifest

Add both intent filters to the activity that handles navigation (usually `MainActivity`) in `AndroidManifest.xml`:

```xml
<activity
    android:name=".MainActivity"
    android:launchMode="singleTop"
    android:exported="true">

    <intent-filter>
        <action android:name="android.intent.action.MAIN" />
        <category android:name="android.intent.category.LAUNCHER" />
    </intent-filter>

    <!-- Verified App Links: https://shop.example.com/products/... and /stores/... -->
    <intent-filter android:autoVerify="true">
        <action android:name="android.intent.action.VIEW" />
        <category android:name="android.intent.category.DEFAULT" />
        <category android:name="android.intent.category.BROWSABLE" />
        <data android:scheme="https" android:host="shop.example.com" />
        <data android:pathPrefix="/products/" />
        <data android:pathPrefix="/stores/" />
    </intent-filter>

    <!-- Custom scheme fallback used by the landing page: acme://products/42 -->
    <intent-filter>
        <action android:name="android.intent.action.VIEW" />
        <category android:name="android.intent.category.DEFAULT" />
        <category android:name="android.intent.category.BROWSABLE" />
        <data android:scheme="acme" />
    </intent-filter>
</activity>
```

- `android:autoVerify="true"` makes Android fetch `assetlinks.json` at install time. When verification succeeds, links open the app directly without the "Open with" chooser.
- `android:launchMode="singleTop"` (or `singleTask`) makes a link tapped while the app is open arrive in `onNewIntent()` instead of launching a second activity.

## Parsing the link

Both URL forms must be handled: `https://shop.example.com/products/42` (path segments) and `acme://products/42` (the first segment is the URI *host*).

```kotlin
// DeepLink.kt
sealed interface DeepLink {
    data class Product(val id: Long) : DeepLink
    data class Store(val id: Long) : DeepLink
}

object DeepLinkParser {
    private const val SCHEME = "acme"
    private val HOSTS = setOf("shop.example.com", "www.shop.example.com")

    fun parse(uri: Uri): DeepLink? {
        val segments: List<String> = when {
            uri.scheme == SCHEME -> listOfNotNull(uri.host) + uri.pathSegments
            uri.scheme == "https" && uri.host in HOSTS -> uri.pathSegments
            else -> return null
        }

        return when {
            segments.size == 2 && segments[0] == "products" ->
                segments[1].toLongOrNull()?.let { DeepLink.Product(it) }
            segments.size == 2 && segments[0] == "stores" ->
                segments[1].toLongOrNull()?.let { DeepLink.Store(it) }
            else -> null
        }
    }
}
```

## Handling the intent

A link arrives in two ways: in `intent.data` when it launches the activity (cold start), and in `onNewIntent()` when the app is already running (`singleTop`). Handle both:

```kotlin
// MainActivity.kt
class MainActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        handleDeepLink(intent)
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        handleDeepLink(intent)
    }

    private fun handleDeepLink(intent: Intent) {
        val uri = intent.data ?: return
        val link = DeepLinkParser.parse(uri) ?: return

        when (link) {
            is DeepLink.Product -> navigator.openProduct(link.id)
            is DeepLink.Store -> navigator.openStore(link.id)
        }
    }
}
```

If navigation uses Jetpack Navigation, `<deepLink app:uri="https://shop.example.com/products/{id}" />` on the destination plus `navController.handleDeepLink(intent)` replaces the manual parser — keep the custom-scheme filter either way, because the landing page uses it.

A cold-start link can arrive before session restore finishes. Hold the parsed link in a field (or a `StateFlow`) and navigate once the start-up flow completes, the same pattern as any splash-screen app.

## Sharing

Share the `share_url` value from the API. The server renders the link preview, so the text can stay short:

```kotlin
fun shareProduct(context: Context, product: Product) {
    val intent = Intent(Intent.ACTION_SEND).apply {
        type = "text/plain"
        putExtra(Intent.EXTRA_TEXT, "${product.name}\n${product.shareUrl}")
    }
    context.startActivity(Intent.createChooser(intent, null))
}
```

## Testing on devices

```bash
# Verification state: shop.example.com should be listed as "verified"
adb shell pm get-app-links com.example.acme

# Force re-verification after changing assetlinks.json
adb shell pm verify-app-links --re-verify com.example.acme

# Open links
adb shell am start -a android.intent.action.VIEW -d "https://shop.example.com/products/42"
adb shell am start -a android.intent.action.VIEW -d "acme://stores/7"
```

## Checklist

- Cold start: with the app closed, a product link opens the app on that product.
- Warm start: with the app in the background, a link reaches `onNewIntent()` and navigates without restarting the task.
- `adb shell pm get-app-links` reports the domain as `verified`.
- Not installed: the link opens the landing page, which redirects to Google Play.
- Deleted/unpublished entity: the app shows a "no longer available" message on `404`.
- Tested with a release build from Google Play (Play App Signing key), not only a debug build.
