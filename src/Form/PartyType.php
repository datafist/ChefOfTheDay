<?php

namespace App\Form;

use App\Entity\Party;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PartyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('children', CollectionType::class, [
                'label' => 'Kinder (1-3)',
                'entry_type' => ChildType::class,
                'entry_options' => [
                    'label' => false,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'by_reference' => false,
                'constraints' => [
                    new Assert\Count([
                        'min' => 1,
                        'max' => 3,
                        'minMessage' => 'Mindestens ein Kind muss angegeben werden.',
                        'maxMessage' => 'Maximal drei Kinder können angegeben werden.'
                    ])
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-Mail-Adresse',
                'required' => false,
                'attr' => ['placeholder' => 'familie@example.com']
            ])
            ->add('parentNames', CollectionType::class, [
                'label' => 'Elternteile (1-2)',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                    'attr' => ['placeholder' => 'Name des Elternteils']
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'by_reference' => false,
                'constraints' => [
                    new Assert\Count([
                        'min' => 1,
                        'max' => 2,
                        'minMessage' => 'Mindestens ein Elternteil muss angegeben werden.',
                        'maxMessage' => 'Maximal zwei Elternteile können angegeben werden.'
                    ])
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Party::class,
        ]);
    }
}
