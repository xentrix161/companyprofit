<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use Nette\Forms\Container;
use Nette\Forms\Controls\SubmitButton;


final class CompanyProfitPresenter extends Nette\Application\UI\Presenter
{
    protected function createComponentCompanyForm($removeEvent): Form
    {
        $form = new Form();

        $form->addText('profit', 'Finančné zhodnotenie firmy:')
            ->addRule($form::FLOAT, 'Zadajte prosim platnú hodnotu (desatinné číslo (-/+)).')
            ->addRule($form::FILLED, 'Vyplnte prosím %label.');

        // https://github.com/Kdyby/FormsReplicator
        $owners = $form->addDynamic('owners', function (Container $owner) use ($removeEvent): void {
            // Fieldy, ktoré obsahuje každý owener
            $owner->addText('name', 'Meno')
                ->addRule(Nette\Forms\Form::FILLED);

            $owner->addText('factor', 'Činiteľ')
                ->addRule(Nette\Forms\Form::INTEGER, 'Zadajte kladné celé číslo.')
                ->addRule(Nette\Forms\Form::MIN, 'Hodnota musí byť aspoň 1.', 1)
                ->addRule(Nette\Forms\Form::FILLED);

            $owner->addText('denominator', 'Menovateľ')
                ->addRule(Nette\Forms\Form::INTEGER, 'Zadajte kladné celé číslo.')
                ->addRule(Nette\Forms\Form::MIN, 'Hodnota musí byť aspoň 1.', 1)
                ->addRule(Nette\Forms\Form::FILLED);

            // REMOVE tlačidlo pri každom ownerovi
            $owner->addSubmit('remove', '-')
                ->setValidationScope([]) # disables validation
                ->onClick[] = [$this, 'companyFormRemoveElementClicked'];

        }, 1);

        // ADD tlačidlo na konci
        $owners->addSubmit('add', '+')
            ->setValidationScope([])
            ->onClick[] = [$this, 'companyFormAddElementClicked'];

        $form->addSubmit('calculate', 'Vypočítať');
        $form->addSubmit('save', 'Uložiť');

        $form->onValidate[] = [$this, 'companyFormValidate'];
        $form->onSuccess[] = [$this, 'companyFormSucceeded'];

        return $form;
    }

    public function companyFormValidate($form)
    {
       // bdump($form->getUnsafeValues(true));

       // if ($form['calculate']->isSubmittedBy()) {
       //     dump($form->getValues());
       // }

    }

    public function companyFormSucceeded($form)
    {
        if ($form['calculate']->isSubmittedBy()) {
            // TODO: vypočítaj
        } else {
            // TODO: ulož
        }
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
