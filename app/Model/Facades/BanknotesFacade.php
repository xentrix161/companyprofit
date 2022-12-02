<?php

namespace App\Model\Facades;

use Nette;

final class BanknotesFacade
{
    /**
     * Get the minimal possible banknotes from entered value
     *
     * @param $profit
     * @return array
     */
    public function getBanknotesCounts($profit): array
    {
        $noteValues = array('500', '200', '100', '50', '20', '10', '5', '2', '1', '0.5', '0.2', '0.1', '0.05', '0.02', '0.01');
        $banknotesCounter = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
        $noteValuesCount = count($banknotesCounter);
        $banknotes = [];

        for ($i = 0; $i < $noteValuesCount; $i++) {
            if ($profit >= (float)$noteValues[$i]) {
                $banknotesCounter[$i] = intval($profit / $noteValues[$i]);
                $profit = $profit - $banknotesCounter[$i] * $noteValues[$i];
            }
        }

        for ($i = 0; $i < $noteValuesCount; $i++)
        {
            $banknotes[$noteValues[$i]] = $banknotesCounter[$i];
        }

        return $banknotes;
    }

    /**
     * Get count of decimals from entered number
     *
     * @param $number
     * @return int
     */
    public function getNumberOfDecimals($number): int
    {
        return (int)strpos(strrev((string)$number), ".");
    }

    /**
     * Get checksum from paid out banknotes. Banknotes must be in assoc array (key = value of banknote,
     * value = banknote count)
     *
     * @param $totalBanknotes
     * @return float|int
     */
    public function getBackCalc($totalBanknotes): float|int
    {
        $checksum = 0;
        foreach ($totalBanknotes as $value => $count) {
            $checksum += (float)$value * $count;
        }
        return $checksum;
    }
}