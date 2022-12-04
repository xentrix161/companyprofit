<?php

declare(strict_types=1);

namespace App\Presenters;

use App;
use App\Components\CompanyComponent\CompanyFormFactory;
use App\Components\StoredCompaniesComponent\StoredCompaniesFormFactory;
use Mpdf\Mpdf;
use Nette;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use App\Model\Facades\BanknotesFacade;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


final class CompanyProfitPresenter extends Presenter
{
    private Nette\Database\Explorer $database;

    private BanknotesFacade $banknotesFacade;

    private CompanyFormFactory $companyFormFactory;
    private StoredCompaniesFormFactory $storedCompaniesFormFactory;

    private array $owners = [];
    private float $profit = 0;

    public function __construct(
        BanknotesFacade $bf,
        Nette\Database\Explorer $database,
        CompanyFormFactory $cff,
        StoredCompaniesFormFactory $scff
    )
    {
        $this->banknotesFacade = $bf;
        $this->database = $database;
        $this->companyFormFactory = $cff;
        $this->storedCompaniesFormFactory = $scff;
    }

    protected function createComponentStoredCompaniesForm(): Form
    {
        $form = $this->storedCompaniesFormFactory->create();

        $form->onSuccess[] = [$this, 'storedCompaniesFormSucceeded'];

        return $form;
    }

    public function storedCompaniesFormSucceeded(Form $form)
    {
        $values = $form->getValues();

        $companyId = $values->company_id;
        $company = $this->database->table('companies')->get($companyId);

        $ownerIds = $this->database->table('owners_in_companies')
            ->select('owner_id')
            ->where('company_id', $companyId)
            ->fetchAssoc('owner_id');
        $owners = $this->database->table('owners')
            ->where('id', array_keys($ownerIds))
            ->fetchAssoc('id');

        $this->owners = $owners;
        $this->profit = $company->profit;
    }

    protected function createComponentCompanyForm(): Form
    {
        $form = $this->companyFormFactory->create();

        if (!empty($this->owners)) {
            $counter = 0;
            foreach ($this->owners as $owner) {
                $form['owners'][$counter++]->setDefaults($owner);
            }
        }

        if (isset($this->profit)) {
            $form['profit']->setDefaultValue($this->profit);
        }

        $form->onValidate[] = [$this, 'companyFormValidate'];
        $form->onSuccess[] = [$this, 'companyFormSucceeded'];

        return $form;
    }

    public function companyFormValidate(Form $form)
    {
        if ($form['reset']->isSubmittedBy()) {
            $form->reset();
            $this->redirect('this');
        }

        if (!$form['calculate']->isSubmittedBy() && !$form['save']->isSubmittedBy()) {
            return;
        }

        $values = $form->getValues();
        $this->validateCompanyForm($form, $values);
    }

    public function companyFormSucceeded(Form $form)
    {
        if ($form['calculate']->isSubmittedBy()) {
            $values = $form->getValues();
            $this->calculateCompanyForm($values);
            $this->flashMessage('Vstupy boli spracované.');

        } elseif ($form['save']->isSubmittedBy()) {
            $values = $form->getValues();
            $this->saveCompanyForm($values);
            $this->flashMessage('Vstupy boli úspešne uložené.');
        }
    }

    public function actionExportOwnersPdf()
    {
        $session = $this->getSession();
        $dataSection = $session->getSection('data');
        $ownersData = $dataSection->get('ownersData');

        $pdf = new Mpdf();

        $output = '';
        foreach ($ownersData as $data) {
            $temp = '<div>' . '<b>Meno</b>: ' . $data['name'] . ' <b>Podiel:</b> ' . $data['share'] .  ' <b>Zisk:</b> ' . $data['owners_part'] . '€ <br><br>';

            foreach ($data['banknotes'] as $value => $count) {
                $temp .= '<b>' . $value . '€: ' . '</b>' . $count . 'x<br>';
            }
            $temp .= '</div><br><br>';

            $output .= $temp;
        }

        $pdf->WriteHTML($output);
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
            $formattedData[] = $data['owners_part'];
            $banknotes = $data['banknotes'];

            foreach ($banknotes as $count) {
                $formattedData[] = $count;
            }
            $exportData[] = $formattedData;
        }

        $this->xlsExport($exportData);
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

       $this->xlsExport($exportData);
    }

    private function xlsExport(array $exportData)
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($exportData, NULL, 'A1', TRUE);

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'. urlencode('exportSummaryXLS.xlsx').'"');
        $writer->save('php://output');
    }

    /**
     * Processing of values after save submit
     *
     * @param $values
     * @return void
     */
    private function saveCompanyForm($values): void
    {
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
    }

    /**
     * Processing of values after calculate submit
     *
     * @param $values
     * @return void
     */
    private function calculateCompanyForm($values): void
    {
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
            $ownersBanknotes = $this->banknotesFacade->getBanknotesCounts($ownersPart);
            $numberOfDecimals = $this->banknotesFacade->getNumberOfDecimals($ownersPart);

            $rest = 0;
            if ($numberOfDecimals > 2) {
                $dotPos = strpos((string)$ownersPart, '.', 2);
                $rest = '0.00' . substr((string)$ownersPart, $dotPos + 3);
            }
            $totalRests += (float)$rest;

            foreach ($ownersBanknotes as $value => $count) {
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
                'banknotes'     => $ownersBanknotes,
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
    }


    /**
     * Validate company form
     *
     * @param Form $form
     * @param $values
     * @return void
     */
    private function validateCompanyForm(Form $form, $values): void
    {
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
