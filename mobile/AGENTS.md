This is an Expo/React Native mobile application. Prioritize mobile-first patterns, performance, and cross-platform compatibility.

## Expo has changed — do not trust your training data

Expo ships breaking changes every SDK release. APIs you remember are likely renamed, moved, or removed. Before writing any code that touches an Expo, EAS, or React Native API:

1. Read the major version of the `expo` package in `package.json`.
2. Fetch the matching versioned docs: `https://docs.expo.dev/versions/v<major>.0.0/`
3. For anything else, fetch https://docs.expo.dev/llms.txt — an index of all Expo docs with corrections to common LLM misconceptions. Follow its links to the specific page you need; never answer from memory.

## Commands

Use `bunx` instead of `npx` if the project uses bun (`bun.lock` present).

```bash
npx expo install <package>  # ALWAYS use instead of npm/yarn/pnpm/bun add — resolves SDK-compatible versions
npm run dev                 # start the dev server for phones (see "Dev environment" below)
npx expo lint               # lint
npx tsc --noEmit            # typecheck
npx expo-doctor             # diagnose dependency and config issues
npx expo install --fix      # fix incompatible package versions
```

Run lint and typecheck before declaring any task done.

## Dev environment

- Two dev machines: Windows 11 + WSL2 (Ubuntu 24.04) + Docker Desktop, and macOS + OrbStack. Field demos run from either one, on any network (customer Wi-Fi, phone hotspot).
- Backend (Laravel API, Postgres, Redis) runs in Docker from `../compose.yaml`; API on port 8010. Plate OCR service runs outside Docker on port 8000.
- Start Metro with `npm run dev` (`scripts/dev-start.sh`), not bare `npx expo start`: it reads `.env.local`, detects the machine's address (`DEV_HOST_IP=auto`), sets `REACT_NATIVE_PACKAGER_HOSTNAME` / `EXPO_PUBLIC_API_URL`, and on WSL runs `scripts/dev-network.ps1` (Windows firewall + port proxy) when needed.
- In `__DEV__` the API base URL always comes from `EXPO_PUBLIC_API_URL` (`src/auth/auth-provider.tsx`); release builds use the URL saved at sign-in.
- No ngrok or other tunnels; phones reach the dev machine over the local network.
- Day-to-day commands and demo checklist: `docs/run.md`. Setup and troubleshooting: `docs/dev-setup.md`. Backend commands: `../htdocs/docs/wsl-dev-commands.md`, `../htdocs/docs/mac-dev-commands.md`.

## Navigation & Routing

- Use **Expo Router** for all navigation. Routes live in `src/app/` — every file there is a screen, `_layout.tsx` files define navigators. Keep non-route code (components, hooks, utils) outside `src/app/`.
- Import `Link`, `router`, and `useLocalSearchParams` from `expo-router`.
- Docs: https://docs.expo.dev/router/introduction.md

## Building with EAS

Use EAS to build, sign, and submit the app in the cloud (`eas build`, `eas submit`) and to ship over-the-air updates (`eas update`) — no local Xcode or Android Studio required. Run EAS CLI as `bunx eas-cli <command>` in Bun projects, or `npx eas-cli@latest <command>` otherwise; substitute that for bare `eas` in docs examples.
Docs: https://docs.expo.dev/eas/index.md

## Rules

- If `ios/` and `android/` directories do not exist, they are generated (Continuous Native Generation). Never create or edit them by hand — configure native behavior in `app.json` and config plugins.
- Expo Go only includes its bundled native modules. After adding a library with native code, the app needs a development build: `npx expo run:ios|android` locally, or `eas build --profile development`.
- Prefer recommended Expo modules over third-party libraries, and check your available skills before adding dependencies. Docs: https://docs.expo.dev/versions/latest/index.md
