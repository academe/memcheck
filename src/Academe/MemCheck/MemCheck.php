<?php

/**
 * This library is used to keep an eye on memory usage when operating a big process over a loop.
 * It will keep track of the average loop memory usage.
 */

namespace Academe\MemCheck;

class MemCheck {

    /**
     * The starting memory usage at the beginning of the loop.
     *
     * @var integer 
     */
    private $initialMemory = NULL;

    /**
     * The total memory limit read from the PHP ini setting.
     *
     * @var integer
     */
    private $memoryLimit = NULL;

    /**
     * Count of loop iterations.
     * We increment at the end of each loop.
     *
     * @var integer
     */
    private $loopCount = 0;

    /**
     * The memory usage at the end of the last loop iteration.
     *
     * @var integer bytes
     */
    public $currentMemory = 0;

    /**
     * Default flag for memory_get_usage()
     *
     * @var boolean
     */
    private $realUsageFlag = false;

    /**
     * The average memory usage for each loop iteration.
     *
     * @var integer bytes
     */
    public $meanMemoryUsage = 0;

    // The limits we set for remaining resources, before we finish off the loop.
    // There are remaining iterations (e.g. 5) and remaining percentage of memory (e.g. 10)
    /**
     * The number of iterations that available memory must be able to run.
     *
     * @var integer NULL to disable this check
     */
    public $iterationLimit = 5;

    /**
     * The percentage of available memory that must be available.
     *
     * @var integer percentage (0-100) NULL to disable this check
     */
    public $percentLimit = 10;

    public function __construct()
    {
        // Record the memory limit for this process.
        // This is the point we will run out of memory.
        $this->memoryLimit = $this->convertBytes(ini_get('memory_limit'));
    }

    /**
     * Convert a ini-style value with numeric order suffixes (K, M, G) to bytes.
     *
     * @param string $val PHP ini setting string.
     * @return integer the value as a numeric.
     * @todo Looks like it may fail if there is NO order suffix present.
     */
    protected function convertBytes($val) {
        $val = trim($val);
        $lastChar = strtolower($val[strlen($val)-1]);
        switch($lastChar) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    /**
     * Initialise the object at the start of a loop.
     *
     */
    public function startLoop()
    {
        $this->initialMemory = memory_get_usage($this->realUsageFlag);
        $this->loopCount = 0;
    }

    /**
     * Indicate the end of a loop iteration.
     *
     */
    public function endIteration()
    {
        // If startLoop has not been called, then call it now.
        if (empty($this->initialMemory)) $this->startLoop();

        // We have finished another loop, so count it.
        $this->loopCount += 1;

        // Snapshot of current memory usage.
        $this->currentMemory = memory_get_usage($this->realUsageFlag);

        // Calulate the average loop memory usage.
        $this->meanMemoryUsage = round(($this->currentMemory - $this->initialMemory) / $this->loopCount);
    }

    /**
     * Return the estimated number of iterations left before we run out of memory.
     *
     * @return integer the remaining loop iterations, estimated from average memory usage.
     */
    public function iterationsRemain()
    {
        if ($this->meanMemoryUsage == 0) $this->meanMemoryUsage = 1;

        $remaining = $this->memoryLimit - $this->currentMemory;

        return floor($remaining / $this->meanMemoryUsage);
    }

    /**
     * Calculate the percentage of memory that has been used by the loop iterations.
     *
     * @return integer the remaining memory as a percentage of initial available memory.
     */
    public function percentRemain()
    {
        $available = $this->memoryLimit - $this->initialMemory;
        $used = $this->currentMemory  - $this->initialMemory;

        return round(100 - ($used / $available) * 100);
    }

    /**
     * Returns true if it is time to stop the loop and clean up,
     * while resources are still available to do so.
     *
     * @return boolean true if enough resources remain for another iteration of the loop.
     */
    public function checkContinue()
    {
        if (isset($this->percentLimit) && $this->percentRemain() < $this->percentLimit)
        {
            return false;
        }

        if (isset($this->iterationLimit) && $this->iterationsRemain() < $this->iterationLimit)
        {
            return false;
        }

        return true;
    }
}
