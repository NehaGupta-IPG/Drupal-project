/**
 * @file
 * Language switcher block script.
 */

(function umamiLanguageSwitcherBlocksScript(Drupal) {
  Drupal.behaviors.umamiLanguageSwitcherBlocks = {
    attach(context) {
      function handleLanguageSwitcher() {
        const languageSwitcherBlocks = context.querySelectorAll(
          '.umami-language-switcher',
        );
        languageSwitcherBlocks.forEach((block) => {
          const toggleButton = block.querySelector(
            '.umami-language-switcher__toggle',
          );
          const languageList = block.querySelector(
            '.umami-language-switcher__content',
          );
          const languageListId = languageList.getAttribute('id');
          toggleButton.setAttribute('aria-controls', languageListId);
          toggleButton.removeAttribute('hidden');
          languageList.setAttribute('hidden', 'hidden');
          toggleButton.addEventListener('click', () => {
            const hidden = languageList.hasAttribute('hidden');
            languageList.toggleAttribute('hidden', !hidden);
            toggleButton.setAttribute('aria-expanded', hidden);
          });
        });
      }
      // We need a timeout to ensure that the language switcher block is
      // rendered before we try to handle it.
      setTimeout(handleLanguageSwitcher, 50);
    },
  };
})(Drupal);
