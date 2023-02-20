<?php

declare(strict_types=1);

/*
 * This file is part of the MarketPay\MT940 library
 *
 * Copyright (c) 2012 Sander Marechal <s.marechal@jejik.com>
 * Licensed under the MIT license
 *
 * For the full copyright and license information, please see the LICENSE
 * file that was distributed with this source code.
 */

namespace MarketPay\Tests\MT940\Fixture;

use MarketPay\MT940\Parser\AbstractParser;

/**
 * Parser for the generic fixture document
 *
 * @author Sander Marechal <s.marechal@jejik.com>
 */
class Parser extends AbstractParser
{
    /**
     * Test if the document is our generic document
     */
    public function accept(string $text): bool
    {
        return substr($text, 0, 11) === ':20:GENERIC';
    }

    /**
     * Get an array of allowed BLZ for this bank
     */
    public function getAllowedBLZ(): array
    {
        return [];
    }
}
