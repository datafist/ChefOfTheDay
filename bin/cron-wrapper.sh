#!/bin/bash
# =============================================================================
# Cron-Wrapper für ChefOfTheDay Symfony Commands
# =============================================================================
#
# Dieses Script stellt sicher, dass Commands in der richtigen Umgebung
# ausgeführt werden und Fehler geloggt werden.
#
# Nutzung: bin/cron-wrapper.sh <command> [args...]
# Beispiel: bin/cron-wrapper.sh app:send-reminders 3
#
# =============================================================================

set -euo pipefail

# Projektverzeichnis (relativ zum Script)
PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
LOG_DIR="${PROJECT_DIR}/var/log"
LOG_FILE="${LOG_DIR}/cron.log"
LOCK_DIR="${PROJECT_DIR}/var/lock"

# Sicherstellen, dass Log- und Lock-Verzeichnisse existieren
mkdir -p "${LOG_DIR}" "${LOCK_DIR}"

# Symfony-Umgebung (Standard: prod, überschreibbar via APP_ENV)
SYMFONY_ENV="${APP_ENV:-prod}"

# Prüfe ob mindestens ein Argument übergeben wurde
if [ $# -lt 1 ]; then
    echo "Nutzung: $0 <command> [args...]" >&2
    exit 1
fi

COMMAND="$1"
shift
LOCK_FILE="${LOCK_DIR}/${COMMAND//[:\/]/_}.lock"

# Timestamp-Funktion
timestamp() {
    date '+%Y-%m-%d %H:%M:%S'
}

# Lock-Mechanismus: Verhindert parallele Ausführung des gleichen Commands
if [ -f "${LOCK_FILE}" ]; then
    LOCK_PID=$(cat "${LOCK_FILE}" 2>/dev/null || echo "")
    if [ -n "${LOCK_PID}" ] && kill -0 "${LOCK_PID}" 2>/dev/null; then
        echo "[$(timestamp)] SKIP: ${COMMAND} läuft bereits (PID: ${LOCK_PID})" >> "${LOG_FILE}"
        exit 0
    fi
    # Verwaistes Lock-File entfernen
    rm -f "${LOCK_FILE}"
fi

# Lock setzen
echo $$ > "${LOCK_FILE}"
trap 'rm -f "${LOCK_FILE}"' EXIT

# Command ausführen
echo "[$(timestamp)] START: ${COMMAND} $*" >> "${LOG_FILE}"

cd "${PROJECT_DIR}"

if php bin/console "${COMMAND}" "$@" --env="${SYMFONY_ENV}" --no-interaction >> "${LOG_FILE}" 2>&1; then
    echo "[$(timestamp)] OK: ${COMMAND} erfolgreich beendet" >> "${LOG_FILE}"
else
    EXIT_CODE=$?
    echo "[$(timestamp)] FEHLER: ${COMMAND} fehlgeschlagen (Exit-Code: ${EXIT_CODE})" >> "${LOG_FILE}"
    exit ${EXIT_CODE}
fi
