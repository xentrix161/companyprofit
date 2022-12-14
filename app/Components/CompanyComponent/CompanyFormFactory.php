<?php

namespace App\Components\CompanyComponent;

use Nette\Application\UI\Form;
use Nette\Forms\Container;
use Nette\Forms\Controls\SubmitButton;

class CompanyFormFactory
{
    public function create(): Form
    {
        $form = new Form();

        $form->addText('profit', 'Finančné zhodnotenie firmy:')
            ->addRule($form::FLOAT, 'Zadajte prosim platnú hodnotu (desatinné číslo (-/+)).')
            ->addRule($form::FILLED, 'Vyplnte prosím %label.');

        $removeCallback = [$this, 'companyFormRemoveElementClicked'];

        // https://github.com/Kdyby/FormsReplicator
        $owners = $form->addDynamic('owners', function (Container $owner) use ($removeCallback): void {
            $owner->addText('name', 'Meno')
                ->addRule(Form::FILLED);

            $owner->addText('factor', 'Činiteľ')
                ->addRule(Form::INTEGER, 'Zadajte kladné celé číslo.')
                ->addRule(Form::MIN, 'Hodnota musí byť aspoň 1.', 1)
                ->addRule(Form::FILLED);

            $owner->addText('denominator', 'Menovateľ')
                ->addRule(Form::INTEGER, 'Zadajte kladné celé číslo.')
                ->addRule(Form::MIN, 'Hodnota musí byť aspoň 1.', 1)
                ->addRule(Form::FILLED);

            $owner->addSubmit('remove', '-')
                ->setValidationScope([])
                ->onClick[] = $removeCallback;

        });

        $owners->addSubmit('add', '+')
            ->setValidationScope([])
            ->onClick[] = [$this, 'companyFormAddElementClicked'];

        $form->addSubmit('reset', 'Reset')
            ->setValidationScope([]);
        $form->addSubmit('calculate', 'Vypočítať');
        $form->addSubmit('save', 'Uložiť');

        return $form;
    }

    public function companyFormAddElementClicked(SubmitButton $button): void
    {
        $button->parent->createOne();
    }

    public function companyFormRemoveElementClicked(SubmitButton $button): void
    {
        // first parent is container and second parent is its replicator
        $users = $button->parent->parent;
        $users->remove($button->parent, true);
    }
}