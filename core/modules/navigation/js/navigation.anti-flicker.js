/**
 * @file
 * Prevents flicker of the navigation sidebar on page load.
 */

(() => {
  if (
    localStorage.getItem('Drupal.navigation.sidebarExpanded') !== 'false' &&
    window.matchMedia('(min-width: 1024px)').matches
  ) {
    document.documentElement.setAttribute('data-admin-toolbar', 'expanded');
  }
})();
