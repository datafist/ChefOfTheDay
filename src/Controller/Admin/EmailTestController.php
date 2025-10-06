<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class EmailTestController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $mailerDsn,
        #[Autowire('%mailer.from_email%')]
        private readonly string $fromEmail,
        #[Autowire('%mailer.from_name%')]
        private readonly string $fromName,
    ) {
    }

    #[Route('/admin/email-test', name: 'admin_email_test')]
    public function test(Request $request): Response
    {
        $testEmail = $request->request->get('test_email');

        if ($request->isMethod('POST') && $testEmail) {
            // Validiere E-Mail-Adresse
            if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('danger', 'Ungültige E-Mail-Adresse: ' . htmlspecialchars($testEmail));
            } else {
                try {
                    // Erstelle einen synchronen Mailer (umgeht Messenger Queue)
                    $transport = Transport::fromDsn($this->mailerDsn);
                    $mailer = new Mailer($transport);
                    
                    // Verwende die zentral konfigurierte Absender-Adresse
                    $email = (new Email())
                        ->from($this->fromEmail)
                        ->to($testEmail)
                        ->subject('🧪 Test-E-Mail von Kita Kochdienst-App')
                        ->html($this->getTestEmailHtml());

                    // Synchroner Versand - wirft Exception bei Fehler
                    $mailer->send($email);
                    
                    $this->addFlash('success', '✅ Test-E-Mail erfolgreich an ' . htmlspecialchars($testEmail) . ' versendet! Bitte prüfen Sie Ihr Postfach (auch Spam-Ordner).');
                } catch (TransportExceptionInterface $e) {
                    $this->addFlash('danger', '❌ E-Mail-Versand fehlgeschlagen: ' . $e->getMessage());
                } catch (\Exception $e) {
                    $this->addFlash('danger', '❌ Fehler: ' . $e->getMessage());
                }
            }
            
            // Redirect to prevent form resubmission
            return $this->redirectToRoute('admin_email_test');
        }

        return $this->render('admin/email_test/index.html.twig', [
            'test_email' => $testEmail,
        ]);
    }

    private function getTestEmailHtml(): string
    {
        return '
            <html>
                <body style="font-family: Arial, sans-serif; padding: 20px; color: #333;">
                    <h2 style="color: #2c3e50;">🧪 E-Mail-Test erfolgreich!</h2>
                    <p>Diese Test-E-Mail wurde von der <strong>Kita Kochdienst-Verwaltungs-App</strong> versendet.</p>
                    
                    <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0;">
                        <strong>✅ Ihre E-Mail-Konfiguration funktioniert!</strong>
                        <p style="margin: 10px 0 0 0;">
                            Der SMTP-Server ist korrekt konfiguriert und E-Mails können erfolgreich versendet werden.
                        </p>
                    </div>
                    
                    <h3 style="color: #2c3e50; margin-top: 30px;">Was wird per E-Mail versendet?</h3>
                    <ul style="line-height: 1.8;">
                        <li><strong>Kochplan-Benachrichtigungen:</strong> Nach dem Generieren eines neuen Kochplans</li>
                        <li><strong>Erinnerungen:</strong> X Tage vor dem Kochdienst (via Cronjob)</li>
                    </ul>
                    
                    <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
                    
                    <p style="color: #666; font-size: 12px;">
                        Kita Kochdienst-Verwaltung<br>
                        Datum: ' . date('d.m.Y H:i') . ' Uhr
                    </p>
                </body>
            </html>
        ';
    }
}
