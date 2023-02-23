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
        if (empty($text)) {
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
}
