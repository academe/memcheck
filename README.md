memcheck
========

A package to keep track of memory remaining when running long
loops, so that the loop can be stopped cleanly and restarted
again in a new session.

Typical Usage
-------------

    require 'vendor/autoload.php';
    
    $MemCheck = new Academe\MemCheck\MemCheck();
    
    // Set warning when memory reaches 10% remaining, or there is enough
    // memory to run five or fewer more iterations of the loop.
    $MemCheck->iterationLimit = 5;
    $MemCheck->percentLimit = 10;
    
    // Initialise.
    $MemCheck->startLoop();
    
    while(true) {
        // do loop functionality
        // ...
        
        $MemCheck->endIteration();
        if (! $MemCheck->checkContinue()) {
            // Clean up, note where we got to, and exit loop.
            break;
        }
    }