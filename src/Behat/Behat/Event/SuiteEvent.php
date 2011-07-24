<?php

namespace Behat\Behat\Event;

use Symfony\Component\EventDispatcher\Event;

use Behat\Behat\DataCollector\LoggerDataCollector;

/*
 * This file is part of the Behat.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Suite event.
 *
 * @author      Konstantin Kudryashov <ever.zet@gmail.com>
 */
class SuiteEvent extends Event implements EventInterface
{
    private $logger;
    private $completed;
    private $parameters = array();

    /**
     * Initializes suite event.
     *
     * @param   Behat\Behat\DataCollector\LoggerDataCollector   $logger     logger instance
     * @param   array                                           $parameters context parameters
     * @param   Boolean                                         $completed  is suite completed
     */
    public function __construct(LoggerDataCollector $logger, array $parameters, $completed)
    {
        $this->logger     = $logger;
        $this->parameters = $parameters;
        $this->completed  = (Boolean) $completed;
    }

    /**
     * Returns suite logger.
     *
     * @return  Behat\Behat\DataCollector\LoggerDataCollector
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Returns context parameters.
     *
     * @return  array
     */
    public function getContextParameters()
    {
        return $this->parameters;
    }

    /**
     * Checks whether test suite was completed entirely.
     *
     * @return  Boolean
     */
    public function isCompleted()
    {
        return $this->completed;
    }
}
