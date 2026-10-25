# Hemingway Team App

React/Vite + Capacitor client for the Hemingway employee API.

## Local setup

```bash
cd mobile
npm install
cp .env.example .env
npm run dev
```

## Native iOS

Requires macOS with Xcode.

```bash
npm run build
npx cap add ios
npm run cap:sync
npm run ios
```

## Native Android

Requires Android Studio.

```bash
npm run build
npx cap add android
npm run cap:sync
npm run android
```

The native `ios/` and `android/` projects are generated locally by Capacitor. The API defaults to `https://dienstplan.hemingway-hilden.de/api/app/v1` and can be overridden through `VITE_API_URL`.
