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

        $form->addGroup('Firma');
        $form->addText('profit', 'Finančné zhodnotenie firmy')
            ->addRule($form::FILLED)
            ->addRule($form::FLOAT);

        // https://github.com/Kdyby/FormsReplicator
        $form->addGroup('Majitelia');
        $owners = $form->addDynamic('owners', function (Container $owner) use ($removeEvent): void {

            // Fieldy, ktoré obsahuje každý owener
            $owner->addText('name', 'Meno')
                ->setRequired();
            $owner->addText('factor', 'Činiteľ');
            $owner->addText('denominator', 'Menovateľ');

            // REMOVE tlačidlo pri každom ownerovi
            $owner->addSubmit('remove', 'Odstrániť')
                ->setValidationScope([]) # disables validation
                ->onClick[] = [$this, 'companyFormRemoveElementClicked'];

        }, 1);

        // ADD tlačidlo na konci
        $owners->addSubmit('add', 'Pridaj majiteľa')
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
