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

$MemCheck->checkContinue() will return true if there enough resources left,
and will return false if there is enough memory for iterationLimit further
loop iterations or there is at most percentLimit percentage of available
memory left.

The idea is that you can clean up nicely when memory is running low
rather than get aborted unexpectedly in the middle of a processing loop
iteration.

Exiting the loop cleanly means that a new process can be started to continue
where this loop left off (i.e. where it ran out of memory). This can be done
by throwing another job onto a queue for processing further, or by returning
a message to the web browser to indicte another call is needed to run the next
batch of records.

### LICENCE

(MIT Licence)

Copyright (c) 2012 Academe Computing <jason@academe.co.uk>

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicence, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
