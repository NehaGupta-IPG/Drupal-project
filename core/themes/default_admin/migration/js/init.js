/* To inject this as early as possible
 * we use native JS instead of Drupal's behaviors.
 */

// Transform legacy local-storage items to Default Admin keys.
function migrateLegacyStorage() {
  const migrate = (legacyKey, key) => {
    const value = localStorage.getItem(legacyKey);
    if (value !== null && localStorage.getItem(key) === null) {
      localStorage.setItem(key, value);
    }
    localStorage.removeItem(legacyKey);
  };

  localStorage.removeItem('GinDarkMode');
  localStorage.removeItem('Drupal.gin.dark_mode');
  migrate('GinSidebarOpen', 'Drupal.navigation.sidebarExpanded');
  migrate('Drupal.gin.toolbarExpanded', 'Drupal.navigation.sidebarExpanded');
  migrate(
    'Drupal.gin.sidebarExpanded.mobile',
    'Drupal.defaultAdmin.sidebarExpanded.mobile',
  );
  migrate(
    'Drupal.gin.sidebarExpanded.desktop',
    'Drupal.defaultAdmin.sidebarExpanded.desktop',
  );
  migrate('Drupal.gin.sidebarWidth', 'Drupal.defaultAdmin.sidebarWidth');
}

migrateLegacyStorage();

// Dark mode Check.
function defaultAdminInitDarkMode() {
  const darkModeClass = 'dark-mode';

  const darkModeSetting = document.getElementById(
    'default-admin-setting-dark_mode',
  )?.textContent;
  // Set window variable.
  window.defaultAdminDarkMode = darkModeSetting
    ? JSON.parse(darkModeSetting)?.defaultAdminDarkMode
    : 'auto';

  if (
    window.defaultAdminDarkMode == 1 ||
    (window.defaultAdminDarkMode === 'auto' &&
      window.matchMedia('(prefers-color-scheme: dark)').matches)
  ) {
    document.documentElement.classList.add(darkModeClass);
  } else {
    document.documentElement.classList.contains(darkModeClass) === true &&
      document.documentElement.classList.remove(darkModeClass);
  }
}

defaultAdminInitDarkMode();

// Sidebar checks.
if (localStorage.getItem('Drupal.defaultAdmin.sidebarWidth')) {
  const sidebarWidth = localStorage.getItem('Drupal.defaultAdmin.sidebarWidth');
  document.documentElement.style.setProperty(
    '--admin-theme-sidebar-width',
    sidebarWidth,
  );
}
