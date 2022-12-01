<?php

namespace App\Model\Facades;

use Nette;

final class BanknotesFacade
{
    public function getBanknotesCounts($profit): array
    {
        $notes = array('500', '200', '100', '50', '20', '10', '5', '2', '1', '0.5', '0.2', '0.1', '0.05', '0.02', '0.01');
        $noteCounter = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
        $countNotes = count($noteCounter);

        for ($i = 0; $i < $countNotes; $i++)
        {
            if ($profit >= (float)$notes[$i])
            {
                $noteCounter[$i] = intval($profit / $notes[$i]);
                $profit = $profit - $noteCounter[$i] * $notes[$i];
            }
        }

        $banknotes = [];
        for ($i = 0; $i < $countNotes; $i++)
        {
            $banknotes[$notes[$i]] = $noteCounter[$i];
        }

        return $banknotes;
    }

    public function getNumberOfDecimals($number)
    {
        return (int)strpos(strrev((string)$number), ".");
    }
}