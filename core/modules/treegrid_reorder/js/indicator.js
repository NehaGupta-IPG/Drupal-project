/**
 * @file
 * Visual feedback for drag intent: the line indicator (between drops) plus the
 * "as-child" outline highlight, with an aria-live announcement so screen
 * readers track what would happen on release.
 *
 * The consumer supplies its own CSS class names and the row-title selector, so
 * this carries no domain-specific markup knowledge.
 *
 * Exposes Drupal.treegridReorder.DropIndicator.
 */

((Drupal) => {
  Drupal.treegridReorder = Drupal.treegridReorder || {};
  const { rowHeaderRect } = Drupal.treegridReorder;

  class DropIndicator {
    /**
     * @param {HTMLElement} host
     *   The element the indicator positions itself within.
     * @param {object} options
     *   { lineClass, dropAsChildClass, titleSelector } — the consumer's CSS
     *   class for the drop line, the class toggled on an as-child target, and
     *   the selector for a row's title text used in announcements.
     */
    constructor(host, options) {
      this.host = host;
      this.lineClass = options.lineClass;
      this.dropAsChildClass = options.dropAsChildClass;
      this.titleSelector = options.titleSelector;
      this.highlighted = null;
      this.line = document.createElement('div');
      this.line.className = this.lineClass;
      this.line.setAttribute('aria-hidden', 'true');
      this.liveRegion = document.createElement('div');
      this.liveRegion.className = 'visually-hidden';
      this.liveRegion.setAttribute('role', 'status');
      this.liveRegion.setAttribute('aria-live', 'polite');
      host.appendChild(this.line);
      host.appendChild(this.liveRegion);
    }

    /**
     * Removes the indicator's DOM nodes.
     */
    destroy() {
      this.line.remove();
      this.liveRegion.remove();
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
          intent.target.classList.add(this.dropAsChildClass);
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
    announce(intent) {
      const title =
        intent.target.querySelector(this.titleSelector)?.textContent?.trim() ??
        '';
      let message;
      if (intent.kind === 'child') {
        message = Drupal.t('Drop as child of @title', { '@title': title });
      } else if (intent.kind === 'before') {
        message = Drupal.t('Drop before @title', { '@title': title });
      } else {
        message = Drupal.t('Drop after @title', { '@title': title });
      }
      this.liveRegion.textContent = message;
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
        this.highlighted.classList.remove(this.dropAsChildClass);
        this.highlighted = null;
      }
    }
  }

  Drupal.treegridReorder.DropIndicator = DropIndicator;
})(Drupal);
