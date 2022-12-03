<?php

declare(strict_types=1);

namespace App\Presenters;

use App;
use Mpdf\Mpdf;
use Nette;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Forms\Container;
use Nette\Forms\Controls\SubmitButton;
use App\Model\Facades\BanknotesFacade;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


final class CompanyProfitPresenter extends Presenter
{
    private Nette\Database\Explorer $database;

    private BanknotesFacade $banknotesFacade;

    public function __construct(BanknotesFacade $bf, Nette\Database\Explorer $database)
    {
        $this->banknotesFacade = $bf;
        $this->database = $database;
    }

    public function actionDefault()
    {

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

            $numberOfDecimals = $this->banknotesFacade->getNumberOfDecimals($values->profit);
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

            if (round($fractionSum, 10) != 1) {
                $form->addError('Súčet zlomkov musí byť 1');
            }
        }
    }

    public function companyFormSucceeded(Form $form)
    {
        if ($form['calculate']->isSubmittedBy()) {
            $values = $form->getValues();

            $profit = $values->profit;
            $owners = $values->owners;
            $totalBanknotes = [];
            $ownersData = [];
            $minusSignal = $profit <= 0;
            $totalRests = 0;

            foreach ($owners as $key => $owner) {
                $factor = $owner->factor;
                $denominator = $owner->denominator;
                $ownersPart = $profit * ($factor / $denominator);
                $banknotes = $this->banknotesFacade->getBanknotesCounts($ownersPart);
                $numberOfDecimals = $this->banknotesFacade->getNumberOfDecimals($ownersPart);

                $rest = 0;
                if ($numberOfDecimals > 2) {
                    $dotPos = strpos((string)$ownersPart, '.', 2);
                    $rest = '0.00' . substr((string)$ownersPart, $dotPos + 3);
                }
                $totalRests += (float)$rest;

                foreach ($banknotes as $value => $count) {
                    if (isset($totalBanknotes[$value])) {
                        $totalBanknotes[$value] += $count;
                    } else {
                        $totalBanknotes[$value] = $count;
                    }
                }

                $ownersData[$key] = [
                    'name'          => $owner->name,
                    'share'         => $factor . '/' . $denominator,
                    'owners_part'   => floor($ownersPart * 100) / 100,
                    'banknotes'     => $banknotes,
                    'rest'          => (float)$rest,
                ];
            }

            $session = $this->getSession();
            $dataSection = $session->getSection('data');
            $dataSection->set('ownersData', $ownersData);
            $dataSection->set('summaryData', $totalBanknotes);
            $dataSection->set('profit', $profit);

            if (!$minusSignal) {
                $backCalc = $this->banknotesFacade->getBackCalc($totalBanknotes);
                $this->template->backCalc = $backCalc;
                $this->template->backCalcWithRests = $backCalc + $totalRests;
                $this->template->totalBanknotes = $totalBanknotes;
            }
            $this->template->profit = $profit;
            $this->template->ownersData = $ownersData;
            $this->template->minusSignal = $minusSignal;
            $this->template->totalRests = round($totalRests, 4);

            $this->flashMessage('Vstupy boli spracované.');

        } elseif ($form['save']->isSubmittedBy()) {
            $values = $form->getValues();

            $profit = $values->profit;
            $owners = $values->owners;

            $dbCompany = $this->database->table('companies')->insert([
                'profit'         => $profit,
                'created'       => new Nette\Utils\DateTime(),
            ]);

            foreach ($owners as $owner) {
                $dbOwner = $this->database->table('owners')->insert([
                    'name'          => $owner->name,
                    'factor'        => $owner->factor,
                    'denominator'   => $owner->denominator,
                    'created'       => new Nette\Utils\DateTime(),
                ]);

                $this->database->table('owners_in_companies')->insert([
                    'owner_id'      => $dbOwner->id,
                    'company_id'    => $dbCompany->id,
                    'created'       => new Nette\Utils\DateTime(),
                ]);
            }

            $this->flashMessage('Vstupy boli úspešne uložené.');
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

    public function actionExportOwnersPdf()
    {
        $session = $this->getSession();
        $dataSection = $session->getSection('data');
        $ownersData = $dataSection->get('ownersData');

        $pdf = new Mpdf();
        $pdf->WriteHTML('<h1>Hello world!</h1>');
        $pdf->Output();
    }

    public function actionExportSummaryPdf()
    {
        $session = $this->getSession();
        $dataSection = $session->getSection('data');
        $summaryData = $dataSection->get('summaryData');
        $profit = $dataSection->get('profit');

        $pdf = new Mpdf();

        if ($profit < 0) {
            $output = '<h1>' . 'Strata: ' . $profit . '€' . '</h1>';
        } else {
            $output = '<h1>' . 'Zisk: ' . $profit . '€' . '</h1>';
        }

        foreach ($summaryData as $value => $count) {
            $output .= '<div>' . '<b>' . $value . ' €: ' . '</b>' . $count . 'x' . '</div>';
        }

        $pdf->WriteHTML($output);
        $pdf->Output();
    }

    public function actionExportOwnersXls()
    {
        $session = $this->getSession();
        $dataSection = $session->getSection('data');
        $ownersData = $dataSection->get('ownersData');

        $header = ['Meno', 'Podiel', 'Zisk v €', '500€', '200€', '100€', '50€', '20€', '10€', '5€', '2€', '1€', '0.5€', '0.2€', '0.1€', '0.05€', '0.02€', '0.01€'];
        $exportData[] = $header;

        foreach ($ownersData as $data) {
            $formattedData = [];

            $formattedData[] = $data['name'];
            $formattedData[] = $data['share'];
            $formattedData[] = $data['owners_part'] . '€';
            $banknotes = $data['banknotes'];

            foreach ($banknotes as $count) {
                $formattedData[] = $count;
            }
            $exportData[] = $formattedData;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->fromArray($exportData, NULL, 'A1', TRUE);

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'. urlencode('exportOwnersXLS.xlsx').'"');
        $writer->save('php://output');
    }

    public function actionExportSummaryXls()
    {
        $session = $this->getSession();
        $dataSection = $session->getSection('data');
        $summaryData = array_values($dataSection->get('summaryData'));
        $profit = $dataSection->get('profit');
        array_unshift($summaryData, $profit);

        $header = ['Zisk v €', '500€', '200€', '100€', '50€', '20€', '10€', '5€', '2€', '1€', '0.5€', '0.2€', '0.1€', '0.05€', '0.02€', '0.01€'];
        $exportData[] = $header;
        $exportData[] = $summaryData;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->fromArray($exportData, NULL, 'A1', TRUE);

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'. urlencode('exportSummaryXLS.xlsx').'"');
        $writer->save('php://output');
    }
}
