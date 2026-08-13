<?php

namespace App\Form;

use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactType extends AbstractType
{
    private const BASE_CLASS = 'w-full bg-white/85 border border-slate-200/60 rounded-lg px-4 py-3.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:bg-white focus:border-sage-dark/30 transition border-l-[3px]';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['placeholder' => 'Jean Dupont', 'class' => self::BASE_CLASS . ' border-l-sage-dark'],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['placeholder' => '06 XX XX XX XX', 'class' => self::BASE_CLASS . ' border-l-sage'],
            ])
            ->add('typeBesoin', ChoiceType::class, [
                'label' => 'Type de besoin',
                'choices' => [
                    'Sélectionnez votre besoin' => '',
                    'Dépannage' => 'depannage',
                    'Développement web' => 'dev_web',
                    'Autre' => 'autre',
                ],
                'attr' => ['class' => self::BASE_CLASS . ' border-l-sage'],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => ['placeholder' => 'Décrivez votre besoin', 'rows' => 6, 'class' => self::BASE_CLASS . ' border-l-sage-light resize-none'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
        ]);
    }
}