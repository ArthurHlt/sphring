<?php
/**
 * Copyright (C) 2014 Arthur Halet
 *
 * This software is distributed under the terms and conditions of the 'MIT'
 * license which can be found in the file 'LICENSE' in this package distribution
 * or at 'http://opensource.org/licenses/MIT'.
 *
 * Author: Arthur Halet
 * Date: 15/10/2014
 */

namespace Arthurh\Sphring\EventDispatcher;

use Arthurh\Sphring\Sphring;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class EventSphring
 * @package Arthurh\Sphring\EventDispatcher
 */
class EventSphring extends Event
{

    /**
     * @var Sphring
     */
    private $sphring;

    /**
     * @param Sphring $sphring
     */
    public function __construct(Sphring $sphring)
    {
        $this->sphring = $sphring;
    }

    /**
     * @return Sphring
     */
    public function getSphring()
    {
        return $this->sphring;
    }

    /**
     * @param Sphring $sphring
     */
    public function setSphring($sphring)
    {
        $this->sphring = $sphring;
    }

}
