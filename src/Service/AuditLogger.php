<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Audit-Logging Service für sicherheitsrelevante und geschäftskritische Aktionen.
 * 
 * Protokolliert in einem dedizierten Log-Channel 'audit':
 * - Plan-Generierung und -Löschung
 * - Manuelle Zuweisungsänderungen
 * - KitaYear-Aktivierung und -Löschung
 * - Familien hinzufügen/entfernen aus Plan
 * - Login-Versuche (Admin + Eltern)
 */
class AuditLogger
{
    public function __construct(
        private readonly LoggerInterface $auditLogger,
    ) {
    }

    /**
     * Loggt eine Admin-Aktion.
     */
    public function logAdminAction(string $action, string $adminUser, array $details = []): void
    {
        $this->auditLogger->info('[ADMIN] ' . $action, array_merge([
            'admin' => $adminUser,
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], $details));
    }

    /**
     * Loggt Plan-Generierung.
     */
    public function logPlanGenerated(string $adminUser, string $kitaYear, int $assignmentCount, int $manualKept = 0): void
    {
        $this->logAdminAction('Plan generiert', $adminUser, [
            'kita_year' => $kitaYear,
            'assignments' => $assignmentCount,
            'manual_kept' => $manualKept,
        ]);
    }

    /**
     * Loggt Plan-Löschung.
     */
    public function logPlanDeleted(string $adminUser, string $kitaYear, int $deletedCount): void
    {
        $this->logAdminAction('Plan gelöscht', $adminUser, [
            'kita_year' => $kitaYear,
            'deleted_assignments' => $deletedCount,
        ]);
    }

    /**
     * Loggt manuelle Zuweisungsänderung.
     */
    public function logAssignmentChanged(string $adminUser, string $date, string $fromFamily, string $toFamily): void
    {
        $this->logAdminAction('Zuweisung geändert', $adminUser, [
            'date' => $date,
            'from' => $fromFamily,
            'to' => $toFamily,
        ]);
    }

    /**
     * Loggt Zuweisung erstellt.
     */
    public function logAssignmentCreated(string $adminUser, string $date, string $family): void
    {
        $this->logAdminAction('Zuweisung erstellt', $adminUser, [
            'date' => $date,
            'family' => $family,
        ]);
    }

    /**
     * Loggt Zuweisung gelöscht.
     */
    public function logAssignmentDeleted(string $adminUser, string $date, string $family): void
    {
        $this->logAdminAction('Zuweisung gelöscht', $adminUser, [
            'date' => $date,
            'family' => $family,
        ]);
    }

    /**
     * Loggt Familie in Plan aufgenommen.
     */
    public function logFamilyAddedToPlan(string $adminUser, string $family, int $transferred): void
    {
        $this->logAdminAction('Familie in Plan aufgenommen', $adminUser, [
            'family' => $family,
            'transferred' => $transferred,
        ]);
    }

    /**
     * Loggt Familie aus Plan entfernt.
     */
    public function logFamilyRemovedFromPlan(string $adminUser, string $family, int $redistributed, int $removed): void
    {
        $this->logAdminAction('Familie aus Plan entfernt', $adminUser, [
            'family' => $family,
            'redistributed' => $redistributed,
            'removed' => $removed,
        ]);
    }

    /**
     * Loggt KitaYear-Aktivierung.
     */
    public function logKitaYearActivated(string $adminUser, string $kitaYear): void
    {
        $this->logAdminAction('KitaYear aktiviert', $adminUser, [
            'kita_year' => $kitaYear,
        ]);
    }

    /**
     * Loggt KitaYear-Löschung.
     */
    public function logKitaYearDeleted(string $adminUser, string $kitaYear): void
    {
        $this->logAdminAction('KitaYear gelöscht', $adminUser, [
            'kita_year' => $kitaYear,
        ]);
    }

    /**
     * Loggt Sicherheitsrelevantes Event.
     */
    public function logSecurityEvent(string $event, array $details = []): void
    {
        $this->auditLogger->warning('[SECURITY] ' . $event, array_merge([
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], $details));
    }
}
