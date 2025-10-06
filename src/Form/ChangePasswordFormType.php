<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Aktuelles Passwort',
                'mapped' => false,
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Bitte geben Sie Ihr aktuelles Passwort ein',
                    ]),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Neues Passwort',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Passwort wiederholen',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'Die Passwörter müssen übereinstimmen.',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Bitte geben Sie ein Passwort ein',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Ihr Passwort sollte mindestens {{ limit }} Zeichen lang sein',
                        'max' => 4096,
                    ]),
                ],
            ])
        ;
    }
}
