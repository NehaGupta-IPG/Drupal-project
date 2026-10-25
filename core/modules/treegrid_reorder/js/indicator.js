/**
 * @file
 * Visual feedback for drag intent: the line indicator (between drops) plus the
 * "as-child" highlight, with an aria-live announcement so screen readers track
 * what would happen on release.
 *
 * The line is a div with the class treegrid-reorder-indicator, and the as-child
 * target carries data-treegrid-reorder-drop-target="child"; the module's
 * stylesheet draws both. The title read for announcements is the row's
 * data-treegrid-reorder-title element, or else its aria-label.
 *
 * Exposes Drupal.treegridReorder.DropIndicator.
 */

((Drupal) => {
  Drupal.treegridReorder = Drupal.treegridReorder || {};
  const { rowHeaderRect, rowTitle } = Drupal.treegridReorder;

  const DROP_TARGET = 'data-treegrid-reorder-drop-target';

  class DropIndicator {
    /**
     * @param {HTMLElement} host
     *   The element the indicator positions itself within.
     */
    constructor(host) {
      this.host = host;
      this.highlighted = null;
      this.line = document.createElement('div');
      this.line.className = 'treegrid-reorder-indicator';
      this.line.setAttribute('aria-hidden', 'true');
      host.appendChild(this.line);
    }

    /**
     * Removes the indicator's DOM nodes.
     */
    destroy() {
      this.clear();
      this.line.remove();
    }

    /**
     * Renders the indicator for an intent.
     *
     * @param {object} intent
     *   The DropIntent.
     */
    render(intent) {
      if (intent.kind === 'child') {
        this.hideLine();
        if (this.highlighted !== intent.target) {
          this.clearChildHighlight();
          intent.target.setAttribute(DROP_TARGET, 'child');
          this.highlighted = intent.target;
        }
        return;
      }
      this.clearChildHighlight();
      const header = rowHeaderRect(intent.target);
      const hostRect = this.host.getBoundingClientRect();
      const top = intent.kind === 'before' ? header.top : header.bottom;
      this.line.style.display = 'block';
      this.line.style.left = `${header.left - hostRect.left}px`;
      this.line.style.top = `${top - hostRect.top}px`;
      this.line.style.width = `${header.width}px`;
    }

    /**
     * Clears all visual feedback.
     */
    clear() {
      this.hideLine();
      this.clearChildHighlight();
    }

    /**
     * Announces the would-be drop to assistive tech.
     *
     * @param {object} intent
     *   The DropIntent.
     */
    // eslint-disable-next-line class-methods-use-this
    announce(intent) {
      const title = rowTitle(intent.target);
      let message;
      if (intent.kind === 'child') {
        message = Drupal.t('Drop as child of @title', { '@title': title });
      } else if (intent.kind === 'before') {
        message = Drupal.t('Drop before @title', { '@title': title });
      } else {
        message = Drupal.t('Drop after @title', { '@title': title });
      }
      if (Drupal.announce) {
        Drupal.announce(message);
      }
    }

    /**
     * Hides the drop line.
     */
    hideLine() {
      this.line.style.display = 'none';
    }

    /**
     * Removes the as-child highlight, if any.
     */
    clearChildHighlight() {
      if (this.highlighted) {
        this.highlighted.removeAttribute(DROP_TARGET);
        this.highlighted = null;
      }
    }
  }

  Drupal.treegridReorder.DropIndicator = DropIndicator;
})(Drupal);
