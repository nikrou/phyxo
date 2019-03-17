function Queue(asStack) {
    Object.defineProperties(
        this,
        {
            add: {
                enumerable: true,
                writable: false,
                value: addToQueue
            },
            next: {
                enumerable: true,
                writable: false,
                value: run
            },
            clear: {
                enumerable: true,
                writable: false,
                value: clearQueue
            },
            stop: {
                enumerable: true,
                writable: true,
                value: false
            }
        }
    );

    let queue = [];
    let running = false;
    let stop = false;

    function clearQueue() {
        queue = [];
        return queue;
    }

    function addToQueue() {
        for (let i in arguments) {
            queue.push(arguments[i]);
        }
        if (!running && !this.stop) {
            this.next();
        }
    }

    function run() {
        running = true;
        if (queue.length < 1 || this.stop) {
            running = false;
            return;
        }

        queue.shift();
    }
}

module.exports = Queue;
