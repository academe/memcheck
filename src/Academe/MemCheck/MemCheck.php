<?php

/**
 * This library is used to keep an eye on memory usage when operating a big process over a loop.
 * It will keep track of the average loop memory usage.
 * A warning can be flagged when memory usage reaches:
 * - a percentage of the total;
 * - within an absolute distance of running out of memory;
 * - running out of memory within a given number of loops.
 */

namespace Academe\MemCheck;

class MemCheck {
    // All memory measured in bytes (would kbytes be better?)
    // The first memory measurement, at the start of the loop.
    // All memory checks are relative to this starting position.
    public $initialMemory = NULL;

    // The memory limit.
    public $memoryLimit = NULL;

    // Count of loop iterations.
    // We increment at the end of each loop.
    public $loopCount = 0;

    // The memory usage at the end of the last loop.
    public $currentMemory = 0;

    // Default flag for memory_get_usage()
    protected $realUsageFlag = false;

    // The average memory usage for each loop iteration.
    public $meanMemoryUsage = 0;

    // The limits we set for remaining resources, before we finish off the loop.
    // There are remaining iterations (e.g. 5) and remaining percentage of memory (e.g. 10)
    public $iterationLimit = NULL;
    public $percentLimit = NULL;

    public function __construct()
    {
        // Record the memory limit for this process.
        // This is the point we will run out of memory.
        $this->memoryLimit = $this->convertBytes(ini_get('memory_limit'));
    }

    // Convert a ini-style value with numeric order suffixes (K, M, G) to bytes.
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

    // Called at the start of the loop to initialise the counts.
    public function startLoop()
    {
        $this->initialMemory = memory_get_usage($this->realUsageFlag);
        $this->loopCount = 0;
    }

    // Called at the end of each itteration of the loop.
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

    // Return the estimated number of iterations left before we run out of memory.
    public function iterationsRemain()
    {
        if ($this->meanMemoryUsage == 0) $this->meanMemoryUsage = 1;

        $remaining = $this->memoryLimit - $this->currentMemory;

        return floor($remaining / $this->meanMemoryUsage);
    }

    // Calculate the percentage of memory that has been used by the loop iterations.
    public function percentRemain()
    {
        $available = $this->memoryLimit - $this->initialMemory;
        $used = $this->currentMemory  - $this->initialMemory;

        return round(100 - ($used / $available) * 100);
    }

    // Returns true if it is time to stop the loop and clean up,
    // while resources are still available to do so.
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
