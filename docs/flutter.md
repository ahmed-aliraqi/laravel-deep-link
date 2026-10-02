# Flutter Integration

How a Flutter app claims the domain and handles the links served by this package. The examples use the `Acme` demo app: domain `shop.example.com`, scheme `acme`, paths `/products/*` and `/stores/*`.

| Screen | Web link | Custom scheme |
|---|---|---|
| Product details | `https://shop.example.com/products/{id}` | `acme://products/{id}` |
| Store page | `https://shop.example.com/stores/{id}` | `acme://stores/{id}` |

Clients must share the `share_url` value returned by the API and never build URLs themselves.

## Prerequisites

The backend serves the verification files from its config. Until all values are set, both files return `404` and links open the landing page instead of the app:

| Config key | Where to find it | Example |
|---|---|---|
| `ios.team_id` | developer.apple.com → Membership details | `ABCDE12345` |
| `ios.bundle_id` | Xcode → Runner target → Signing & Capabilities | `com.example.acme` |
| `android.package` | `applicationId` in `android/app/build.gradle` | `com.example.acme` |
| `android.sha256_fingerprints` | Play Console → App integrity → App signing key certificate, plus the upload and debug keys | `AB:CD:…:89` |
| `scheme` | Chosen by the team | `acme` |
| `ios.store_url` / `android.store_url` | The store listings | `https://apps.apple.com/app/id…` |

> **Warning.** Builds installed from Google Play are signed with Google's Play App Signing key, not your upload key. If only the upload key fingerprint is registered, links work in local builds but fail for real users. Register both.

Print the debug key fingerprint:

```bash
keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android -keypass android | grep SHA256
```

## Android

Add both intent filters inside the main `<activity>` in `android/app/src/main/AndroidManifest.xml`. `autoVerify="true"` makes Android check `assetlinks.json` at install time, so links open the app without a chooser dialog.

```xml
<!-- Disable Flutter's built-in handler; app_links handles links instead -->
<meta-data android:name="flutter_deeplinking_enabled" android:value="false" />

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
```

Keep `android:launchMode="singleTop"` on the activity (the Flutter default). Otherwise a link tapped while the app is open starts a second copy of the activity instead of reaching the running app.

## iOS

In Xcode, open the Runner target → Signing & Capabilities → **+ Capability** → Associated Domains, and add `applinks:shop.example.com`. The capability must also be enabled for the App ID in the Apple Developer portal.

```xml
<!-- ios/Runner/Runner.entitlements -->
<key>com.apple.developer.associated-domains</key>
<array>
  <string>applinks:shop.example.com</string>
  <!-- While developing, bypass Apple's CDN cache: -->
  <!-- <string>applinks:shop.example.com?mode=developer</string> -->
</array>
```

```xml
<!-- ios/Runner/Info.plist -->
<key>FlutterDeepLinkingEnabled</key>
<false/>

<key>CFBundleURLTypes</key>
<array>
  <dict>
    <key>CFBundleURLName</key>
    <string>com.example.acme</string>
    <key>CFBundleURLSchemes</key>
    <array>
      <string>acme</string>
    </array>
  </dict>
</array>
```

> Apple fetches `apple-app-site-association` through its CDN when the app is installed, and may cache it for up to a day. After changing the Team ID or Bundle ID, reinstall the app. For development builds, use `?mode=developer` and enable Settings → Developer → Associated Domains Development on the device.

## Handling links in Dart

Use [`app_links`](https://pub.dev/packages/app_links) to receive links and [`share_plus`](https://pub.dev/packages/share_plus) to share them.

```bash
flutter pub add app_links share_plus
```

A `acme://products/42` URI puts `products` in the host, while `https://shop.example.com/products/42` puts it in the path. The parser handles both forms.

```dart
// lib/deep_links/deep_link.dart
sealed class DeepLink {
  const DeepLink();
}

class ProductLink extends DeepLink {
  const ProductLink(this.productId);
  final int productId;
}

class StoreLink extends DeepLink {
  const StoreLink(this.storeId);
  final int storeId;
}

const _scheme = 'acme';
const _hosts = {'shop.example.com', 'www.shop.example.com'};

DeepLink? parseDeepLink(Uri uri) {
  final List<String> segments;

  if (uri.scheme == _scheme) {
    segments = [uri.host, ...uri.pathSegments];
  } else if (uri.scheme == 'https' && _hosts.contains(uri.host)) {
    segments = uri.pathSegments;
  } else {
    return null;
  }

  final ids = segments.map(int.tryParse).toList();

  return switch (segments) {
    ['products', _] when ids[1] != null => ProductLink(ids[1]!),
    ['stores', _] when ids[1] != null => StoreLink(ids[1]!),
    _ => null,
  };
}
```

In `app_links` 6 and later, `uriLinkStream` also emits the link that launched the app. Subscribe once and do not also call `getInitialLink()`, or the cold-start link is handled twice. A link can arrive before the splash screen, session restore or router is ready, so hold it until the app can navigate.

```dart
// lib/deep_links/deep_link_service.dart
import 'dart:async';
import 'package:app_links/app_links.dart';
import 'deep_link.dart';

class DeepLinkService {
  DeepLinkService(this._navigate);

  final void Function(DeepLink link) _navigate;
  final _appLinks = AppLinks();
  StreamSubscription<Uri>? _subscription;
  DeepLink? _pending;
  bool _ready = false;

  void start() {
    _subscription ??= _appLinks.uriLinkStream.listen((uri) {
      final link = parseDeepLink(uri);
      if (link == null) return;
      _ready ? _navigate(link) : _pending = link;
    });
  }

  /// Call after splash / auth restore, once the router can push screens.
  void markReady() {
    _ready = true;
    final link = _pending;
    _pending = null;
    if (link != null) _navigate(link);
  }

  void dispose() => _subscription?.cancel();
}
```

Wire it to the router. This example uses `go_router`; adapt the route names to the app. Use `push` so the visitor can go back to where they were.

```dart
final deepLinks = DeepLinkService((link) {
  switch (link) {
    case ProductLink(:final productId):
      router.push('/products/$productId');
    case StoreLink(:final storeId):
      router.push('/stores/$storeId');
  }
})..start();

// After the splash screen finishes:
deepLinks.markReady();
```

- Keep the linked screens reachable for guests when possible, and only ask for login on actions that need auth.
- On `404`, show a short "This content is no longer available" message instead of an empty screen — an entity can be unpublished after its link was shared.

## Sharing

Share the `share_url` from the API. The server generates the link preview (title, image, description), so the shared text can stay short.

```dart
import 'package:share_plus/share_plus.dart';

Future<void> shareProduct(Product product) {
  return SharePlus.instance.share(
    ShareParams(text: '${product.name}\n${product.shareUrl}'),
  );
}

// Older share_plus versions: Share.share('${product.name}\n${product.shareUrl}');
```

Add `shareUrl` (`json['share_url']`) to the models returned by the API.

## Testing on devices

```bash
# Android: shop.example.com should be listed as "verified"
adb shell pm get-app-links com.example.acme
adb shell pm verify-app-links --re-verify com.example.acme
adb shell am start -a android.intent.action.VIEW -d "https://shop.example.com/products/42"
adb shell am start -a android.intent.action.VIEW -d "acme://stores/7"

# iOS simulator
xcrun simctl openurl booted "https://shop.example.com/products/42"
xcrun simctl openurl booted "acme://stores/7"
```

On a real iPhone, paste the link into Notes or Messages and tap it. Links typed into Safari's address bar never open the app — that is iOS behaviour, and the landing page covers it.

## Checklist

- Cold start: with the app closed, a product link opens the app on that product after the splash screen.
- Warm start: with the app in the background, a store link opens that store's screen.
- Logged out: the screen opens; actions that need auth ask for login.
- Not installed (Android): the link opens Google Play.
- Not installed (iPhone): the link opens the App Store after a short moment.
- Deleted/unpublished entity: the app shows the "no longer available" message.
- The WhatsApp preview shows the image and title.
- Tested with a release build from Play or TestFlight, not only a debug build, because the signing keys differ.
