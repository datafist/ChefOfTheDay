<?php

namespace App\Service;

use App\Entity\KitaYear;
use App\Repository\CookingAssignmentRepository;
use App\Util\DateHelper;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PdfExportService
{
    public function __construct(
        private readonly CookingAssignmentRepository $assignmentRepository,
        private readonly Environment $twig,
    ) {
    }

    public function generateCookingPlanPdf(KitaYear $kitaYear): string
    {
        $assignments = $this->assignmentRepository->findBy(
            ['kitaYear' => $kitaYear],
            ['assignedDate' => 'ASC']
        );

        // Gruppiere Zuweisungen nach Monat
        $assignmentsByMonth = [];
        foreach ($assignments as $assignment) {
            $month = $assignment->getAssignedDate()->format('Y-m');
            $monthName = DateHelper::getMonthNameGerman((int)$assignment->getAssignedDate()->format('n')) 
                       . ' ' . $assignment->getAssignedDate()->format('Y');
            
            if (!isset($assignmentsByMonth[$month])) {
                $assignmentsByMonth[$month] = [
                    'name' => $monthName,
                    'assignments' => [],
                ];
            }
            
            $assignmentsByMonth[$month]['assignments'][] = $assignment;
        }

        // Rendere HTML-Template
        $html = $this->twig->render('pdf/cooking_plan.html.twig', [
            'kitaYear' => $kitaYear,
            'assignmentsByMonth' => $assignmentsByMonth,
            'totalAssignments' => count($assignments),
        ]);

        // Konfiguriere DOMPDF
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function getDayNameGerman(string $englishDay): string
    {
        $days = [
            'Monday' => 'Montag',
            'Tuesday' => 'Dienstag',
            'Wednesday' => 'Mittwoch',
            'Thursday' => 'Donnerstag',
            'Friday' => 'Freitag',
            'Saturday' => 'Samstag',
            'Sunday' => 'Sonntag',
        ];
        return $days[$englishDay] ?? $englishDay;
    }
}
