/**
 * @file
 * Back to top file.
 */

((Drupal) => {
  const scrollContainer = document.querySelector('.scroll-top');
  const backToTop = document.querySelector('.scroll-top__button');
  const progressCircle = document.querySelector('.scroll-top__progress');

  // Function to toggle visibility of the button based on scroll position
  const toggleButtonVisibility = () => {
    if (window.scrollY > 200) {
      scrollContainer.style.cssText = `
        opacity: 1; 
        visibility: visible;
      `;
    } else {
      scrollContainer.style.cssText = `
        opacity: 0; 
        visibility: hidden;
      `;
    }
  };

  // Function to update the progress bar based on scroll position
  const updateProgressBar = () => {
    const scrollTop = window.scrollY;
    const docHeight =
      document.documentElement.scrollHeight - window.innerHeight;
    const scrollPercent = (scrollTop / docHeight) * 100;
    progressCircle.style.background = `conic-gradient(#1b9ae4 ${scrollPercent}%, transparent ${scrollPercent}%)`;
  };

  // Add the scroll event listener to toggle button visibility and update progress bar
  window.addEventListener('scroll', () => {
    toggleButtonVisibility();
    updateProgressBar();
  });

  // Initial check in case the user has already scrolled down
  toggleButtonVisibility();
  updateProgressBar();

  backToTop.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
})(Drupal);
