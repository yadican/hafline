#!/usr/bin/env bash
# `npm run dev`: starts Metro for Expo Go on the current network, using the settings in .env.local.
# Runs on macOS and on Windows inside WSL. Extra arguments are passed to `expo start` (e.g. --clear).
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ ! -f .env.local ]]; then
  echo ".env.local not found. Create it first: cp .env.example .env.local" >&2
  exit 1
fi

# eval instead of `source <(...)`: macOS ships bash 3.2, which cannot source a process substitution.
set -a
eval "$(tr -d '\r' < .env.local)"
set +a

DEV_HOST_IP="${DEV_HOST_IP:-auto}"
METRO_PORT="${METRO_PORT:-8081}"
API_PORT="${API_PORT:-8010}"

if command -v powershell.exe >/dev/null; then
  # WSL runs Metro behind NAT; Windows has to forward the port and open the firewall for phones.
  network_script="$(wslpath -w scripts/dev-network.ps1)"
  network_args=(-HostIp "$DEV_HOST_IP" -MetroPort "$METRO_PORT" -ApiPort "$API_PORT")
  check_status=0
  check_output="$(powershell.exe -NoProfile -ExecutionPolicy Bypass -File "$network_script" "${network_args[@]}" -Check | tr -d '\r')" || check_status=$?
  if [[ $check_status -ne 0 && $check_status -ne 3 ]]; then
    echo "$check_output" >&2
    exit 1
  fi
  DEV_HOST_IP="$(tail -n 1 <<< "$check_output")"
  if [[ $check_status -eq 3 ]]; then
    echo "Windows network setup needed for ${DEV_HOST_IP}; approve the Windows admin prompt."
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File "$network_script" -HostIp "$DEV_HOST_IP" \
      -MetroPort "$METRO_PORT" -ApiPort "$API_PORT"
  fi
elif [[ "$DEV_HOST_IP" == auto ]]; then
  if [[ "$(uname)" == Darwin ]]; then
    DEV_HOST_IP="$(ipconfig getifaddr "$(route -n get default 2>/dev/null | awk '/interface:/ {print $2}')" || true)"
  else
    DEV_HOST_IP="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for (i = 1; i < NF; i++) if ($i == "src") print $(i + 1)}' || true)"
  fi
  if [[ -z "$DEV_HOST_IP" ]]; then
    echo "Could not detect this computer's network address. Set DEV_HOST_IP in .env.local." >&2
    exit 1
  fi
fi

export DEV_HOST_IP
export EXPO_PUBLIC_API_URL="${API_URL:-http://${DEV_HOST_IP}:${API_PORT}}"
export REACT_NATIVE_PACKAGER_HOSTNAME="$DEV_HOST_IP"
if [[ -z "${EXPO_TOKEN:-}" && -f ~/.expo-token ]]; then
  EXPO_TOKEN="$(tr -d '[:space:]' < ~/.expo-token)"
  export EXPO_TOKEN
fi

api_status="$(curl -s -o /dev/null -m 5 -w '%{http_code}' "http://localhost:${API_PORT}/up" || true)"
if [[ -z "${API_URL:-}" && "$api_status" != 200 ]]; then
  echo "WARNING: API is not answering on localhost:${API_PORT}/up (HTTP ${api_status:-none}). Start Docker: docker compose up -d" >&2
fi

echo
echo "  Expo Go      : exp://${DEV_HOST_IP}:${METRO_PORT}"
echo "  Server (app) : ${EXPO_PUBLIC_API_URL}"
echo "  Phone check  : ${EXPO_PUBLIC_API_URL}/up"
echo
# ${@+"$@"}: with `set -u`, bash 3.2 treats an empty "$@" as unbound.
exec npx expo start --go --lan --port "$METRO_PORT" ${@+"$@"}
