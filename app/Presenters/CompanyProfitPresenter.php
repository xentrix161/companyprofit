<?php

declare(strict_types=1);

namespace App\Presenters;

use App;
use Nette;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Forms\Container;
use Nette\Forms\Controls\SubmitButton;
use App\Model\Facades\BanknotesFacade;


final class CompanyProfitPresenter extends Presenter
{

    private BanknotesFacade $banknotesFacade;

    public function __construct(BanknotesFacade $bf)
    {
        $this->banknotesFacade = $bf;
    }

    protected function createComponentCompanyForm($removeEvent): Form
    {
        $form = new Form();

        $form->addText('profit', 'Finančné zhodnotenie firmy:')
            ->addRule($form::FLOAT, 'Zadajte prosim platnú hodnotu (desatinné číslo (-/+)).')
            ->addRule($form::FILLED, 'Vyplnte prosím %label.');

        // https://github.com/Kdyby/FormsReplicator
        $owners = $form->addDynamic('owners', function (Container $owner) use ($removeEvent): void {
            // Fieldy, ktoré obsahuje každý owner
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

        }, 2);

        // ADD tlačidlo
        $owners->addSubmit('add', '+')
            ->setValidationScope([])
            ->onClick[] = [$this, 'companyFormAddElementClicked'];

        $form->addSubmit('calculate', 'Vypočítať');
        $form->addSubmit('save', 'Uložiť');

        $form->onValidate[] = [$this, 'companyFormValidate'];
        $form->onSuccess[] = [$this, 'companyFormSucceeded'];

        return $form;
    }

    public function companyFormValidate(Form $form)
    {
        if ($form['calculate']->isSubmittedBy() || $form['save']->isSubmittedBy()) {
            $values = $form->getValues();

            $profit = $values->profit;
            $numberOfDecimals = $this->banknotesFacade->getNumberOfDecimals($profit);

            if ($numberOfDecimals > 2) {
                $form->addError('Finančné zhodnotenie firmy môže obsahovať maximálne 2 desatinné miesta');
            }

            $owners = $values->owners;

            if (is_countable($owners) && count($owners) < 1) {
                $form->addError('Pridajte aspoň jedného majiteľa');
            }

            $fractionSum = 0;
            foreach ($owners as $owner) {
                $factor = $owner->factor;
                $denominator = $owner->denominator;
                if ($factor > $denominator) {
                    $form->addError('Činiteľ nemôže byť v tomto prípade väčší ako menovateľ');
                }
                $fractionSum += $factor / $denominator;
            }

            if ($fractionSum != 1) {
                $form->addError('Súčet zlomkov musí byť 1');
            }
        }
    }

    public function companyFormSucceeded(Form $form)
    {
        if ($form['calculate']->isSubmittedBy() || $form['save']->isSubmittedBy()) {
            $values = $form->getValues();

            $profit = $values->profit;
            $owners = $values->owners;

            $ownersData = [];
            foreach ($owners as $key => $owner) {
                $factor = $owner->factor;
                $denominator = $owner->denominator;
                $ownersPart = $profit * ($factor / $denominator);
                $banknotes = $this->banknotesFacade->getBanknotesCounts($ownersPart);
                $numberOfDecimals = $this->banknotesFacade->getNumberOfDecimals($ownersPart);

                $left = 0;
                if ($numberOfDecimals > 2) {
                    $dotPos = strpos((string)$ownersPart, '.', 2);
                    $left = '0.00' . substr((string)$ownersPart, $dotPos + 3);
                }

                $ownersData[$owner->name] = [
                    'name'          => $owner->name,
                    'share'         => $factor . '/' . $denominator,
                    'owners_part'   => floor($ownersPart * 100) / 100,
                    'banknotes'     => $banknotes,
                    'left'          => (float)$left,
                ];
            }

            $this->template->ownersData = $ownersData;
        } else {
            // TODO: ulož vstupy
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
