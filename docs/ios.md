# iOS (Native) Integration

How a native iOS app (Swift) claims the domain and handles the links served by this package. The examples use the `Acme` demo app: domain `shop.example.com`, scheme `acme`, paths `/products/*` and `/stores/*`.

## Prerequisites

The backend must serve `https://shop.example.com/.well-known/apple-app-site-association` (no redirects, `application/json`, HTTPS). It is generated from the configured Team ID and Bundle ID — until both are set, the file returns `404`.

> Apple does not fetch the file from your server directly. Devices download it through Apple's CDN (`app-site-association.cdn-apple.com`), which caches it for up to a day. After changing the Team ID or Bundle ID, reinstall the app and check what the CDN serves:
>
> ```bash
> curl -s https://app-site-association.cdn-apple.com/a/v1/shop.example.com
> ```

## Associated Domains

In Xcode: target → Signing & Capabilities → **+ Capability** → Associated Domains, then add the domain. The capability must also be enabled for the App ID in the Apple Developer portal.

```xml
<!-- Acme.entitlements -->
<key>com.apple.developer.associated-domains</key>
<array>
  <string>applinks:shop.example.com</string>
  <!-- While developing, bypass Apple's CDN cache: -->
  <!-- <string>applinks:shop.example.com?mode=developer</string> -->
</array>
```

For development builds with `?mode=developer`, also enable Settings → Developer → Associated Domains Development on the device.

## Custom scheme

The landing page falls back to `acme://` when Universal Links were not triggered. Register the scheme in `Info.plist`:

```xml
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

## Parsing the link

Both URL forms must be handled: `https://shop.example.com/products/42` (path segments) and `acme://products/42` (the first segment is the URL *host*).

```swift
// DeepLink.swift
enum DeepLink: Equatable {
    case product(id: Int)
    case store(id: Int)

    private static let scheme = "acme"
    private static let hosts: Set<String> = ["shop.example.com", "www.shop.example.com"]

    init?(url: URL) {
        let segments: [String]

        if url.scheme == Self.scheme {
            segments = [url.host].compactMap { $0 } + url.pathComponents.filter { $0 != "/" }
        } else if url.scheme == "https", let host = url.host, Self.hosts.contains(host) {
            segments = url.pathComponents.filter { $0 != "/" }
        } else {
            return nil
        }

        switch (segments.first, segments.count > 1 ? Int(segments[1]) : nil) {
        case ("products", let id?): self = .product(id: id)
        case ("stores", let id?): self = .store(id: id)
        default: return nil
        }
    }
}
```

## Handling links — SwiftUI

Universal Links arrive as an `NSUserActivity`; custom scheme links arrive as a plain `URL`. Handle both:

```swift
@main
struct AcmeApp: App {
    @StateObject private var router = Router()

    var body: some Scene {
        WindowGroup {
            ContentView()
                .environmentObject(router)
                // Custom scheme: acme://products/42
                .onOpenURL { url in
                    handle(url)
                }
                // Universal Links: https://shop.example.com/products/42
                .onContinueUserActivity(NSUserActivityTypeBrowsingWeb) { activity in
                    guard let url = activity.webpageURL else { return }
                    handle(url)
                }
        }
    }

    private func handle(_ url: URL) {
        guard let link = DeepLink(url: url) else { return }

        switch link {
        case .product(let id): router.push(.productDetails(id: id))
        case .store(let id): router.push(.storeDetails(id: id))
        }
    }
}
```

A cold-start link can arrive before session restore finishes. Buffer the parsed `DeepLink` in the router and apply it once the root view is ready, the same pattern as push-notification navigation.

## Handling links — UIKit (SceneDelegate)

```swift
// Cold start
func scene(_ scene: UIScene, willConnectTo session: UISceneSession,
           options connectionOptions: UIScene.ConnectionOptions) {
    if let activity = connectionOptions.userActivities.first,
       activity.activityType == NSUserActivityTypeBrowsingWeb,
       let url = activity.webpageURL {
        handle(url)
    }
    if let url = connectionOptions.urlContexts.first?.url {
        handle(url)
    }
}

// Universal Link while running
func scene(_ scene: UIScene, continue userActivity: NSUserActivity) {
    guard userActivity.activityType == NSUserActivityTypeBrowsingWeb,
          let url = userActivity.webpageURL else { return }
    handle(url)
}

// Custom scheme while running
func scene(_ scene: UIScene, openURLContexts URLContexts: Set<UIOpenURLContext>) {
    guard let url = URLContexts.first?.url else { return }
    handle(url)
}
```

## Sharing

Share the `share_url` value from the API. The server renders the link preview, so the shared text can stay short:

```swift
// SwiftUI
ShareLink(item: URL(string: product.shareUrl)!) {
    Label("Share", systemImage: "square.and.arrow.up")
}

// UIKit
let controller = UIActivityViewController(
    activityItems: [product.name, URL(string: product.shareUrl)!],
    applicationActivities: nil
)
present(controller, animated: true)
```

## Smart App Banner

The landing page emits `<meta name="apple-itunes-app" content="app-id=…, app-argument=…">` automatically when the App Store URL is configured on the backend. Safari then shows **Open** when the app is installed and **Get** otherwise — no app-side work needed.

## Testing

```bash
# Simulator
xcrun simctl openurl booted "https://shop.example.com/products/42"
xcrun simctl openurl booted "acme://stores/7"
```

On a real iPhone, paste the link into Notes or Messages and tap it. Links typed into Safari's address bar **never** open the app — that is iOS behaviour, and the landing page covers it. Long-press a link to check which options iOS offers ("Open in Acme" confirms verification worked).

## Checklist

- Cold start: with the app closed, a product link opens the app on that product.
- Warm start: with the app in the background, a store link navigates without restarting.
- Pasting a link into Notes and tapping it opens the app, not Safari.
- Not installed: the link opens the landing page, which redirects to the App Store.
- Deleted/unpublished entity: the app shows a "no longer available" message on `404`.
- Tested with a TestFlight build — the Associated Domains capability must be present in the distribution profile.
