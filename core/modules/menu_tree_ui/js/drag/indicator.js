/**
 * @file
 * Visual feedback for drag intent: the line indicator (between drops) plus the
 * "as-child" outline highlight, with an aria-live announcement so screen
 * readers track what would happen on release.
 *
 * Exposes Drupal.menuTreeUi.drag.DropIndicator.
 */

((Drupal) => {
  Drupal.menuTreeUi = Drupal.menuTreeUi || {};
  Drupal.menuTreeUi.drag = Drupal.menuTreeUi.drag || {};
  const { rowHeaderRect } = Drupal.menuTreeUi.drag;

  class DropIndicator {
    /**
     * @param {HTMLElement} host
     *   The <menu-tree> host the indicator positions itself within.
     */
    constructor(host) {
      this.host = host;
      this.highlighted = null;
      this.line = document.createElement('div');
      this.line.className = 'menu-tree-ui-drop-indicator';
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
          intent.target.classList.add('menu-tree-ui-row--drop-as-child');
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
        intent.target
          .querySelector('.menu-tree-ui-row__title')
          ?.textContent?.trim() ?? '';
      let message;
      if (intent.kind === 'child') {
        message = `Drop as child of ${title}`;
      } else if (intent.kind === 'before') {
        message = `Drop before ${title}`;
      } else {
        message = `Drop after ${title}`;
      }
      this.liveRegion.textContent = message;
      if (Drupal.announce) {
        Drupal.announce(message);
      }
    }

    /**
     * Positions the drop line explicitly, in host-relative pixels.
     *
     * Used by keyboard move mode to draw an indented line at a chosen depth
     * (drag positions the line from the pointer via render() instead).
     *
     * @param {number} top
     *   Top offset within the host, in px.
     * @param {number} left
     *   Left offset within the host, in px.
     * @param {number} width
     *   Line width, in px.
     */
    lineAt(top, left, width) {
      this.clearChildHighlight();
      this.line.style.display = 'block';
      this.line.style.top = `${top}px`;
      this.line.style.left = `${left}px`;
      this.line.style.width = `${width}px`;
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
        this.highlighted.classList.remove('menu-tree-ui-row--drop-as-child');
        this.highlighted = null;
      }
    }
  }

  Drupal.menuTreeUi.drag.DropIndicator = DropIndicator;
})(Drupal);
