<?php

namespace App\Components\StoredCompaniesComponent;

use Nette\Application\UI\Form;
use Nette\Database\Explorer;

class StoredCompaniesFormFactory
{
    private Explorer $database;

    public function __construct(Explorer $database)
    {
        $this->database = $database;
    }

    public function create(): Form
    {
        $form = new Form();

        $companies = $this->database->table('companies')->fetchPairs('id', 'profit');
        $form->addSelect('company_id', 'Vyberte si firmu', $companies)
            ->setRequired('Toto pole je povinné');
        $form->addSubmit('choose', 'Načítať');

        return $form;
    }
}