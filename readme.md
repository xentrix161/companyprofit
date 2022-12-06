# CompanyProfit Calculator
Aplikácia slúžiaca na výpočet prislúchajúceho zisku alebo straty na základe podielov majiteľov vo firme.

Požiadavky
------------
- Nette 3.1 
- PHP 8.1

## Ukážkový local.neon
```yaml
    parameters:
      

    database:
      dsn: 'mysql:host=127.0.0.1;dbname=company'
      user: ...
      password: ...

    services:
      - App\Model\Facades\BanknotesFacade
      - App\Components\CompanyComponent\CompanyFormFactory
      - App\Components\StoredCompaniesComponent\StoredCompaniesFormFactory
```

## Rozbehanie projektu
* `git clone git@github.com:xentrix161/companyprofit.git`
* Vytvorenie local.neon súboru + pridanie db loginu namiesto ...
* Spustiť príkaz `composer install`
* Vytvorenie databázy v localhoste s názvom 'company'
* Spustenie migrágrácií `php ./bin/console.php migrations:reset`

