/**
 * @file
 * The per-element request queue behind <drupal-treegrid>'s exclusive().
 *
 * Units of work run one at a time, first in first out; the next starts when the
 * previous one's promise settles, whether it resolved or threw. A unit receives
 * a unit object whose methods run directly inside it, so work that is already
 * running in a unit never waits behind the queue it holds. The unit object
 * stops working once its function has settled.
 *
 * Exposes Drupal.treegrid.Queue.
 */

((Drupal) => {
  Drupal.treegrid = Drupal.treegrid || {};

  class Queue {
    /**
     * @param {function(object): object} [extendUnit]
     *   Receives the unit being built and returns more methods for it, keyed by
     *   name. They are guarded like the unit's own methods.
     */
    constructor(extendUnit = () => ({})) {
      this.extendUnit = extendUnit;
      this.tail = Promise.resolve();
      this.pending = 0;
    }

    /**
     * Adds a unit to the queue.
     *
     * @param {function(object): *} fn
     *   The work. Receives the unit object; may return a promise.
     *
     * @return {Promise<*>}
     *   Settles with what fn returned or threw, once its turn has come and gone.
     */
    run(fn) {
      // With nothing ahead, the unit starts at once; its first step then runs
      // before run() returns, so work started on idle never shows as waiting.
      const result =
        this.pending === 0
          ? this.runUnit(fn)
          : this.tail.then(() => this.runUnit(fn));
      this.pending += 1;
      const finished = () => {
        this.pending -= 1;
      };
      this.tail = result.then(finished, finished);
      return result;
    }

    /**
     * Runs one unit and retires its unit object afterwards.
     *
     * @param {function(object): *} fn
     *   The work.
     *
     * @return {Promise<*>}
     *   Settles with what fn returned or threw.
     */
    async runUnit(fn) {
      const { unit, retire } = this.createUnit();
      try {
        return await fn(unit);
      } finally {
        retire();
      }
    }

    /**
     * Builds a unit object.
     *
     * @return {{unit: object, retire: function(): void}}
     *   The unit, and the function that makes its methods throw.
     */
    createUnit() {
      let alive = true;
      const guard =
        (method) =>
        (...args) => {
          if (!alive) {
            throw new Error(
              'A treegrid unit was used after its work finished.',
            );
          }
          return method(...args);
        };
      const unit = {};
      unit.exclusive = guard((fn) => {
        try {
          return Promise.resolve(fn(unit));
        } catch (error) {
          return Promise.reject(error);
        }
      });
      Object.entries(this.extendUnit(unit)).forEach(([name, method]) => {
        unit[name] = guard(method);
      });
      return {
        unit,
        retire: () => {
          alive = false;
        },
      };
    }
  }

  Drupal.treegrid.Queue = Queue;
})(Drupal);
