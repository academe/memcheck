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
    private $startMemory = NULL;
    /**
     * The starting time usage at the beginning of the loop.
     *
     * @var integer
     */
    private $startTime = NULL;

    /**
     * The total memory limit read from the PHP ini setting.
     *
     * @var integer
     */
    private $memoryLimit = NULL;

    /**
     * The total time limit read from the PHP ini setting or set in the app.
     *
     * @var integer
     */
    private $timeLimit = NULL;

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
     * The total time spent at the end of the last loop iteration.
     *
     * @var integer seconds
     */
    public $currentTime = 0;

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

    /**
     * The average time for each loop iteration.
     *
     * @var integer seconds
     */
    public $meanTime = 0;

    // The limits we set for remaining resources, before we finish off the loop.
    // There are remaining iterations (e.g. 5) and remaining percentage of memory (e.g. 10)
    /**
     * The number of iterations that available memory must be able to run.
     *
     * @var integer NULL to disable this check
     */
    public $iterationLimit = 5;

    /**
     * The percentage of available memory that must be free.
     *
     * @var integer percentage (0-100) NULL to disable this check
     */
    public $percentMemoryLimit = 10;

    /**
     * The percentage of available time that must be left.
     *
     * @var integer percentage (0-100) NULL to disable this check
     */
    public $percentTimeLimit = 10;

    public function __construct()
    {
        // Record the memory limit for this process.
        // This is the point we will run out of memory.
        $this->memoryLimit = $this->convertBytes(ini_get('memory_limit'));

        // Capture the time limit of the process.
        // If zero, then there is no limit.
        $this->timeLimit = ini_get('max_execution_time');
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
        $this->startMemory = memory_get_usage($this->realUsageFlag);
        $this->startTime = time();
        $this->loopCount = 0;
    }

    /**
     * Signal the end of a loop iteration.
     *
     * @return boolean the result of checkContinue().
     */
    public function endIteration()
    {
        // If startLoop has not been called, then call it now.
        if (empty($this->startMemory)) $this->startLoop();

        // We have finished another loop, so count it.
        $this->loopCount += 1;

        // Snapshot of current memory usage.
        $this->currentMemory = memory_get_usage($this->realUsageFlag);

        // Snapshot of current time.
        $this->currentTime = time();

        // Calulate the average loop memory usage.
        $memoryUsed = $this->currentMemory - $this->startMemory;
        $this->meanMemoryUsage = round($memoryUsed / $this->loopCount);

        // Calulate the average loop time.
        $totalTime = $this->currentTime - $this->startTime;
        $this->meanTime = round($totalTime / $this->loopCount);

        return $this->checkContinue();
    }

    /**
     * Return the estimated number of iterations left before we run out of memory or time.
     *
     * @return integer the remaining loop iterations, estimated from average memory usage.
     * @todo Take into account the time limit too, and provide the lowest.
     */
    public function iterationsRemain()
    {
        // Protect from divide-by-zero
        if ($this->meanMemoryUsage == 0) $this->meanMemoryUsage = 1;

        $remainingMemory = $this->memoryLimit - $this->currentMemory;
        $memoryIterations = floor($remainingMemory / $this->meanMemoryUsage);

        if (!empty($this->timeLimit) && $this->meanTime > 0) {
            $timeRemain = $this->timeLimit - $this->startTime + $this->currentTime;
            $timeIterations = floor($timeRemain / $this->meanTime);

            return min($memoryIterations, $timeIterations);
        }

        return $memoryIterations;
    }

    /**
     * Calculate the percentage of memory that remains for further loop iterations.
     *
     * @return integer the remaining memory as a percentage of initial available memory.
     */
    public function percentMemoryRemain()
    {
        $available = $this->memoryLimit - $this->startMemory;
        $used = $this->currentMemory - $this->startMemory;

        return round(100 - ($used / $available) * 100);
    }

    /**
     * Calculate the percentage of time that remains for further loop iterations.
     * Returns -1 if time is unlimited.
     *
     * @return integer the remaining time as a percentage of the process time limit.
     */
    public function percentTimeRemain()
    {
        if (empty($this->timeLimit)) return -1;

        // CHECKME: the available time for the loop should exclude any
        // time spent before the loop was initialised. I'm not sure how we
        // would measure that without setting this class up right at the start
        // of the request and taking a time snapshot then.
        $available = $this->timeLimit;
        $used = $this->currentTime - $this->startTime;

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
        if (isset($this->percentTimeLimit)) {
            $timeRemain = $this->percentTimeRemain();

            if ($timeRemain > 0 && $timeRemain < $this->percentTimeLimit)
            {
                return false;
            }
        }

        if (isset($this->percentMemoryLimit) && $this->percentMemoryRemain() < $this->percentMemoryLimit)
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
