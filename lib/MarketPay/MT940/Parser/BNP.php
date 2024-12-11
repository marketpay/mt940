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
class BNP extends AbstractParser
{

    private $data;
    /**
     * Check whether provided MT940 statement string can be parsed by this parser
     */
    public function accept(string $text): bool
    {
        if (empty($text))
        {
            return false;
        }
        return strpos($text, 'F01BNPAESMSAXXX') !== false;
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




        $description = $lines[1] ?? null;

        // GET VIRTUAL IBAN
        $viban = null;
        $description =str_replace("\r\n", "", $description);
        if(substr($description, -1) == '/') {
            $description = substr($description, 0, -1);
        }
        $parts = explode('//', $description);
        $fields = [
            'type' => 'TYPE/', // description of the accounting entry code and label
            'code' => 'CODE/', // local code for the country
            'description' => 'REMI/', // remittance information of the instruction
            'vacc' => 'VACC/', // virtual account reference
            'reference' => 'EREF/', // reference of the original payment
            'ordp' => 'ORDP/NAME/', // ordering party name
            'info' => 'INFO/', // account number of the originatort
            'obk' => 'OBK/', // ordering bank
        ];
        foreach ($parts as $part) {
            foreach($fields as $f => $fname)
                if (strpos($part, $fname) === 0) {
                    $this->data[$f] = substr($part, strlen($fname));
                }
        }

        $transaction = $this->reader->createTransaction();
        $transaction
            ->setAmount($amount)
            ->setContraAccount($this->contraAccount($lines))
            ->setValueDate($valueDate)
            ->setBookDate($bookDate)
            ->setCode($this->data['code']??'')
            ->setRef($this->data['reference']??'')
            ->setBankRef($this->data['obk']??'')
            ->setSupplementaryDetails($lines[0])
            ->setTxText($this->txText($lines))
            ->setPrimanota($this->primanota($lines))
            ->setExtCode($this->extCode($lines))
            ->setEref($this->data['reference']??'')
            ->setBIC($this->data['obk']??'')
            ->setIBAN($this->data['vacc']??'')
            ->setAccountHolder($this->accountHolder($lines))
            ->setDescription($description);





        return $transaction;
    }

}
