/**
 * @file
 * Provides the filtering process for the image styles.
 */

(function (Drupal, once) {
  Drupal.behaviors.imageStylesTableFilterByText = {
    attach(context, settings) {
      const [input] = once(
        'image-style-filter-text',
        'input.image-style-filter-text',
        context,
      );
      if (!input) {
        return;
      }

      const ROW_DISPLAY_STYLE = 'table-row';
      const table = document.querySelector(input.getAttribute('data-table'));
      let rows;

      function filterImageStyleList(e) {
        const query = e.target.value.toLowerCase();

        function showFilteredTableRow(row) {
          const rowText = row.querySelector('td');
          const rowTextMatch =
            rowText.textContent.trim().toLowerCase().indexOf(query) !== -1;
          row.closest('tr').style.display = rowTextMatch
            ? ROW_DISPLAY_STYLE
            : 'none';
        }

        function displayRow(row) {
          row.closest('tr').style.display = ROW_DISPLAY_STYLE;
        }

        if (query.length >= 1) {
          rows.forEach((row) => {
            showFilteredTableRow(row);
          });
        } else {
          rows.forEach((row) => {
            displayRow(row);
          });
        }
      }

      if (table) {
        rows = table.querySelectorAll('tbody tr');
        input.addEventListener('keyup', filterImageStyleList);
        input.addEventListener('change', filterImageStyleList);
      }
    },
  };
})(Drupal, once);
