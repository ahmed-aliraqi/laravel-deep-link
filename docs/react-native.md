# React Native Integration

How a React Native app claims the domain and handles the links served by this package. The examples use the `Acme` demo app: domain `shop.example.com`, scheme `acme`, paths `/products/*` and `/stores/*`.

The native projects need the same platform setup as native apps — follow the manifest/entitlements sections below, then wire the JavaScript side with React Navigation or the `Linking` API.

## Android setup

Add both intent filters to `MainActivity` in `android/app/src/main/AndroidManifest.xml`:

```xml
<activity android:name=".MainActivity" android:launchMode="singleTask" android:exported="true">
  <!-- Verified App Links -->
  <intent-filter android:autoVerify="true">
    <action android:name="android.intent.action.VIEW" />
    <category android:name="android.intent.category.DEFAULT" />
    <category android:name="android.intent.category.BROWSABLE" />
    <data android:scheme="https" android:host="shop.example.com" />
    <data android:pathPrefix="/products/" />
    <data android:pathPrefix="/stores/" />
  </intent-filter>

  <!-- Custom scheme fallback used by the landing page -->
  <intent-filter>
    <action android:name="android.intent.action.VIEW" />
    <category android:name="android.intent.category.DEFAULT" />
    <category android:name="android.intent.category.BROWSABLE" />
    <data android:scheme="acme" />
  </intent-filter>
</activity>
```

`launchMode="singleTask"` is the React Native default — keep it, otherwise a tapped link starts a second activity instead of reaching the running app.

Register the SHA-256 fingerprints of every signing key on the backend (Play App Signing key, upload key, debug key). See the warning in the [Android guide](android.md#prerequisites).

## iOS setup

1. Xcode → target → Signing & Capabilities → **+ Capability** → Associated Domains → add `applinks:shop.example.com`.
2. Register the custom scheme in `Info.plist`:

```xml
<key>CFBundleURLTypes</key>
<array>
  <dict>
    <key>CFBundleURLSchemes</key>
    <array><string>acme</string></array>
  </dict>
</array>
```

3. Forward links to React Native in `AppDelegate` (bare workflow):

```objc
#import <React/RCTLinkingManager.h>

// Custom scheme: acme://products/42
- (BOOL)application:(UIApplication *)application openURL:(NSURL *)url
            options:(NSDictionary<UIApplicationOpenURLOptionsKey,id> *)options
{
  return [RCTLinkingManager application:application openURL:url options:options];
}

// Universal Links: https://shop.example.com/products/42
- (BOOL)application:(UIApplication *)application continueUserActivity:(NSUserActivity *)userActivity
 restorationHandler:(void (^)(NSArray<id<UIUserActivityRestoring>> *))restorationHandler
{
  return [RCTLinkingManager application:application
                   continueUserActivity:userActivity
                     restorationHandler:restorationHandler];
}
```

With Expo, set `scheme`, `ios.associatedDomains` and `android.intentFilters` in `app.json` instead — `npx uri-scheme` can generate the values.

## React Navigation

The cleanest wiring is React Navigation's `linking` config: it handles the cold-start URL, runtime URLs and parsing in one place.

```tsx
// linking.ts
import type { LinkingOptions } from '@react-navigation/native';
import type { RootStackParamList } from './types';

export const linking: LinkingOptions<RootStackParamList> = {
  prefixes: [
    'https://shop.example.com',
    'https://www.shop.example.com',
    'acme://',
  ],
  config: {
    screens: {
      ProductDetails: 'products/:id',
      StoreDetails: 'stores/:id',
    },
  },
};
```

```tsx
// App.tsx
import { NavigationContainer } from '@react-navigation/native';
import { linking } from './linking';

export default function App() {
  return (
    <NavigationContainer linking={linking} fallback={<Splash />}>
      <RootNavigator />
    </NavigationContainer>
  );
}
```

`:id` arrives as a string in `route.params` — cast it before calling the API.

## Without React Navigation

Use the `Linking` API directly. Handle both the cold-start URL and links received while running:

```tsx
import { useEffect } from 'react';
import { Linking } from 'react-native';

type DeepLink =
  | { type: 'product'; id: number }
  | { type: 'store'; id: number };

const HOSTS = ['shop.example.com', 'www.shop.example.com'];

export function parseDeepLink(url: string): DeepLink | null {
  const parsed = new URL(url);

  // acme://products/42 puts "products" in the host;
  // https://shop.example.com/products/42 puts it in the path.
  const segments =
    parsed.protocol === 'acme:'
      ? [parsed.hostname, ...parsed.pathname.split('/').filter(Boolean)]
      : HOSTS.includes(parsed.hostname)
        ? parsed.pathname.split('/').filter(Boolean)
        : null;

  if (!segments) return null;

  const id = Number(segments[1]);
  if (!Number.isInteger(id)) return null;

  if (segments[0] === 'products') return { type: 'product', id };
  if (segments[0] === 'stores') return { type: 'store', id };

  return null;
}

export function useDeepLinks(navigate: (link: DeepLink) => void) {
  useEffect(() => {
    const handle = (url: string | null) => {
      if (!url) return;
      const link = parseDeepLink(url);
      if (link) navigate(link);
    };

    // Cold start
    Linking.getInitialURL().then(handle);

    // While running
    const subscription = Linking.addEventListener('url', ({ url }) => handle(url));

    return () => subscription.remove();
  }, [navigate]);
}
```

A cold-start link can fire before auth restore or the navigator is ready — buffer the parsed link and navigate once the app is ready, exactly like push-notification navigation.

## Sharing

Share the `share_url` value from the API. The server renders the link preview, so the text can stay short:

```tsx
import { Share } from 'react-native';

export function shareProduct(product: { name: string; shareUrl: string }) {
  return Share.share({ message: `${product.name}\n${product.shareUrl}` });
}
```

## Testing

```bash
# Works for both platforms
npx uri-scheme open "https://shop.example.com/products/42" --android
npx uri-scheme open "acme://stores/7" --ios

# Or directly
adb shell am start -a android.intent.action.VIEW -d "https://shop.example.com/products/42"
xcrun simctl openurl booted "acme://products/42"
```

## Checklist

- Cold start: with the app closed, a product link opens the app on that product.
- Warm start: with the app in the background, a store link navigates without restarting.
- Android: `adb shell pm get-app-links <package>` reports the domain as `verified`.
- iOS: pasting a link into Notes and tapping it opens the app, not Safari.
- Not installed: the link opens the landing page, which redirects to the right store.
- Deleted/unpublished entity: the app shows a "no longer available" message on `404`.
- Tested with release builds (TestFlight / Google Play), because the signing keys differ from debug.
