<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ChildType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Kindname',
                'attr' => ['placeholder' => 'Max Mustermann'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Der Kindname darf nicht leer sein.']),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Der Kindname muss mindestens {{ limit }} Zeichen lang sein.',
                        'maxMessage' => 'Der Kindname darf maximal {{ limit }} Zeichen lang sein.',
                    ]),
                ],
            ])
            ->add('birthYear', IntegerType::class, [
                'label' => 'Geburtsjahr',
                'attr' => ['placeholder' => '2019'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Das Geburtsjahr darf nicht leer sein.']),
                    new Assert\Range([
                        'min' => 2015,
                        'max' => date('Y'),
                        'notInRangeMessage' => 'Das Geburtsjahr muss zwischen {{ min }} und {{ max }} liegen.',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Das Formular ist nicht an eine Entity gebunden, sondern arbeitet mit Arrays
        ]);
    }
}
