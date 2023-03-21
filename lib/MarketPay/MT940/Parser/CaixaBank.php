<?php

declare(strict_types=1);

/*
 * This file is part of the MarketPay\MT940 library
 *
 * Copyright (c) 2020 Powercloud GmbH <d.richter@powercloud.de>
 * Licensed under the MIT license
 *
 * For the full copyright and license information, please see the LICENSE
 * file that was distributed with this source code.
 */

namespace MarketPay\MT940\Parser;

use MarketPay\MT940\TransactionInterface;

/**
 * DeutscheBank provides a parser for Deutsche Bank
 * @package MarketPay\MT940\Parser
 */
class CaixaBank extends AbstractParser
{
    /**
     * Check whether provided MT940 statement string can be parsed by this parser
     */
    public function accept(string $text): bool
    {
        if (empty($text))
        {
            return false;
        }
        return strpos($text, 'I940CAIXESBBAXXX') !== false;
    }

    /**
     * Get an array of allowed BLZ for this bank
     */
    public function getAllowedBLZ(): array
    {
        return [];
    }

    protected function transaction(array $lines): TransactionInterface
    {
        if (!preg_match('/(\d{6})(\d{4})?((?:C|D|RD|RC)R?)([0-9,]{1,15})/', $lines[0], $match))
        {
            throw new \RuntimeException(sprintf('Could not parse transaction line "%s"', $lines[0]));
        }

        // Parse the amount
        $amount = (float)str_replace(',', '.', $match[4]);
        if (in_array($match[3], array('D', 'DR', 'RC', 'RCR')))
        {
            $amount *= -1;
        }

        // Parse dates
        $valueDate = \DateTime::createFromFormat('ymd', $match[1]);
        $valueDate->setTime(0, 0, 0);

        $bookDate = null;

        if ($match[2])
        {
            // current|same year as valueDate
            $bookDate_sameYear = \DateTime::createFromFormat('ymd', $valueDate->format('y') . $match[2]);
            $bookDate_sameYear->setTime(0, 0, 0);

            /* consider proper year -- $valueDate = '160104'(YYMMTT) & $bookDate = '1228'(MMTT) */
            // previous year bookDate
            $bookDate_previousYear = clone($bookDate_sameYear);
            $bookDate_previousYear->modify('-1 year');

            // next year bookDate
            $bookDate_nextYear = clone($bookDate_sameYear);
            $bookDate_nextYear->modify('+1 year');

            // bookDate collection
            $bookDateCollection = [];

            // previous year diff
            $bookDate_previousYear_diff = $valueDate->diff($bookDate_previousYear);
            $bookDateCollection[$bookDate_previousYear_diff->days] = $bookDate_previousYear;

            // current|same year as valueDate diff
            $bookDate_sameYear_diff = $valueDate->diff($bookDate_sameYear);
            $bookDateCollection[$bookDate_sameYear_diff->days] = $bookDate_sameYear;

            // next year diff
            $bookDate_nextYear_diff = $valueDate->diff($bookDate_nextYear);
            $bookDateCollection[$bookDate_nextYear_diff->days] = $bookDate_nextYear;

            // get the min from these diffs
            $bookDate = $bookDateCollection[min(array_keys($bookDateCollection))];
        }

        // GET VIRTUAL IBAN
        $viban = null;
        $tmp = explode("\r\n", $lines[0]);
        if (count($tmp) > 1)
        {
            if (preg_match('/([0-9]{14})/', $tmp[1], $m))
            {
                $account = $m[1];
                $office = '2100' . substr($account, 0, 4);
                $account = substr($account, 4);
                $control_digit = $this->bankControlDigit($office, $account);
                $viban = $this->bankAccountToIBAN($office . $control_digit . $account);
            }
        }


        $description = $lines[1] ?? null;
        $transaction = $this->reader->createTransaction();
        $transaction
            ->setAmount($amount)
            ->setContraAccount($this->contraAccount($lines))
            ->setValueDate($valueDate)
            ->setBookDate($bookDate)
            ->setCode($this->code($lines))
            ->setRef($this->ref($lines))
            ->setBankRef($this->bankRef($lines))
            ->setSupplementaryDetails($lines[0])
            ->setGVC($this->gvc($lines))
            ->setTxText($this->txText($lines))
            ->setPrimanota($this->primanota($lines))
            ->setExtCode($this->extCode($lines))
            ->setEref($this->eref($lines))
            ->setBIC($this->bic($lines))
            ->setIBAN($viban)
            ->setAccountHolder($this->accountHolder($lines))
            ->setKref($this->kref($lines))
            ->setMref($this->mref($lines))
            ->setCred($this->cred($lines))
            ->setSvwz($this->svwz($lines))
            ->setPurp($this->purp($lines))
            ->setDebt($this->debt($lines))
            ->setCoam($this->coam($lines))
            ->setOamt($this->oamt($lines))
            ->setAbwa($this->abwa($lines))
            ->setAbwe($this->abwe($lines))
            ->setDescription($this->description($description));

        return $transaction;
    }

    private function bankControlDigit($bank_office, $account)
    {
        $control_digit = "";
        $weights = array(6, 3, 7, 9, 10, 5, 8, 4, 2, 1);

        foreach (array($bank_office, $account) as $str)
        {
            $sum = 0;
            for ($i = 0, $len = mb_strlen($str); $i < $len; $i++)
            {
                $sum += $weights[$i] * mb_substr($str, $len - $i - 1, 1);
            }
            $digit = 11 - $sum % 11;
            if ($digit == 11)
            {
                $digit = 0;
            } elseif ($digit == 10)
            {
                $digit = 1;
            }
            $control_digit .= $digit;
        }

        return $control_digit;
    }

    private function bankAccountToIBAN($account, $countryCode = 'ES')
    {
        $weights = array('A' => '10',
            'B' => '11',
            'C' => '12',
            'D' => '13',
            'E' => '14',
            'F' => '15',
            'G' => '16',
            'H' => '17',
            'I' => '18',
            'J' => '19',
            'K' => '20',
            'L' => '21',
            'M' => '22',
            'N' => '23',
            'O' => '24',
            'P' => '25',
            'Q' => '26',
            'R' => '27',
            'S' => '28',
            'T' => '29',
            'U' => '30',
            'V' => '31',
            'W' => '32',
            'X' => '33',
            'Y' => '34',
            'Z' => '35');
        $dividend = $account . $weights[substr($countryCode, 0, 1)] . $weights[substr($countryCode, 1, 1)] . '00';
        $controlDigit = (string)(98 - bcmod($dividend, '97'));
        if (strlen($controlDigit) == 1) $controlDigit = '0' . $controlDigit;
        return $countryCode . $controlDigit . $account;
    }
}
