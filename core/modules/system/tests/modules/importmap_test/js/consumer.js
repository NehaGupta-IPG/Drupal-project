// The 'importmap-test-foo' specifier is resolved by the import map emitted in
// the HTML head, so it does not refer to a file path that ESLint can resolve.
/* eslint-disable import/no-unresolved */
import value from 'importmap-test-foo';

window.importmapTestValue = value;
