class Bar {
  constructor(el) {
    this.el = el;
  }

  init() {
    this.el.textContent = 'Root level bar';
  }
}

export default Bar;
